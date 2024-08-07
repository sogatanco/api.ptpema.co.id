<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use App\Models\Sppd\Sppd;
use App\Models\Sppd\TujuanSppd;
use App\Models\Sppd\PenomoranSppd;


class PengajuanController extends Controller
{

    public function store(Request $request){
        $sppd = new Sppd();
        $sppd->nomor_sppd =((PenomoranSppd::find($request->nomor)->last_number)+1).'/PEMA/ST-'.PenomoranSppd::find($request->nomor)->kode.'/'.date('Y');

        return new PostResource(true, unique_random('documents', 'doc_id', 40) ,$request->all());
    }   
}
