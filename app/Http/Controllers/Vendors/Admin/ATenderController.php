<?php

namespace App\Http\Controllers\Vendors\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor\Tender;
use App\Models\Vendor\TenderPeserta;
use App\Models\Vendor\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\Structure;
use App\Models\Vendor\ViewPerusahaan;
use App\Models\Vendor\MasterKbli;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification ;


class ATenderController extends Controller
{
    function store(Request $request)
    {
        $request->validate([
            'metode_pengadaan' => 'required',
            'nama_tender' => 'required',
            'lokasi' => 'required',
            'tgl_pendaftaran' => 'required',
            'batas_pendaftaran' => 'required',
            'jenis_pengadaan' => 'required',
            'kbli' => 'required',
            'hps' => 'required',
            'dok_tender' => 'required',
            'dok_deskripsi_tender' => 'required'
        ]);

        $file_dok_tender = base64_decode($request->dok_tender, true);
        $file_dok_deskripsi_tender = base64_decode($request->dok_deskripsi_tender, true);
        // $file_doc_penyampaian_penawaran = base64_decode($request->doc_penyampaian_penawaran, true);
        $file_dok_untuk_vendor =  base64_decode($request->dok_untuk_vendor, true);

        $dok_tender = 'dok_tender.pdf';
        $dok_deskripsi_tender = 'dok_deskripsi_tender.pdf';
        // $doc_penyampaian_penawaran = 'doc_penyampaian_penawaran.docx';
        $dok_untuk_vendor = 'template_dokumen_pengadaan.rar';


        $t = new Tender();
        $t->user_id = Employe::employeId();
        $t->pilihan_tender = $request->pilihan_tender;
        $t->metode_pengadaan = $request->metode_pengadaan;
        $t->sistem_kualifikasi = $request->sistem_kualifikasi;
        $t->nama_tender = $request->nama_tender;
        $t->slug = Str::of($request->nama_tender)->slug('-');
        $t->lokasi = $request->lokasi;
        $t->tgl_pendaftaran = $request->tgl_pendaftaran;
        $t->batas_pendaftaran = $request->batas_pendaftaran;
        // $t->masa_sanggah = $request->masa_sanggah;
        // $t->tgl_masa_sanggah = $request->tgl_masa_sanggah;
        $t->jenis_pengadaan = $request->jenis_pengadaan;
        $t->hps = $request->hps;
        $t->kbli = json_encode($request->kbli);
        $t->dok_template = $dok_untuk_vendor;
        $t->centang_dok_wajib = json_encode($request->centang_dok_wajib);
        $t->dok_tender = $dok_tender;
        $t->dok_deskripsi_tender = $dok_deskripsi_tender;
        // $t->doc_penyampaian_penawaran = $doc_penyampaian_penawaran;

        $userRoles = Auth::user()->roles;
        if(in_array('AdminVendorUmum', $userRoles)){
            $t->owner = 'umum';
        }else{
            $t->owner = 'scm';
        }

        if ($t->save()) {
            Storage::disk('public_vendor')->put('tender/' . $t->id_tender . '/' . $dok_tender, $file_dok_tender);
            Storage::disk('public_vendor')->put('tender/' . $t->id_tender . '/' . $dok_deskripsi_tender, $file_dok_deskripsi_tender);
            Storage::disk('public_vendor')->put('tender/' . $t->id_tender . '/' . $dok_untuk_vendor, $file_dok_untuk_vendor);
            // Storage::disk('public_vendor')->put('tender/' . $t->id_tender . '/' . $doc_penyampaian_penawaran, $file_doc_penyampaian_penawaran);

            if($t->metode_pengadaan === 'seleksi_terbatas' || $t->metode_pengadaan === 'tender_terbatas'){
                $participants = $request->company_selected;
                for ($i=0; $i < count($participants); $i++) {
                    
                    if($participants[$i]['value'] === 'all_vendor'){
                        // ambil semua vendor by tipe penyedia
                        $vendors = Perusahaan::select('id')
                                    ->where('tipe', $request->tipe_penyedia)
                                    ->get();

                        for ($v=0; $v < count($vendors); $v++) { 
                            // masukkan semua vendor ke table tender peserta
                            TenderPeserta::create([
                                'perusahaan_id' => $vendors[$v]->id,
                                'tender_id' => $t->id_tender
                            ]);
                        }
                    }else{
                        // masukkan peserta ke table tender peserta
                        TenderPeserta::create([
                            'perusahaan_id' => $participants[$i]['value'],
                            'tender_id' => $t->id_tender
                        ]);
                    }
                }
            }

            return new PostResource(true, 'Tender Inserted !', $t);
        } else {
            return new PostResource(false, 'Failed Tender Insert !', []);
        }
    }

