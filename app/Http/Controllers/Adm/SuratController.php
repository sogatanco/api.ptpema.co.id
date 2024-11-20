<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\PenomoranSurat;
use App\Models\Adm\Surat;
use App\Models\Employe;
use Illuminate\Http\Request;

class SuratController extends Controller
{
    function insert(Request $request){

        $surat=new Surat();
        $surat->nomor_surat=sprintf("%02d", ((PenomoranSurat::where('type',$request->type)->first()->last_number)+1)) .'/'.PenomoranSurat::where('type',$request->type)->first()->kode.'/'. $this->getRomawi(date('m')).'/'.date('Y');
        $surat->no_document=unique_random('documents', 'doc_id', 40);
        $surat->kepada=$request->kepada;
        $surat->perihal=$request->perihal;
        $surat->j_lampiran=$request->lampiran;
        $surat->jenis_lampiran=$request->jenislampiran;
        // $surat->file_lampiran='sdgsdg';
        $surat->isi_surat=$request->isiSurat;
        $surat->tembusans=$request->tembusans;
        $surat->id_divisi=$request->divisi;
        $surat->submitted_by=Employe::employeId();
        $surat->sign_by=$request->ttdBy;

        
      return new PostResource(true, 'Insert Surat', $surat);
    }

    function getRomawi($bln)
    {
        switch ($bln) {
            case 1:
                return "I";
                break;
            case 2:
                return "II";
                break;
            case 3:
                return "III";
                break;
            case 4:
                return "IV";
                break;
            case 5:
                return "V";
                break;
            case 6:
                return "VI";
                break;
            case 7:
                return "VII";
                break;
            case 8:
                return "VIII";
                break;
            case 9:
                return "IX";
                break;
            case 10:
                return "X";
                break;
            case 11:
                return "XI";
                break;
            case 12:
                return "XII";
                break;
        }
    }
}
