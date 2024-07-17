<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employe;
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
        // $newEmploye = Employe::create($request->all());

        // if(!$newEmploye) {
        //     throw new HttpResponseException(response([
        //         "status" => false,
        //         "message" => "Failed to create new employe."
        //     ], 500));
        // }
        $employeId = $request->employe_id;

        return response()->json([
            "status" => true,
            // "message" => "New employe has been created.",
            "data" => $request->all(),
            "employe_id" => $employeId
        ], 200);
    }
}
