<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Vendor\Province;

class ProvinceController extends Controller
{
    public function list()
    {
        $list = Province::orderBy('name')
                ->get();

        return response()->json([
            'status' => 200,
            'data' => $list
        ], 200);
    }
}
