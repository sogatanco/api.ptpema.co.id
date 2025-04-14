<?php

namespace App\Http\Controllers\MobilOp;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Mobil\Mobil;
use Illuminate\Http\Request;

class MobilController extends Controller
{
    public function insert(Request $request) {
        $mobil=new Mobil();

        $mobil->brand=$request->brand;
        $mobil->plat=$request->plat;
        $mobil->status=$request->status;

        if($mobil->save()) {
            return new PostResource(true, 'success', []);
        }
    }

    public function getMobil() {
        $data = Mobil::where('deleted',0)->get();
        return new PostResource(true, 'success', $data);
    }
}
