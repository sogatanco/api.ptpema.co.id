<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->query('id');
        $token = $request->bearerToken();

        if($query){
            $isValid = true;
            if($isValid){
                return response()->json([
                    "status" => true,
                    "message" => $token,
                    "data" => $query
                ], 200);
            }else{
                throw new HttpResponseException(response([
                    "status" => false,
                    "message" => "Unauthenticated"
                ]));
            }
        }else{
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Bad Request"
            ], 400));
        }
    }
}
