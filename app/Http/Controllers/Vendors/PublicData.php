<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor\Tender;

class PublicData extends Controller
{
    public function dataTender(){
        $data=Tender::where('metode_pengadaan', 'umum')->latest();
        return response()->json([
            "success" => true,
            "data" => $data
        ], 200);
    }
}
