<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Models\Vendor\Tender;
use Illuminate\Http\Request;

class TenderController extends Controller
{
    public function listTender()
    {
        $data = Tender::where('metode_pengadaan', 'umum')
                ->orWhere('metode_pengadaan', 'terbatas')
                ->get();

        return response()->json([
            "success" => true,
            "data" => $data
        ], 200);
    }
}
