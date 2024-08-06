<?php

namespace App\Http\Controllers\Sppd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sppd\Pihak;
use App\Models\Sppd\CategoriSppd;
use App\Models\Sppd\JenisSppd;
use App\Models\Sppd\DasarSppd;
use App\Models\Sppd\PenomoranSppd;
use App\Models\Sppd\GolonganSppd;
use App\Http\Resources\PostResource;
use App\Models\Sppd\KlasifikasiBisnis;
use App\Models\Structure;

class StaticDataController extends Controller
{
    public function getPihak(){
        $data=Pihak::all();
        foreach ($data as $d) {
            $d->base_penomoran=PenomoranSppd::where('id_pihak', $d->id)->get();
            $d->base_golongan=GolonganSppd::where('id_pihak', $d->id)->get();
        }
        return new PostResource(true, 'data pihak', $data);
    }

    public function getCategori(){
        $data=CategoriSppd::all();
        return new PostResource(true, 'data categori', $data);
    }

    public function getJenis(){
        $data=JenisSppd::all();
        return new PostResource(true, 'data jenis', $data);
    }

    public function getDasar(){
        $data=DasarSppd::all();
        return new PostResource(true, 'data dasar', $data);
    }

    public function getDeatailEmployee($employeeID){
        $employee=Structure::where('employe_id',$employeeID)->first();
        return new PostResource(true, 'data employee', $employee);
    }

    public function getKlasifikasiBisnis(){
        $data=KlasifikasiBisnis::all();
        return new PostResource(true, 'data klasifikasi bisnis', $data);
    }
}