    function index()
    {
        $userRoles = Auth::user()->roles;

        $owner = in_array('AdminVendorUmum', $userRoles) ? 'umum' : 'scm';

        $tenders = Tender::where('owner', $owner)
                ->orderBy('id_tender', 'DESC')
                ->get();

        return new PostResource(true, 'List Tender', $tenders);
    }

    function showPer($id)
    {
        $list = [];
        $ikut = TenderPeserta::where('tender_id', $id)->where('status', 'submit_dokumen')->get();
        for ($i = 0; $i < count($ikut); $i++) {
            $list[$i]['value'] = $ikut[$i]->perusahaan_id;
            $list[$i]['label'] = ViewPerusahaan::find($ikut[$i]->perusahaan_id)->bentuk_usaha . ' ' . ViewPerusahaan::find($ikut[$i]->perusahaan_id)->nama_perusahaan;
        }

        return new PostResource(true, 'Tender', $list);
    }

    function show($id)
    {
        $td = Tender::where('id_tender', $id)->first();
        $kbliList = MasterKbli::whereIn('nomor_kbli', json_decode($td->kbli))->get();

        $kblis = [];
        if (count($kbliList) > 0) {
            for ($i = 0; $i < count($kbliList); $i++) {
                $l[$i] = [
                    'value' => $kbliList[$i]->nomor_kbli,
                    'label' =>  $kbliList[$i]->nomor_kbli . " - " . $kbliList[$i]->nama_kbli
                ];

                array_push($kblis, $l[$i]);
            }
        }

        $td['kbli_list'] = $kblis;

        if (file_exists(public_path('vendor_file/tender/'.$id.'/'.$td->dok_tender))) {
            $td['dok_tender_base64'] = base64_encode(file_get_contents(public_path('vendor_file/tender/'.$id.'/'.$td->dok_tender)));
        }

        if (file_exists(public_path('vendor_file/tender/'.$id.'/'.$td->dok_deskripsi_tender))) {
            $td['dok_desk_tender_base64'] = base64_encode(file_get_contents(public_path('vendor_file/tender/'.$id.'/'.$td->dok_deskripsi_tender)));
        }
        if (file_exists(public_path('vendor_file/tender/'.$id.'/'.$td->doc_penyampaian_penawaran))) {
            // $td['doc_penyampaian_penawaran_base64'] = base64_encode(file_get_contents(public_path('vendor_file/tender/'.$id.'/'.$td->doc_penyampaian_penawaran)));
            $td['doc_penyampaian_penawaran_base64'] = 'vendor_file/tender/'.$id.'/'.$td->doc_penyampaian_penawaran;
        }

        if (count(TenderPeserta::where('tender_id', $id)->get()) > 0) {
            $td->perusahaan_yang_ikut = TenderPeserta::where('tender_id', $id)->get();
            foreach ($td->perusahaan_yang_ikut as $p) {
                $p->detail = Perusahaan::find($p->perusahaan_id);
                $p->value = ViewPerusahaan::find($p->perusahaan_id)->id;
                $p->label = ViewPerusahaan::find($p->perusahaan_id)->bentuk_usaha . ' ' . ViewPerusahaan::find($p->perusahaan_id)->nama_perusahaan;
            }
        } else {
            $td->perusahaan_yang_ikut = [];
        }

        return new PostResource(true, 'Tender', $td);
    }

