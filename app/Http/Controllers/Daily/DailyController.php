<?php

namespace App\Http\Controllers\Daily;

use Illuminate\Http\Request;

class DailyController extends Controller
{
    public function store(Request $request){

        return response()->json([
            "status" => true,
            "message" => "From daily store controller"
        ], 200);

    }
}
