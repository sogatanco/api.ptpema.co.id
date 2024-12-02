<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\ListSurat;
use App\Models\Adm\PenomoranSurat;
use App\Models\Adm\Surat;
use App\Models\Employe;
use App\Models\Structure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SuratController extends Controller
{
    function insert(Request $request){


        $surat=new Surat();


        if($request->lampiran+0>0){
            $file = base64_decode(str_replace('data:application/pdf;base64,', '', $request->fileLampiran), true);
            $fileName = 'lampiran/' . date('Y') . '/' .sprintf("%02d", ((PenomoranSurat::where('type',$request->type)->first()->last_number)+1)). '.pdf';
            if (Storage::disk('public_adm')->put($fileName, $file)) {
                $surat->file_lampiran=$fileName;
            }
        }
        
        $surat->nomor_surat=sprintf("%02d", ((PenomoranSurat::where('type',$request->type)->first()->last_number)+1)) .'/'.PenomoranSurat::where('type',$request->type)->first()->kode.'/'. $this->getRomawi(date('m')).'/'.date('Y');
        $surat->no_document=unique_random('documents', 'doc_id', 40);
        $surat->kepada=$request->kepada;
        $surat->perihal=$request->perihal;
        $surat->j_lampiran=$request->lampiran;
        $surat->jenis_lampiran=$request->jenislampiran;    
        $surat->isi_surat=$request->isiSurat;
        $surat->tembusans=implode(",",$request->tembusans);
        $surat->id_divisi=$request->divisi;
        $surat->submitted_by=Employe::employeId();
        $surat->submitted_current_position=(Structure::where('employe_id', $surat->submitted_by)->first('position_name')->position_name);
        $surat->sign_by=$request->ttdBy;

        if($surat->save()){
            return new PostResource(true, 'Data Inserted', []);
        }   
     
    }

    public function getSurat($what){   
        if($what=== 'approved'){
            $data=[];
        }else{
            $data=ListSurat::where('created_by', Employe::employeId())->latest()->get();
        }

        return new PostResource(true, 'data surat', $data);
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
