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
use App\Http\Controllers\Notification\NotificationController;

class InvController extends Controller
{

    function index()
    {
        $data = Asset::get();
        foreach ($data as $d) {
            $member = explode(',', $d->responsible);
            $memberNew = [];
            for ($i = 0; $i < count($member); $i++) {
                $m = str_replace('//', '', $member[$i]);
                $memberNew[$i] = Structure::where('employe_id', $m)->first();
            }
            $d->responsible_list = $memberNew;
            $d->type_name = assetCat::where('code', $d->type)->first()->name;
            $child = AssetChild::where('id_parent', $d->id)->get();
            foreach ($child as $c) {
                $newn = [];
                if ($c->responsible !== NULL) {
                    $cres = explode(',', $c->responsible);

                    for ($a = 0; $a < count($cres); $a++) {
                        $n = str_replace('//', '', $cres[$a]);
                        $newn[$a] = Structure::where('employe_id', $n)->first();
                    }
                }
                $c->res_list = $newn;
            }

            $d->child = $child;
            $d->amount = count($child);
            $d->price = $d->amount * $d->price;
            $d->current = round($d->price - ($this->getAnnual($d->acquisition) * assetCat::where('code', $d->type)->first()->depreciation / 100 * $d->price), 0);
            // harga beli - (umur aset x persentase penyusutan pertahun x harga beli)
            $d->old = $this->getUmur($d->acquisition, date("Y-m-d"));
        }
        return new PostResource(true, 'data asset', $data);
    }

    function show($id)
    {
        $d = Asset::where('id', $id)->first();
        $member = explode(',', $d->responsible);
        $memberNew = [];
        for ($i = 0; $i < count($member); $i++) {
            $m = str_replace('//', '', $member[$i]);
            $memberNew[$i] = Structure::where('employe_id', $m)->first();
        }
        $d->responsible_list = $memberNew;
        $d->type_name = assetCat::where('code', $d->type)->first()->name;
        $d->file_image=public_path('inven'. $d->file);

        $child = AssetChild::where('id_parent', $d->id)->get();
        foreach ($child as $c) {
            $newn = [];
            if ($c->responsible !== NULL) {
                $cres = explode(',', $c->responsible);

                for ($a = 0; $a < count($cres); $a++) {
                    $n = str_replace('//', '', $cres[$a]);
                    $newn[$a] = Structure::where('employe_id', $n)->first();
                }
            }
            $c->res_list = $newn;
        }
        $d->amount = count($child);
        $d->price = $d->amount * $d->price;
        $d->current = round($d->price - ($this->getAnnual($d->acquisition) * assetCat::where('code', $d->type)->first()->depreciation / 100 * $d->price), 0);
        // harga beli - (umur aset x persentase penyusutan pertahun x harga beli)
        $d->child = $child;
        $d->old = $this->getUmur($d->acquisition, date("Y-m-d"));
        return new PostResource(true, 'data asset', $d);
    }

    function getUmur($start, $end)
    {
        $umur = strtotime($end) - strtotime($start);
        if ($umur < (30 * 60 * 60 * 24)) {
            return ($umur / (60 * 60 * 24)) . ' day';
        } else if ($umur < (365 * 60 * 60 * 24)) {
            return (round($umur / (30 * 60 * 60 * 24), 0)) . ' month ' . (($umur % (30 * 60 * 60 * 24)) / (60 * 60 * 24)) . ' day';
        } else {
            return (round($umur / (365 * 60 * 60 * 24), 0)) . ' year ' . round(($umur % (365 * 60 * 60 * 24)) / (30 * 60 * 60 * 24), 0) . ' month';
        }
    }

    function getAnnual($when)
    {
        $umur = strtotime(date("Y-m-d")) - strtotime($when);
        return $umur / (365 * 60 * 60 * 24);
    }

    function store(Request $request)
    {
        $dataImage = explode(',', $request->file);
        $image = base64_decode($dataImage[1], true);
        $image_name = '/' . time() . '_' . $request->type . '.png';
        if (Storage::disk('public_inven')->put($image_name, $image)) {
            $db = new Asset();
            $d = strtotime($request->acquisition);
            $db->asset_number = sprintf("%03d", assetCat::where('code', $request->type)->first()->last_number + 1) . '-PEMA-' . $request->type . '-' . $this->numberToRoman(date('m', $d)) . '-' . date('Y', $d);
            $db->name = $request->name;
            $db->type = $request->type;
            $db->price = $request->price;
            $db->vendor = $request->vendor;
            $db->acquisition = date('Y-m-d', strtotime($request->acquisition));
            $db->file = $image_name;
            $db->location = $request->location;
            $db->responsible = $request->responsible;
            // return new PostResource(false, 'sdgsg', [$this->numberToRoman(3)]);

            if ($db->save()) {
                $numb = explode('-PEMA-', $db->asset_number);
                for ($i = 1; $i <= (int) $request->amount; $i++) {
                    AssetChild::create(['asset_number' => $numb[0] . '-' . sprintf("%03d", $i) . '-PEMA-' . $numb[1], 'responsible' => $request->responsible, 'id_parent' => $db->id,]);
                }
                $l = new AssetLog();
                $l->id_asset = $db->id;
                $l->id_employee = Employe::employeId();
                $l->activity = 'Add new Asset data';
                if ($l->save()) {
                    return new PostResource(true, 'sdgsg', $db->acquisition);
                }
            }
        }
        return new PostResource(false, 'failed', $request->all());
    }

