<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            "status" => true,
            "message" => "Hello World",
        ], 200);
    }

    public function employees()
    {
        $list = Employe::get();
        $total = $list->count();
        return response()->json([
            "status" => true,
            "total" => $total,
            "data" => $list
        ], 200);
    }

    public function store(Request $request)
    {

        // create employe as user
        $newUser = new User();
        $newUser->email = $request->email;
        $newUser->save();

        // $newEmploye = Employe::create($request->all());

        // if(!$newEmploye) {
        //     return response()->json([
        //         "status" => false,
        //         "message" => "Failed to create new employe."
        //     ]);
        // }


        return response()->json([
            "status" => true,
            "data" => "success",
        ], 200);
    }
}
