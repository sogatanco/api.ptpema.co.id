<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor\Izin;
use App\Models\Vendor\ViewPerusahaan;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;


class IzinController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor');
    }

    public function store(Request $request)
    {
        // $file = base64_decode($request->file, true);
        // $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id . '/' . 'izin/' . time() . '.pdf';
        // if (Storage::disk('public_vendor')->put($filename, $file)) {
        //     $akt = new Izin();
        //     $akt->perusahaan_id = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id;
        //     $akt->nomor = $request->nomor;
        //     $akt->nama_izin = $request->nama_izin;
        //     $akt->tgl_terbit = $request->tgl_terbit;
        //     $akt->tgl_berakhir = $request->tgl_berakhir;
        //     $akt->file_izin = $filename;
        //     $akt->keterangan = $request->keterangan;
        //     if ($akt->save()) {
        //         return new PostResource(true, 'New Izin Inserted', []);
        //     } else { 
        //         return new PostResource(false, 'Failed to add akta', []);
        //     }
        // } else {
        //     return new PostResource(false, 'Failed to upload akta', []);
        // }

        // $request->validate([
        //     'nomor' => 'required',
        //     'tgl_terbit' => 'required',
        //     'file' => 'required',
        // ]);
        
        $perusahaanId = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id;

        $filePath = 'public_vendor' . '/ ' . $perusahaanId . '/' . 'izin/' . time() . '.pdf';

        $file = $request->file('file'); 

        if($file->storeAs($filePath, $file->hashName())){ 
            $akt = new Izin();
            $akt->perusahaan_id = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id;
            $akt->nomor = $request->nomor;
            $akt->nama_izin = $request->nama_izin;
            $akt->tgl_terbit = $request->tgl_terbit;
            $akt->tgl_berakhir = $request->tgl_berakhir;
            $akt->file_izin = $file->hashName();
            $akt->keterangan = $request->keterangan;
            if ($akt->save()) {
                return new PostResource(true, 'New Izin Inserted', []);
            } else { 
                return new PostResource(false, 'Failed to add akta', []);
            };
        }else{
            throw new HttpResponseException(response([
                'status' => false,
                'message' => 'Failed to upload akta',
            ]));
        }
    }

    public function view()
    {
        $filename = Izin::where('perusahaan_id', ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->get();

        foreach ($filename as $f) {
            $f->file_base64 = base64_encode(file_get_contents(public_path('vendor_file/' . $f->file_izin)));
        }
        return new PostResource(true, 'Detail Akta ', $filename);
    }

    public function delete($id)
    {
        $izin = Izin::where('id_izin', $id)->first();
        if ($izin->perusahaan_id == ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id) {
            if (file_exists(public_path('vendor_file/' . $izin->file_izin))) {
                Storage::disk('public_vendor')->delete($izin->file_izin);
            }

            if ($izin->delete()) {
                return new PostResource(true, 'Deleted Succesfully', []);
            }
        } else {
            return new PostResource(false, 'Not Permitted', []);
        }
    }
}
