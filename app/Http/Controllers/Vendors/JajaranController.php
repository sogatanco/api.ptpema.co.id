<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor\Jajaran;
use App\Models\Vendor\ViewPerusahaan;
use App\Http\Resources\PostResource;
use PhpParser\Node\Expr\Cast\Object_;

class JajaranController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor');
    }

    public function store(Request $request)
    {
        $jjr = new Jajaran();
        $jjr->perusahaan_id = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id;
        $jjr->nama = $request->nama;
        $jjr->jabatan = $request->jabatan;
        $jjr->no_npwp_direksi = $request->no_npwp_direksi;
        if ($jjr->save()) {
            return new PostResource(true, 'Jajaran Direksi Inserted !', []);
        } else {
            return new PostResource(true, 'Jajaran Direksi Failed to insert!', []);
        }
    }

    public function edit($id, Request $request){
        $jjr=Jajaran::find($id);
        $jjr->nama = $request->nama;
        $jjr->jabatan = $request->jabatan;
        $jjr->no_npwp_direksi = $request->no_npwp_direksi;
        if ($jjr->save()) {
            return new PostResource(true, 'Jajaran Direksi Updated Succesfully', []);
        } else {
            return new PostResource(true, 'Failed to update!', []);
        }
    }

    public function myDirek(){
        $jjr=Jajaran::where('perusahaan_id',ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id)->get();
        $data['list']=$jjr;
        $data['struktur_base64']=null;
        $data['struktur_filename']=null;
        if (file_exists(public_path('vendor_file/' . ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->struktur_organisasi))){
            $data['struktur_base64']=base64_encode(file_get_contents(public_path('vendor_file/' . ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->struktur_organisasi)));
            $data['struktur_filename']='struktur.pdf';
        }  
        return new PostResource(true, 'My Directors', $data);
    }   

    public function deleteDir($id){
        if(Jajaran::find($id)->delete()){
            return new PostResource(true, 'Deleted Succesfully', []);
        }else{
            return new PostResource(false, 'Failed to delete', []);
        }
    }
}
