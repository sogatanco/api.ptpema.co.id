<?php

namespace App\Http\Controllers\Verify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\PostResource;
use App\Models\Verify\ListVerif;
use App\Models\Adm\ListSurat;
use App\Models\Structure;

class ScanVerif extends Controller
{
    function getDetail($id_doc, Request $request )
    {
        $d = ListVerif::where('id_doc', $id_doc)->where('type', $request->type)->first();
        if ($d->jenis_doc==2) {
            $data=ListSurat::where('no_document',$d->id_doc)->first();
            $data['signer']=Structure::where('employe_id', $data->sign_by)->first();
            $data['tglSurat']=$data->created_at;
            $data['nomorSurat']=$data->nomor_surat;
            $data['lampiran']=$data->j_lampiran;
            $data['jenisLampiran']=$data->jenis_lampiran;
            $data['isiSurat']=$data->isi_surat;
            $data['ttdBy']=$data->sign_by;
            if($data->tembusans!==null && $data->tembusans!=='' ){
                $data['tembusans']=explode(',',$data->tembusans);
            }else{
                $data['tembusans']=[];
            }
            
            if($data->file_lampiran!==null && file_exists(public_path('adm/' . $data->file_lampiran))){
                $data['fileLampiran']=base64_encode(file_get_contents(public_path('adm/' . $data->file_lampiran)));   
            }else{
                $data['fileLampiran']='';
            }
        }
        return new PostResource(true, 'success', $d);
    }
}
