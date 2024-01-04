<?php

namespace App\Http\Controllers\Da;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\DailyAct\DaCatAct;

class DaCatController extends Controller
{
    function index(){
        $list = DaCatAct::get();
        $total = $list->count();

        return response()->json([
            "status" => true,
            "total" => $total,
            "data"=>$list
        ], 200);
    }
}
