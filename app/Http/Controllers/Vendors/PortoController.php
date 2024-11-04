<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor\Porto;
use App\Models\Vendor\ViewPerusahaan;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;


class PortoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor');
    }

    public function store(Request $request)
    {
        $file = base64_decode($request->file, true);
        $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id . '/' . 'spk_porto/' . time() . '.pdf';
        if (Storage::disk('public_vendor')->put($filename, $file)) {
            $akt = new Porto();
            $akt->perusahaan_id  = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id;
            $akt->nama_project = $request->nama_project;
            $akt->tahun_mulai = $request->tahun_mulai;
            $akt->tahun_selesai = $request->tahun_selesai;
            $akt->durasi = $request->durasi;
            $akt->nilai_po = $request->nilai_po;
            $akt->owner = $request->owner;
            $akt->spk = $filename;
            if ($akt->save()) {
                return new PostResource(true, 'New Porto Inserted', []);
            } else {
                return new PostResource(false, 'Failed to add akta', []);
            }
        } else {
            return new PostResource(false, 'Failed to upload akta', []);
        }
    }

    public function view()
    {
        $filename = Porto::where('perusahaan_id', ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->get();

        return new PostResource(true, 'Detail Portofolio ', $filename);
    }

    public function delete($id)
    {
        $porto = Porto::where('id_porto', $id)->first();
        if ($porto->perusahaan_id == ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id) {
            if (file_exists(public_path('vendor_file/' . $porto->spk))) {
                Storage::disk('public_vendor')->delete($porto->spk);
            }

            if ($porto->delete()) {
                return new PostResource(true, 'Deleted Succesfully', []);
            }
        } else {
            return new PostResource(false, 'Not Permitted', []);
        }
    }
}
