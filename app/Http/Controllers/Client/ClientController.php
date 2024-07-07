<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            "status" => true,
            "message" => "Hello World",
        ], 200);
    }
}
