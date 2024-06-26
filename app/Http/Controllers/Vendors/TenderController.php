<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use App\Models\Vendor\Tender;
use App\Models\Vendor\TenderPeserta;
use App\Models\Vendor\ViewPerusahaan;
use App\Models\Vendor\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Exceptions\HttpResponseException;

class TenderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor');
    }

    public function listTender()
    {
        $tenders = Tender::orderBy('id_tender', 'DESC')->get();

        $data = [];
        for ($t=0; $t < count($tenders); $t++) { 
            if (count(TenderPeserta::where('tender_id', $tenders[$t]->id_tender)->where('perusahaan_id',  ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->get()) > 0) {
                $tenders[$t]['register'] = true;
                $statusPeserta = TenderPeserta::where('tender_id', $tenders[$t]->id_tender)->where('perusahaan_id',  ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->first()->status;
                if (count(TenderPeserta::where('tender_id', $tenders[$t]->id_tender)->where('status',  'pemenang')->get()) > 0) {
                    if ($statusPeserta == 'lulus_tahap_1' || $statusPeserta == 'submit_dokumen') {
                        $tenders[$t]['status_peserta'] = 'gagal, coba lagi';
                    } else {
                        $tenders[$t]['status_peserta'] = $statusPeserta;
                    }
                } else if(count(TenderPeserta::where('tender_id', $tenders[$t]->id_tender)->where('status',  'lulus_tahap_1')->get()) > 0){
                    if ($statusPeserta == 'submit_dokumen') {
                        $tenders[$t]['status_peserta'] = 'tidak lulus tahap 1';
                    } else {
                        $tenders[$t]['status_peserta'] = $statusPeserta;
                    }
                }
                array_push($data, $tenders[$t]);
            } else {
                if($tenders[$t]->metode_pengadaan == 'seleksi_umum' || $tenders[$t]->metode_pengadaan == 'tender_umum'){
                    $tenders[$t]['register'] = false;
                    $tenders[$t]['status_peserta'] = '';

                    array_push($data, $tenders[$t]);
                }
            }
        }

        return response()->json([
            "success" => true,
            "data" => $data
        ], 200);
    }

    public function ikot(Request $request)
    {

        if (count(TenderPeserta::where('tender_id', $request->tender_id)->where('perusahaan_id', ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->get()) > 0) {

            return response()->json([
                "success" => false,
                "message" => "sudah terdaftar"
            ], 409);
        } else {
            $t = new TenderPeserta();
            $t->perusahaan_id = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id;
            $t->tender_id = $request->tender_id;
            if ($t->save()) {
                return response()->json([
                    "success" => true,
                ], 200);
            }
        }
    }

    public function showTender($slug)
    {
        $d = Tender::where('slug', $slug)->first();
        return response()->json([
            "success" => true,
            "data" => $d
        ], 200);
    }

    public function pesertaTender($slug)
    {
        // cek dulu dia udah terverifikasi belum
        // kalau belum gak usah kirim form
        $company = Perusahaan::where('user_id', Auth::user()->id)->first();

        $tender  = Tender::where(['tender.slug' => $slug, 'tender_peserta.perusahaan_id' => $company->id])
            ->leftJoin('tender_peserta', 'tender_peserta.tender_id', '=', 'tender.id_tender')
            ->first();

        if($tender->owner === 'umum'){
            $tender->status_verifikasi_admin = $company->status_verifikasi_umum;
        }else{
            $tender->status_verifikasi_admin = $company->status_verifikasi_scm;
        }
        
        return response()->json([
            "success" => true,
            "data" => $tender,
        ], 200);
    }

    public function upload(Request $request)
    {
        $t = TenderPeserta::where('tender_id', $request->tender_id)->where('perusahaan_id', ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->first();

        Storage::disk('public_vendor')->put('tender/' . $request->tender_id . '/' . ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id . '/' . $request->key . '.pdf', base64_decode($request->file, true));
        $t[$request->key] = 'tender/' . $request->tender_id . '/' . ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id . '/' . $request->key . '.pdf';

        if ($t->save()) {
            return response()->json([
                "success" => true,
                "message" => $request->key . "file sudah di upload"
            ], 200);
        }
    }

    public function finalIkot($id)
    {
        $tender = Tender::find($id);
        $docs = json_decode($tender->centang_dok_wajib);

        return response()->json([
            "success" => true,
            "data" => $docs
        ], 200);
    }

    public function submitDokumen($idPeserta)
    {
        $tender = TenderPeserta::find($idPeserta);

        if (!$tender) {
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Participant data not found."
            ], 404));
        };

        $tender->status = 'submit_dokumen';

        if ($tender->save()) {
            return response()->json([
                "status" => true,
                "message" => "Tender documents have been submitted."
            ], 200);
        } else {
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Something went wrong."
            ], 500));
        };
    }
}
