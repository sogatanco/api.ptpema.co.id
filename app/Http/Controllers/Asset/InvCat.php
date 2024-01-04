<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\Inven\assetCat;


class InvCat extends Controller
{
    function index(){
        $data=assetCat::get();
        return response()->json([
            "status" => true,
            "data" => $data,
        ], 200);
    }
}