    function update(Request $request)
    {
        $t = Tender::find($request->id);
        if ($request->dok_tender !== '') {
            $file_dok_tender = base64_decode($request->dok_tender, true);
            $dok_tender = 'dok_tender.pdf';
            Storage::disk('public_vendor')->put('tender/' . $request->id . '/' . $dok_tender, $file_dok_tender);
            $t->dok_tender = $dok_tender;
        }
        if ($request->dok_deskripsi_tender !== '') {
            $file_dok_deskripsi_tender = base64_decode($request->dok_deskripsi_tender, true);
            $dok_deskripsi_tender = 'dok_deskripsi_tender.pdf';
            Storage::disk('public_vendor')->put('tender/' . $request->id . '/' . $dok_deskripsi_tender, $file_dok_deskripsi_tender);
            $t->dok_deskripsi_tender = $dok_deskripsi_tender;
        }
        if($request->doc_penyampaian_penawaran!==''){
            $file_doc_penyampaian_penawaran = base64_decode($request->doc_penyampaian_penawaran, true);
            $doc_penyampaian_penawaran = 'doc_penyampaian_penawaran.docx';
            Storage::disk('public_vendor')->put('tender/' . $request->id . '/' . $doc_penyampaian_penawaran, $file_doc_penyampaian_penawaran);
            $t->doc_penyampaian_penawaran = $doc_penyampaian_penawaran;
        }

        $t->pilihan_tender = $request->pilihan_tender;
        $t->metode_pengadaan = $request->metode_pengadaan;
        $t->sistem_kualifikasi = $request->sistem_kualifikasi;
        $t->nama_tender = $request->nama_tender;
        $t->slug = Str::of($request->nama_tender)->slug('-');
        $t->lokasi = $request->lokasi;
        $t->tgl_pendaftaran = $request->tgl_pendaftaran;
        $t->batas_pendaftaran = $request->batas_pendaftaran;
        // $t->masa_sanggah = $request->masa_sanggah;
        // $t->tgl_masa_sanggah = $request->tgl_masa_sanggah;
        $t->jenis_pengadaan = $request->jenis_pengadaan;
        $t->hps = $request->hps;
        $t->kbli = json_encode($request->kbli);
        $t->centang_dok_wajib = json_encode($request->centang_dok_wajib);
        if ($t->save()) {
            return new PostResource(true, 'Tender  updated !', []);
        } else {
            return new PostResource(false, 'Failed Tender update !', []);
        }
    }

    public function deleteTender($id)
    {
        $t = Tender::find($id);
        if ($t->delete()) {
            return new PostResource(true, 'Tender  deleted !', []);
        }
    }

    public function setTahap2($id, Request $request)
    {

        $t2d = $request->list_peserta;
        for ($i = 0; $i < count($t2d); $i++) {
            $f = TenderPeserta::where('perusahaan_id', $t2d[$i])->where('tender_id', $id)->first();
            $f->status = 'lulus_tahap_1';
            $f->save();
        }
        return new PostResource(true, 'tahap 2 submit', $request->list_peserta);
    }

    public function setPemenang($id, Request $request)
    {

        $t = TenderPeserta::where('tender_id', $id)->where('perusahaan_id', $request->list_peserta)->first();
        $t->status = 'pemenang';
        if ($t->save()) {
            $tender = Tender::find($id);
            $tender->status_tender = 'tutup';
            if ($tender->save()) {
                return new PostResource(true, 'pemenang submit', []);
            }
        }
    }

    public function getTahap2($id)
    {
        $data = TenderPeserta::where('tender_id', $id)->where('status', 'lulus_tahap_1')->get();
        foreach ($data as $d) {
            $d->value = $d->perusahaan_id;
            $d->label = ViewPerusahaan::find($d->perusahaan_id)->bentuk_usaha . ' ' . ViewPerusahaan::find($d->perusahaan_id)->nama_perusahaan;
        }

        return new PostResource(true, 'tahap dua', $data);
    }

