<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\ListSuratMasuk;
use App\Models\Employe;
use Illuminate\Http\Request;
use App\Models\Adm\SuratMasuk as SM;
use Illuminate\Support\Facades\Storage;

class SuratMasuk extends Controller
{
    public function insert(Request $request)
    {
        $now = new \DateTime();
        $uniqueCode = $now->format('YmdHis') . '-' . substr(microtime(), 2, 6); 

        $teks = str_replace(' ', '-', $request->perihal);
        $teks = preg_replace('/[^a-zA-Z-]/', '', $teks);
        $fileName = 'surat_masuk/' . date('Y') . '/' . date('m') . '/' .$uniqueCode.'-'. $teks . '.pdf';

        $suratMasuk=new SM();
        
        if (Storage::disk('public_adm')->put($fileName,      file_get_contents($request->file('file')->getRealPath()))) {
            $suratMasuk->file=$fileName;
            $suratMasuk->nomor= $request->nomorSurat;
            $suratMasuk->pengirim=$request->pengirim;
            $suratMasuk->perihal=$request->perihal;
            $suratMasuk->id_direksi=$request->dir;
            $suratMasuk->via= $request->via;
            $suratMasuk->insert_by=Employe::employeId() ;
            $suratMasuk->tgl_surat=date('Y-m-d', strtotime($request->tglSurat));
            if ($suratMasuk->save()) {
                return new PostResource(true, 'Berhasil', []);
             }

        }

        // return new PostResource(true, 'sdgsdg',$request->all());
    }

    public function getSM($what){
        $data=[];
        if ($what=='to_me') {
            $data=ListSuratMasuk::where('live_receiver',Employe::employeId())->whereNull('tindak_lanjut')->latest('diterima')->get();
        }else if ($what== 'tinjut') {
            $data=ListSuratMasuk::where('live_receiver',Employe::employeId())->whereNotNull('tindak_lanjut')->latest('ditinjut')->get();
        }

        return new PostResource(true,'data Surat Masuk', $data);
    }
}
