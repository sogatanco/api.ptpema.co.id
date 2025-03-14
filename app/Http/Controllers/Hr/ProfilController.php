<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Hr\Profil;
use Illuminate\Http\Request;

class ProfilController extends Controller
{
    public function getImage($employe_id){
        $p=Profil::where('employe_id', $employe_id)->get()->first();
        return new PostResource(true, 'data employee', $p);
    }
}
