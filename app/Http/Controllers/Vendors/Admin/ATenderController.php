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
use Illuminate\Support\Str;


class ATenderController extends Controller
{
    function store(Request $request)
    {
        $file_dok_tender = base64_decode($request->dok_tender, true);
        $file_dok_deskripsi_tender = base64_decode($request->dok_deskripsi_tender, true);

        $dok_tender = 'dok_tender.pdf';
        $dok_deskripsi_tender = 'dok_deskripsi_tender.pdf';


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

        if ($t->save()) {
            Storage::disk('public_vendor')->put('tender/' . $t->id_tender . '/' . $dok_tender, $file_dok_tender);
            Storage::disk('public_vendor')->put('tender/' . $t->id_tender . '/' . $dok_deskripsi_tender, $file_dok_deskripsi_tender);
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
        foreach ($ikut as $p) {
            $list['value']=$p->perusahaan_id;
            $list['label']=ViewPerusahaan::find($p->perusahaan_id)->bentuk_usaha.' '.ViewPerusahaan::find($p->perusahaan_id)->nama_perusahaan;
        }

        return new PostResource(true, 'Tender', $list);
    }

    function show($id)
    {
        $td = Tender::where('id_tender', $id)->first();
        if (count(TenderPeserta::where('tender_id', $id)->get()) > 0) {
            $td->perusahaan_yang_ikut = TenderPeserta::where('tender_id', $id)->get();
            foreach ($td->perusahaan_yang_ikut as $p) {
                $p->detail = ViewPerusahaan::find($p->perusahaan_id);
            }
        }else{
            $td->perusahaan_yang_ikut=[];
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

    public function deleteTender($id){
        $t=Tender::find($id);
        if($t->delete()){
            return new PostResource(true, 'Tender  deleted !', []);
        }
    }

    public function setTahap2($id, Request $request){
        $t=Tender::find($id);
        $t->tahap_dua=$request->list_peserta;
        $t->status_tender='tutup';
        if($t->save()){
            return new PostResource(true, 'tahap 2 submit', []);
        }
    }

    public function setPemenang($id, Request $request){
        $t=Tender::find($id);
        $t->pemenang=$request->list_peserta;
        $t->status_tender='tutup';
        if($t->save()){
            return new PostResource(true, 'pemenang submit', []);
        }
    }

    public function getTahap2($id){
        $t2=Tender::find($id)->tahap_dua;
        $t2=json_decode($t2);
        $list=[];
        foreach($t2 as $t){
            $list['value']=$t;
            $List['label']=ViewPerusahaan::find(30);
        }
        return new PostResource(true, 'tahap dua', $list);
    }
}
