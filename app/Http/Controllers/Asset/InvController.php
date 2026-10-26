<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inven\Asset;
use App\Models\Inven\assetCat;
use App\Models\Inven\AssetChild;
use App\Models\Inven\AssetLog;
use App\Models\Inven\AssetServis;
use App\Models\Employe;
use App\Http\Resources\PostResource;
use App\Models\Structure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Notification\NotificationController;

class InvController extends Controller
{

    function index()
    {
        $data = Asset::get();
        $this->withDetails($data);
        return new PostResource(true, 'data asset', $data);
    }

    function show($id)
    {
        $d = Asset::where('id', $id)->first();
        if (!$d) {
            return response()->json(['success' => false, 'message' => 'Asset tidak ditemukan', 'data' => null], 404);
        }
        $this->withDetails(collect([$d]));
        $d->file_image=$this->convertImageToBase64(public_path('inven'. $d->file));
        return new PostResource(true, 'data asset', $d);
    }

    // Looks up children, categories and responsible employees once for all rows
    // (struktur_lengkap_oke per row took ~2 minutes for the full list)
    private function withDetails($assets)
    {
        $children = AssetChild::whereIn('id_parent', $assets->pluck('id'))->get()->groupBy('id_parent');
        $cats = assetCat::get()->keyBy('code');
        $people = $this->people($assets->pluck('responsible')->merge($children->flatten()->pluck('responsible')));

        foreach ($assets as $d) {
            $cat = $cats->get($d->type);
            $d->responsible_list = $this->responsibleList($d->responsible, $people);
            $d->type_name = $cat->name ?? null;
            $child = $children->get($d->id, collect())->values();
            foreach ($child as $c) {
                $c->res_list = $c->responsible !== NULL ? $this->responsibleList($c->responsible, $people) : [];
            }
            $d->child = $child;
            $d->amount = count($child);
            $d->price = $d->amount * $d->price;
            // harga beli - (umur aset x persentase penyusutan pertahun x harga beli), tidak kurang dari 0
            $d->current = max(0, round($d->price - ($this->getAnnual($d->acquisition) * ($cat->depreciation ?? 0) / 100 * $d->price), 0));
            $d->old = $this->getUmur($d->acquisition, date("Y-m-d"));
        }

        return $assets;
    }

    // responsible is stored as "//id1//,//id2//"
    private function people($responsibles)
    {
        $ids = $responsibles->filter()->flatMap(fn ($r) => explode(',', str_replace('//', '', $r)))->unique()->values();

        return Structure::whereIn('employe_id', $ids)->get()->unique('employe_id')->keyBy('employe_id');
    }

    private function responsibleList($responsible, $people)
    {
        return array_map(fn ($m) => $people->get(str_replace('//', '', $m)), explode(',', (string) $responsible));
    }

    function getUmur($start, $end)
    {
        $umur = (new \DateTime($start))->diff(new \DateTime($end));
        if ($umur->y === 0 && $umur->m === 0) {
            return $umur->d . ' day';
        } else if ($umur->y === 0) {
            return $umur->m . ' month ' . $umur->d . ' day';
        } else {
            return $umur->y . ' year ' . $umur->m . ' month';
        }
    }

    function getAnnual($when)
    {
        $umur = strtotime(date("Y-m-d")) - strtotime($when);
        return $umur / (365 * 60 * 60 * 24);
    }

