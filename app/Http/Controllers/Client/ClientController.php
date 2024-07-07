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
            $client = Client::where('id', $query)->first();
            if($client && $client->client_secret == $token){
                return response()->json([
                    "status" => true,
                    "message" => $token,
                    "data" => $query
                ], 200);
            }else{
                throw new HttpResponseException(response([
                    "status" => false,
                    "message" => "Unauthenticated"
                ], 401));
            }
        }else{
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Bad Request"
            ], 400));
        }
    }
}
