<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Http\Models\Vendor\MasterBidangUsaha;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            "message" => "from user vendor endpoint"
        ], 200);
    }

    public function registerCompany(Request $request) 
    {
        return response()->json([
            "data" => $request->all(),
        ],200);
    }

    public function listBidangUsaha()
    {
        return response()->json([
            "message" => "from list bidang usaha"
        ]);
    }
}
