<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {

        $userRequest = $request->bearerToken();

        return response()->json([
            "status" => true,
            "message" => $userRequest
        ], 200);
    }
}
