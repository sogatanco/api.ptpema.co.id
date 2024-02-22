<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mitra\Mitra;
use App\Http\Resources\PostResource;

class MitraController extends Controller
{
    public function index(){
       $data= Mitra::select('name as value', 'name as label')
                    ->where('no_hp','!=', '')->orderBy('name', 'ASC')->get();
       foreach ($data as $d){
        $d->name=strtoupper($d->name);
       }
        return new PostResource(true, 'Data Mitra', $data);
    }
}