    private function invalid($validator)
    {
        return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'data' => $validator->errors()], 422);
    }

    function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|exists:asset_cats,code',
            'price' => 'required|integer|min:0',
            'vendor' => 'nullable|string|max:255',
            'acquisition' => 'required|date|before_or_equal:today',
            'amount' => 'required|integer|min:1|max:999',
            'file' => 'required|string',
            'location' => 'required|string|max:255',
            'responsible' => 'required|string|max:255',
        ], [], ['name' => 'nama', 'type' => 'jenis', 'price' => 'harga beli', 'acquisition' => 'tanggal perolehan', 'amount' => 'jumlah', 'file' => 'gambar', 'location' => 'lokasi', 'responsible' => 'penanggung jawab']);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }
        $dataImage = explode(',', $request->file);
        $image = base64_decode($dataImage[1] ?? '', true);
        if (!$image || !@getimagesizefromstring($image)) {
            return response()->json(['success' => false, 'message' => 'Gambar tidak valid'], 422);
        }
        $image_name = '/' . time() . '_' . $request->type . '.png';
        if (!Storage::disk('public_inven')->put($image_name, $image)) {
            return new PostResource(false, 'Gambar gagal disimpan', []);
        }

        $db = DB::transaction(function () use ($request, $image_name) {
            // Lock the category row so two saves at once can't take the same number (a trigger bumps last_number on insert)
            $cat = assetCat::where('code', $request->type)->lockForUpdate()->first();
            $db = new Asset();
            $d = strtotime($request->acquisition);
            $db->asset_number = sprintf("%03d", $cat->last_number + 1) . '-PEMA-' . $request->type . '-' . $this->numberToRoman(date('m', $d)) . '-' . date('Y', $d);
            $db->name = $request->name;
            $db->type = $request->type;
            $db->price = $request->price;
            $db->vendor = $request->vendor;
            $db->acquisition = date('Y-m-d', $d);
            $db->file = $image_name;
            $db->location = $request->location;
            $db->responsible = $request->responsible;
            $db->save();

            $numb = explode('-PEMA-', $db->asset_number);
            for ($i = 1; $i <= (int) $request->amount; $i++) {
                AssetChild::create(['asset_number' => $numb[0] . '-' . sprintf("%03d", $i) . '-PEMA-' . $numb[1], 'responsible' => $request->responsible, 'id_parent' => $db->id,]);
            }
            $this->log($db->id, 'Add new Asset data');

            return $db;
        });

        return new PostResource(true, 'Asset ' . $db->asset_number . ' berhasil disimpan', $db->acquisition);
    }

    private function log($idAsset, $activity)
    {
        $l = new AssetLog();
        $l->id_asset = $idAsset;
        $l->id_employee = Employe::employeId();
        $l->activity = $activity;
        $l->save();
    }

    function updateAsset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:assets,id',
            'name' => 'required|string|max:255',
            'price' => 'required|integer|min:0',
            'vendor' => 'nullable|string|max:255',
            'acquisition' => 'required|date|before_or_equal:today',
            'location' => 'required|string|max:255',
            'responsible' => 'required|string|max:255',
        ], [], ['name' => 'nama', 'price' => 'harga beli', 'acquisition' => 'tanggal perolehan', 'location' => 'lokasi', 'responsible' => 'penanggung jawab']);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }
        $db = Asset::find($request->id);
        $db->name = $request->name;
        $db->price = $request->price;
        $db->vendor = $request->vendor;
        $db->acquisition = date('Y-m-d', strtotime($request->acquisition));
        $db->location = $request->location;
        $db->responsible = $request->responsible;
        if ($db->save()) {
            $this->log($request->id, 'Update asset data');
            return new PostResource(true, 'updated succesfully', []);
        }
        return new PostResource(false, 'failed', []);
    }

    function uChild(Request $request)
    {
        $db = AssetChild::find($request->id);
        if (!$db) {
            return new PostResource(false, 'Asset tidak ditemukan', []);
        }
        $db->responsible = $request->responsible;
        if ($db->save()) {
            $this->log($db->id_parent, 'Update Responsible for asset child ' . $db->asset_number);
            return new PostResource(true, 'Changed Succesfully !!', []);
        }
        return new PostResource(false, 'failed', []);
    }

    function deleteChild(Request $request)
    {
        $ids = array();
        $chekid = array();
        foreach ($request->data as $d) {
            array_push($ids, $d['id']);
        }
        foreach (AssetChild::where('id_parent', $request->data[0]['id_parent'])->get() as $t) {
            array_push($chekid, $t['id']);
        }

        if (count($ids) >= count($chekid)) {
            return new PostResource(false, ' Failed !! You have to leave one row', []);
        } else {
            if (AssetChild::destroy($ids)) {
                $l = new AssetLog();
                $l->id_asset = $request->data[0]['id_parent'];
                $l->id_employee = Employe::employeId();
                $l->activity = 'Delete ' . count($ids) . ' childs of asset';
                if ($l->save()) {
                    return new PostResource(true, 'Deleted Succesfully !', []);
                }
            }
        }

        return new PostResource(array_diff($ids, $chekid) == [], 'berhasil', count($chekid));
    }

    function deleteAsset(Request $request)
    {
        $ids = collect($request->data)->pluck('id')->filter()->values()->all();
        if (!$ids) {
            return new PostResource(false, 'Pilih asset yang akan dihapus', []);
        }
        // Children go too, otherwise they stay on people's "my responsibility" list without a parent
        $deleted = DB::transaction(function () use ($ids) {
            AssetChild::whereIn('id_parent', $ids)->delete();
            return Asset::destroy($ids);
        });
        if ($deleted) {
            return new PostResource(true, 'Deleted Successfully !!', $ids);
        }
        return new PostResource(false, 'Asset tidak ditemukan', []);
    }
    function addChild(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_parent' => 'required|exists:assets,id',
            'amount' => 'required|integer|min:1|max:999',
        ], [], ['amount' => 'jumlah']);
        if ($validator->fails()) {
            return $this->invalid($validator);
        }
        $last = AssetChild::where('id_parent', $request->id_parent)->orderBy('asset_number', 'DESC')->first();
        if (!$last) {
            return new PostResource(false, 'Asset ini belum punya nomor turunan', []);
        }
        $num = explode('-PEMA-', $last->asset_number);
        $numP = explode('-', $num[0]);
        $resp = Asset::find($request->id_parent)->responsible;
        for ($i = $numP[1] + 1; $i <= $numP[1] + $request->amount; $i++) {
            AssetChild::create(['asset_number' => $numP[0] . '-' . sprintf("%03d", $i) . '-PEMA-' . $num[1], 'id_parent' => $request->id_parent, 'responsible' => $resp]);
        }
        $this->log($request->id_parent, 'Add ' . $request->amount . ' childs to asset');
        return new PostResource(true, 'sucess', []);
    }

    function changeImage(Request $request)
    {
        $db = Asset::find($request->id);
        $dataImage = explode(',', (string) $request->file);
        $image = base64_decode($dataImage[1] ?? '', true);
        if (!$db || !$image || !@getimagesizefromstring($image)) {
            return response()->json(['success' => false, 'message' => 'Gambar tidak valid'], 422);
        }
        if ($db->file === '/placeholder_asset.jpeg') {
            $image_name = '/' . $db->asset_number . '.png';
        } else {
            $image_name = $db->file;
        }


        if (Storage::disk('public_inven')->put($image_name, $image)) {
            $db1 = Asset::find($request->id);
            $db1->file = $image_name;
            $l = new AssetLog();
            $l->id_asset = $request->id;
            $l->id_employee = Employe::employeId();
            $l->activity = 'Update asset image';
            if ($l->save() && $db1->save()) {
                return new PostResource(true, 'Changed Succesfully !!', []);
            }
        }
        return new PostResource(false, 'Gambar gagal disimpan', []);
    }

    function getAssetOnMe()
    {
        $data = AssetChild::where('responsible', 'like', '%//' . Employe::employeId() . '//%')
            ->whereIn('id_parent', Asset::select('id'))->get();
        $parents = Asset::whereIn('id', $data->pluck('id_parent'))->get()->keyBy('id');
        $cats = assetCat::get()->keyBy('code');
        $inService = AssetServis::whereIn('asset_child', $data->pluck('id'))->whereNotIn('status', ['done', 'reject'])->pluck('asset_child')->flip();
        $people = $this->people($data->pluck('responsible'));
        foreach ($data as $d) {
            $parent = $parents->get($d->id_parent);
            $d->name = $parent->name;
            $d->request_service = $inService->has($d->id);
            $d->location = $parent->location;
            $d->file = $parent->file;
            $d->type_name = $cats->get($parent->type)->name ?? '-';
            $d->res_list = $this->responsibleList($d->responsible, $people);
        }

        return new PostResource(true, 'data asset', $data);
    }

    function numberToRoman($number)
    {
        $map = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
        $returnValue = '';
        while ($number > 0) {
            foreach ($map as $roman => $int) {
                if ($number >= $int) {
                    $number -= $int;
                    $returnValue .= $roman;
                    break;
                }
            }
        }
        return $returnValue;
    }

    function requestService(Request $request)
    {

        $db = new AssetServis();

        $db->asset_child = $request->asset_child;
        $db->complaint = $request->complaint;
        $db->request_by = Employe::employeId();
        $db->status = 'submit';
        if ($db->save()) {
            $l = new AssetLog();
            $l->id_asset = AssetChild::find($request->asset_child)->id_parent;
            $l->id_employee = Employe::employeId();
            $l->activity = 'Request Service for ' . AssetChild::find($request->asset_child)->asset_number;
            if ($l->save()) {

                $recipients = Structure::select('employe_id')->where('roles', 'like', '%PicAsset%')->get();
                $entityId = '#request';
                NotificationController::new('REQUEST_SERVICE', $recipients, $entityId);
                return new PostResource(true, 'success !!', []);
            }
            // return new PostResource(true, 'success', []);
        }
    }

    function getRequest()
    {
        if (in_array("PicAsset", auth()->user()->roles)) {
            $data = AssetServis::where('status', '!=', 'reject')->where('status', '!=', 'done')->get();
        } else {
            $data = AssetServis::where('request_by', Employe::employeId())->latest()->get();
        }
        $children = AssetChild::whereIn('id', $data->pluck('asset_child'))->get()->keyBy('id');
        $parents = Asset::whereIn('id', $children->pluck('id_parent'))->get()->keyBy('id');
        $requesters = Employe::whereIn('employe_id', $data->pluck('request_by'))->pluck('first_name', 'employe_id');
        foreach ($data as $d) {
            $child = $children->get($d->asset_child);
            $parent = $child ? $parents->get($child->id_parent) : null;
            $d->requester = $requesters->get($d->request_by);
            $d->asset_number = $child->asset_number ?? '(asset sudah dihapus)';
            $d->asset_name = $parent->name ?? '(asset sudah dihapus)';
            $d->asset_id = $parent->id ?? null;
            $d->location = $parent->location ?? '-';
        }
        return new PostResource(true, 'data service', $data);
    }

    function updateStatus(Request $request)
    {
        $db = AssetServis::find($request->id);
        $db->status = $request->status;
        if ($db->save()) {
            $l = new AssetLog();
            $l->id_asset = AssetChild::find($db->asset_child)->id_parent;
            $l->id_employee = Employe::employeId();
            $l->activity = 'Service for ' . AssetChild::find($db->asset_child)->asset_number . ' was ' . $request->status;
            if ($l->save()) {

                $recipients = $db->request_by;
                $entityId = '#request';
                if ($request->status === 'progress') {
                    NotificationController::new('SERVICE_APPROVED', $recipients, $entityId);
                } else {
                    NotificationController::new('SERVICE_REJECTED', $recipients, $entityId);
                }

                return new PostResource(true, 'success !!', []);
            }
        }
    }

    function uploadBukti(Request $request)
    {
        $name = 'service/' . $request->asset_number . '/' . $request->id . '.pdf';
        $file = base64_decode($request->base64file, true);
        if (Storage::disk('public_inven')->put($name, $file)) {
            return new PostResource(true, 'success !!', ["filename" => $name]);
        }
    }

    function doneService(Request $request)
    {
        $db = AssetServis::find($request->id);
        $db->status = 'done';
        $db->cost = $request->cost;
        $db->proof = $request->proof;
        if ($db->save()) {
            $l = new AssetLog();
            $l->id_asset = AssetChild::find($db->asset_child)->id_parent;
            $l->id_employee = Employe::employeId();
            $l->activity = 'Service done for ' . AssetChild::find($db->asset_child)->asset_number;
            if ($l->save()) {
                $recipients = $db->request_by;
                $entityId = '#request';
                NotificationController::new('SERVICE_DONE', $recipients, $entityId);
                return new PostResource(true, 'success !!', []);
            }
        }

    }

    public function convertImageToBase64($imagePath)
    {
        if (file_exists($imagePath)) {
            
            $fileContent = file_get_contents($imagePath);


            $mimeType = mime_content_type($imagePath);

            // Encode ke base64
            $base64Image = 'data:' . $mimeType . ';base64,' . base64_encode($fileContent);

            return $base64Image;
        }

        return '';
    }

}