    public function ba($id, Request $request)
    {
        $t = Tender::find($id);
        Storage::disk('public_vendor')->put('tender/' . $id . '/' . $request->key . '.pdf', base64_decode($request->file, true));
        $t[$request->key] = 'tender/' . $id . '/' . $request->key . '.pdf';
        $t['status_approval'] = $request->key === 'upload_ba_pemenang' ? 'submit_pemenang' : 'submit_tahap_2';

        if ($t->save()) {

            $employeId = Employe::employeId();
            $structure =  Structure::select('direct_atasan')
                    ->where('employe_id', $employeId)
                    ->first();

            $directSupervisorId = $structure->direct_atasan;

            $notifData = [
                'from_employe' => $employeId,
                'to_employe' => $directSupervisorId,
                'title' => 'Berita Acara Tender',
                'desc' => 'Permintaan approval',
                'category' => 'tender',
            ];

            $newNotification = new Notification($notifData);
            $newNotification->save();

            return response()->json([
                "success" => true,
                "message" => $request->key . "file sudah di upload"
            ], 200);
        }
    }

    public function updateTenderStatus(Request $request, $tenderId)
    {
        $fileDocument = $request->file('file_document');

        if($fileDocument){
            $fileDocument->storeAs('public/documents', $fileDocument->hashName());
            $status_document = $fileDocument->hashName();
        }else{
            $status_document = null;
        }

        $tender = Tender::find($tenderId);
        $tender->status_tender = $request->status_tender;
        $tender->status_document = $status_document;
        $tender->save();

        return response()->json([
            "status" => true,
            "message" => 'Tender updated successfully',
        ], 200);
    }

    public function approvalBa()
    {

        $employeId = Employe::employeId();

        $needApprovalTenders = Tender::where('status_approval', 'submit_pemenang')
                            ->orWhere('status_approval', 'submit_tahap_2')
                            ->get();

        $approvalData = []; 
        if($needApprovalTenders){

            for ($at=0; $at < count($needApprovalTenders); $at++) { 
                $adminId = $needApprovalTenders[$at]->user_id;
                $AdminDirectSupervisor = Structure::select('direct_atasan')
                                ->where('employe_id', $adminId)
                                ->first();

                $directSupervisorId = $AdminDirectSupervisor->direct_atasan;

                if($directSupervisorId === $employeId){

                    $needApprovalTenders[$at]->pesertamm = TenderPeserta::select('perusahaan.bentuk_usaha', 'perusahaan.nama_perusahaan')
                                                    ->join('perusahaan', 'perusahaan.id', '=', 'tender_peserta.perusahaan_id')
                                                    ->where('tender_peserta.tender_id', $needApprovalTenders[$at]->id_tender)
                                                    ->get();

                    $needApprovalTenders[$at]->lulus_tahap_1 = TenderPeserta::select('perusahaan.bentuk_usaha', 'perusahaan.nama_perusahaan')
                                                            ->join('perusahaan', 'perusahaan.id', '=', 'tender_peserta.perusahaan_id')
                                                            ->where([
                                                                'tender_peserta.tender_id' => $needApprovalTenders[$at]->id_tender,
                                                                'tender_peserta.status' => 'lulus_tahap_1'
                                                            ])
                                                            ->get();

                    $needApprovalTenders[$at]->pemenang = TenderPeserta::select('perusahaan.bentuk_usaha', 'perusahaan.nama_perusahaan')
                                                            ->join('perusahaan', 'perusahaan.id', '=', 'tender_peserta.perusahaan_id')
                                                            ->where([
                                                                'tender_peserta.tender_id' => $needApprovalTenders[$at]->id_tender,
                                                                'tender_peserta.status' => 'pemenang'
                                                            ])
                                                            ->get();

                    array_push($approvalData, $needApprovalTenders[$at]);
                }
            }
        }

        return response()->json([
            'status' => true,
            'data' => $approvalData
        ], 200);
    }

    public function approveBaByManager(Request $request, $tenderId)
    {
        $tender = Tender::find($tenderId);

        $tender->status_approval = $request->status_approval;
        
        if($tender->status_approval === 'revisi_pemenang' || $tender->status_approval === 'revisi_tahap_2'){
            $tender->catatan = $request->catatan;
        }

        if($tender->status_approval === 'approved_pemenang'){
            $tender->status_tender = 'tutup';
        }

        $tender->save();

        return response()->json([
            'status' => true,
            'message' => "Status tender has been updated"
        ]);
    }   
}
