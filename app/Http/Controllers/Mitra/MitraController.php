<?php

namespace App\Http\Controllers\Mitra;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mitra\Mitra;
use App\Http\Resources\PostResource;

class MitraController extends Controller
{
    public function index(){
       $data= Mitra::get();
        return new PostResource(true, 'Data Mitra', $data);
    }
}