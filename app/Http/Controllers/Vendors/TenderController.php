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
        $data = Tender::where('metode_pengadaan', 'umum')
            ->orWhere('metode_pengadaan', 'terbatas')
            ->get();

            foreach($data as $d){
               if(count(TenderPeserta::where('tender_id', $d->id_tender)->where('perusahaan_id',  ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->get())>0){
                $d->register=true;
               }else{
                $d->register=false;
               }
              
            //    $d->sss=TenderPeserta::where('tender_id', $d->id_tender)->where('perusahaan_id',  ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->get();
            }

        return response()->json([
            "success" => true,
            "data" => $data
        ], 200);
    }

    public function ikot(Request $request)
    {
        
        if( count(TenderPeserta::where('tender_id', $request->tender_id)->where('perusahaan_id', ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->get())>0){
          
                return response()->json([
                    "success" => false,
                    "message"=>"sudah terdaftar"
                ], 409);
        }else{
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

    public function showTender($slug){
        $d=Tender::where('slug', $slug)->first();
        return response()->json([
            "success" => true,
            "data"=>$d
        ], 200);
    }

    public function pesertaTender($slug)
    {   
        $company = Perusahaan::where('user_id', Auth::user()->id)->first();
        $data  = Tender::where(['tender.slug' => $slug, 'tender_peserta.perusahaan_id' => $company->id])
                    ->leftJoin('tender_peserta', 'tender_peserta.tender_id', '=', 'tender.id_tender')
                    ->first();
                
        return response()->json([
            "success" => true,
            "data"=>$data
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
                "message"=>$request->key. "file sudah di upload"
            ], 200);
        }
    }

    public function finalIkot($id){
        $tender=Tender::find($id);
        return response()->json([
            "success" => true,
            "data"=>json_decode($tender->centang_dok_wajib)
        ], 200);
    }

    public function submitDokumen($idPeserta)
    {
        $tender = TenderPeserta::find($idPeserta);

        if(!$tender){
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Data not found."
            ], 500));
        };

        $tender->status = 'submit_dokumen';

        if($tender->save()){
            return response()->json([
                "status" => true,
                "message" => "Tender documents have been submitted."
            ], 200);
        }else{
            throw new HttpResponseException(response([
                "status" => false,
                "message" => "Something went wrong."
            ], 500));
        };
    }
}
