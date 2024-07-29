<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sppd\Pihak;
use App\Models\Sppd\CategoriSppd;
use App\Models\Sppd\JenisSppd;
use App\Http\Resources\PostResource;

class StaticDataController extends Controller
{
    public function getPihak(){
        $data=Pihak::all();
        return new PostResource(true, 'data pihak', $data);
    }

    public function getCategori(){
        $data=CategoriSppd::all();
        return new PostResource(true, 'data pihak', $data);
    }

    public function getJenis(){
        $data=JenisSppd::all();
        return new PostResource(true, 'data pihak', $data);
    }
}
