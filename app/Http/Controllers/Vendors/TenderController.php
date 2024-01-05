<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Models\Vendor\Tender;
use App\Models\Vendor\TenderPeserta;
use App\Models\Vendor\ViewPerusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TenderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor');
    }

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

    public function ikot(Request $request)
    {
        $t = new TenderPeserta();
        $t->perusahaan_id = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id;
        $t->tender_id = $request->tender_id;
        if ($t->save()) {
            return response()->json([
                "success" => true,
            ], 200);
        }
    }

    public function upload(Request $request)
    {
        $t = TenderPeserta::where('tender_id', $request->tender_id)->where('perusahaan_id', ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->first();

        Storage::disk('public_vendor')->put('tender/' . $request->tender_id . '/' . ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id . '/' . $request->key . '.pdf', base64_decode($request->file, true));
        $t[$request->key] = 'tender/' . $request->tender_id . '/' . ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id . '/' . $request->key . '.pdf';

        if ($t->save()) {
            return response()->json([
                "success" => true,
                "message"=>$request->key. "file suah di upload"
            ], 200);
        }
    }
}
