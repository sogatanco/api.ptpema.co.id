<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index()
    {
        return response()->json([
            "status" => true,
            "message" => "from client controller"
        ], 200);
    }
}
