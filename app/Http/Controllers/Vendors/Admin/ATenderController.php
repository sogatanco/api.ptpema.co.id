<?php

namespace App\Http\Controllers\Vendors\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor\Tender;
use App\Models\Vendor\TenderPeserta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Resources\PostResource;
use App\Models\Employe;
use App\Models\Vendor\ViewPerusahaan;
use App\Models\Vendor\MasterKbli;
use Illuminate\Support\Str;


class ATenderController extends Controller
{
    function store(Request $request)
    {
        $file_dok_tender = base64_decode($request->dok_tender, true);
        $file_dok_deskripsi_tender = base64_decode($request->dok_deskripsi_tender, true);
        $file_doc_penyampaian_penawaran = base64_decode($request->doc_penyampaian_penawaran, true);

        $dok_tender = 'dok_tender.pdf';
        $dok_deskripsi_tender = 'dok_deskripsi_tender.pdf';
        $doc_penyampaian_penawaran = 'doc_penyampaian_penawaran.docx';


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
        $t->centang_dok_wajib = json_encode($request->centang_dok_wajib);
        $t->dok_tender = $dok_tender;
        $t->dok_deskripsi_tender = $dok_deskripsi_tender;
        $t->doc_penyampaian_penawaran = $doc_penyampaian_penawaran;

        if ($t->save()) {
            Storage::disk('public_vendor')->put('tender/' . $t->id_tender . '/' . $dok_tender, $file_dok_tender);
            Storage::disk('public_vendor')->put('tender/' . $t->id_tender . '/' . $dok_deskripsi_tender, $file_dok_deskripsi_tender);
            Storage::disk('public_vendor')->put('tender/' . $t->id_tender . '/' . $doc_penyampaian_penawaran, $file_doc_penyampaian_penawaran);
            return new PostResource(true, 'Tender Inserted !', $t);
        } else {
            return new PostResource(false, 'Failed Tender Insert !', []);
        }
    }

    function index()
    {
        $data = Tender::get();
        return new PostResource(true, 'List Tender', $data);
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

        if (file_exists(public_path('vendor_file/tender' . $td->dok_tender))) {
            $td['dok_tender_base64'] = base64_encode(file_get_contents(public_path('vendor_file/' . $td->dok_tender)));
        }

        if (file_exists(public_path('vendor_file/tender' . $td->dok_deskripsi_tender))) {
            $td['dok_desk_tender_base64'] = base64_encode(file_get_contents(public_path('vendor_file/' . $td->dok_deskripsi_tender)));
        }
        if (file_exists(public_path('vendor_file/tender' . $td->doc_penyampaian_penawaran))) {
            $td['doc_penyampaian_penawaran_base64'] = base64_encode(file_get_contents(public_path('vendor_file/' . $td->doc_penyampaian_penawaran)));
        }

        if (count(TenderPeserta::where('tender_id', $id)->get()) > 0) {
            $td->perusahaan_yang_ikut = TenderPeserta::where('tender_id', $id)->get();
            foreach ($td->perusahaan_yang_ikut as $p) {
                $p->detail = ViewPerusahaan::find($p->perusahaan_id);
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
        $t->kbli = $request->kbli;
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
        if ($t->save()) {
            return response()->json([
                "success" => true,
                "message" => $request->key . "file sudah di upload"
            ], 200);
        }
    }
}
