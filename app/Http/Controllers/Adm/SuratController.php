<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Adm\Surat;
use Illuminate\Http\Request;

class SuratController extends Controller
{
    function insert(Request $request){
        
      return new PostResource(true, 'Insert Surat', $request->all());
    }
}