    function updateAsset(Request $request)
    {
        $db = Asset::find($request->id);
        $db->name = $request->name;
        $db->price = $request->price;
        $db->vendor = $request->vendor;
        $db->acquisition = date('Y-m-d', strtotime($request->acquisition));
        $db->location = $request->location;
        $db->responsible = $request->responsible;
        if ($db->save()) {
            $l = new AssetLog();
            $l->id_asset = $request->id;
            $l->id_employee = Employe::employeId();
            $l->activity = 'Update asset data';
            if ($l->save()) {
                return new PostResource(true, 'updated succesfully', []);
            }
        } else {
            return new PostResource(false, 'failed', []);
        }
    }

    function uChild(Request $request)
    {
        $db = AssetChild::find($request->id);
        $db->responsible = $request->responsible;
        if ($db->save()) {
            $l = new AssetLog();
            $l->id_asset = $db->id_parent;
            $l->id_employee = Employe::employeId();
            $l->activity = 'Update Responsible for asset child ' . $db->asset_number;
            if ($l->save()) {
                return new PostResource(true, 'Changed Succesfully !!', []);
            }
        } else {
            return new PostResource(false, 'sgsdg', []);
        }
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
        $ids = array();
        foreach ($request->data as $d) {
            array_push($ids, $d['id']);
        }
        if (Asset::destroy($ids)) {
            return new PostResource(true, 'Deleted Successfully !!', $ids);
        }
    }
    function addChild(Request $request)
    {
        $num = explode('-PEMA-', AssetChild::where('id_parent', $request->id_parent)->orderBy('asset_number', 'DESC')->get()->first()->asset_number);
        $numP = explode('-', $num[0]);
        $resp = Asset::find($request->id_parent)->responsible;
        for ($i = $numP[1] + 1; $i <= $numP[1] + $request->amount; $i++) {
            AssetChild::create(['asset_number' => $numP[0] . '-' . sprintf("%03d", $i) . '-PEMA-' . $num[1], 'id_parent' => $request->id_parent, 'responsible' => $resp]);
        }
        $l = new AssetLog();
        $l->id_asset = $request->id_parent;
        $l->id_employee = Employe::employeId();
        $l->activity = 'Add ' . $request->amount . ' childs to asset';
        if ($l->save()) {
            return new PostResource(true, 'sucess', []);
        }
    }

    function changeImage(Request $request)
    {
        $db = Asset::find($request->id);
        $dataImage = explode(',', $request->file);
        $image = base64_decode($dataImage[1], true);
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
    }

    function getAssetOnMe()
    {
        $data = AssetChild::where('responsible', 'like', '%//' . Employe::employeId() . '//%')->get();
        foreach ($data as $d) {
            $d->name = Asset::find($d->id_parent)->name;
            if (count(AssetServis::where('asset_child', $d->id)->whereNotIn('status', ['done', 'reject'])->get()) > 0) {
                $d->request_service = true;
            } else {
                $d->request_service = false;
            }

            $d->location = Asset::find($d->id_parent)->location;
            $d->file = Asset::find($d->id_parent)->file;
            $d->type_name = assetCat::where('code', Asset::find($d->id_parent)->type)->first()->name;
            $newn = [];
            $cres = explode(',', $d->responsible);
            for ($a = 0; $a < count($cres); $a++) {
                $n = str_replace('//', '', $cres[$a]);
                $newn[$a] = Structure::where('employe_id', $n)->first();
            }
            $d->res_list = $newn;
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
        foreach ($data as $d) {
            $d->requester = Employe::where('employe_id', $d->request_by)->first()->first_name;
            $d->asset_number = AssetChild::find($d->asset_child)->asset_number;
            $d->asset_name = Asset::find(AssetChild::find($d->asset_child)->id_parent)->name;
            $d->asset_id = Asset::find(AssetChild::find($d->asset_child)->id_parent)->id;
            $d->location = Asset::find(AssetChild::find($d->asset_child)->id_parent)->location;
        }
        return new PostResource(true, 'dsgsdg', $data);
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
