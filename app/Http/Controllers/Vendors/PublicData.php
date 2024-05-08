<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Vendor\Tender;


class PublicData extends Controller
{
    public function dataTender(){
        $data=Tender::where('metode_pengadaan', 'seleksi_umum')
            ->orWhere('metode_pengadaan', 'tender_umum')
            ->get();
            
        return response()->json([
            "success" => true,
            "data" => $data
        ], 200);
    }

   
}
