<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use App\Models\Sppd\Sppd;
use App\Models\Sppd\TujuanSppd;


class PengajuanController extends Controller
{

    public function store(Request $request){
        return new PostResource(true, 'SPPD Inserted Succesfully',$request);
    }   
}
