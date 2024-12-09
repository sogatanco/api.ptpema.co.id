<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuratMasuk extends Controller
{
    public function insert(Request $request)
    {
        $now = new \DateTime();
        $uniqueCode = $now->format('YmdHis') . '-' . substr(microtime(), 2, 6); 

        $teks = str_replace(' ', '-', $request->perihal);
        $teks = preg_replace('/[^a-zA-Z-]/', '', $teks);
        $fileName = 'surat_masuk/' . date('Y') . '/' . date('m') . '/' . $teks.$uniqueCode . '.pdf';

        $suratMasuk=new SuratMasuk();
        
        // if (Storage::disk('public_adm')->put($fileName, $request->file('file'))) {
        //     $suratMasuk->file=$fileName;
        //     $suratMasuk->nomor= $request->nomorSurat;
        //     $suratMasuk->pengirim=$request->pengirim;
        //     $suratMasuk->perihal=$request->perihal;
        //     $suratMasuk->id_direksi=$request->dir;
        //     $suratMasuk->via= $request->via;
        //     $suratMasuk->tgl_surat=date('Y-m-d', strtotime($request->tglSurat));
        //     if ($suratMasuk->save()) {
        //         return new PostResource(true, 'Berhasil', []);
        //      }

        // }

        return new PostResource(true, 'sdgsdg',$request->all());
    }
}
