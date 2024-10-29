<?php

namespace App\Http\Controllers\Vendors;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor\Akta;
use App\Models\Vendor\ViewPerusahaan;
use App\Models\Vendor\Perusahaan;
use App\Http\Resources\PostResource;
use Illuminate\Support\Facades\Storage;


class FileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api_vendor');
    }

    function viewFile(){

        $p=Perusahaan::where('user_id', Auth::user()->id)->get()->first();
        $doc[0]['id']='company_profile';
        $doc[0]['file_name']=null;
        $doc[0]['base64']=null;
        if (file_exists(public_path('vendor_file/' . $p->company_profile))){
            $doc[0]['id']='company_profile';
            $doc[0]['file_name']='company_profile.pdf';
            // $doc[0]['base64']=base64_encode(file_get_contents(public_path('vendor_file/' . $p->company_profile)));
            $doc[0]['base64']="bakbudik";
        }
        
        $doc[1]['id']='ktp_pengurus';
        $doc[1]['file_name']=null;
        $doc[1]['base64']=null;
        if (file_exists(public_path('vendor_file/' . $p->ktp_pengurus))){
            $doc[1]['id']='ktp_pengurus';
            $doc[1]['file_name']='ktp_pengurus.pdf';
            // $doc[1]['base64']=base64_encode(file_get_contents(public_path('vendor_file/' . $p->ktp_pengurus)));
            $doc[1]['base64']="bakbudik";
        }
        
        $doc[2]['id']='sk_kemenkumham';
        $doc[2]['file_name']=null;
        $doc[2]['base64']=null;
        if (file_exists(public_path('vendor_file/' . $p->sk_kemenkumham))){
            $doc[2]['id']='sk_kemenkumham';
            $doc[2]['file_name']='sk_kemenkumham.pdf';
            // $doc[2]['base64']=base64_encode(file_get_contents(public_path('vendor_file/' . $p->sk_kemenkumham)));
            $doc[2]['base64']="bakbudik";
        }
        
        $doc[3]['id']='fakta_integritas';
        $doc[3]['file_name']=null;
        $doc[3]['base64']=null;
        if (file_exists(public_path('vendor_file/' . $p->fakta_integritas))){
            $doc[3]['id']='fakta_integritas';
            $doc[3]['file_name']='fakta_integritas.pdf';
            // $doc[3]['base64']=base64_encode(file_get_contents(public_path('vendor_file/' . $p->fakta_integritas)));
            $doc[3]['base64']="bakbudik";
        }
        
        $doc[4]['id']='spt';
        $doc[4]['file_name']=null;
        $doc[4]['base64']=null;
        if (file_exists(public_path('vendor_file/' . $p->spt))){
            $doc[4]['id']='spt';
            $doc[4]['file_name']='spt.pdf';
            // $doc[4]['base64']=base64_encode(file_get_contents(public_path('vendor_file/' . $p->spt)));
            $doc[4]['base64']="bakbudik";
        }
        
        $doc[5]['id']='pph';
        $doc[5]['file_name']=null;
        $doc[5]['base64']=null;
        if (file_exists(public_path('vendor_file/' . $p->pph))){
            $doc[5]['id']='pph';
            $doc[5]['file_name']='pph.pdf';
            // $doc[5]['base64']=base64_encode(file_get_contents(public_path('vendor_file/' . $p->pph)));
            $doc[5]['base64']="bakbudik";
        }
        
        $doc[6]['id']='lap_keuangan';
        $doc[6]['file_name']=null;
        $doc[6]['base64']=null;
        if (file_exists(public_path('vendor_file/' . $p->lap_keuangan))){
            $doc[6]['id']='lap_keuangan';
            $doc[6]['file_name']='lap_keuangan.pdf';
            // $doc[6]['base64']=base64_encode(file_get_contents(public_path('vendor_file/' . $p->lap_keuangan)));
            $doc[6]['base64']="bakbudik";
        }
        
        $doc[7]['id']='rek_koran';
        $doc[7]['file_name']=null;
        $doc[7]['base64']=null;
        if (file_exists(public_path('vendor_file/' . $p->rek_koran))){
            $doc[7]['id']='rek_koran';
            $doc[7]['file_name']='rek_koran.pdf';
            // $doc[7]['base64']=base64_encode(file_get_contents(public_path('vendor_file/' . $p->rek_koran)));
            $doc[7]['base64']="bakbudik";
        }
        return new PostResource(true,'Doc Company', $doc);
    }

    function uplaodFile(Request $request)
    {   
        $fileTitle = $request->whatfile;
        $file = $request->file('file');

        $userId = Auth::user()->id;
        $perusahaan = Perusahaan::where('user_id', $userId)->first();

        $filePath = $perusahaan->id .'/'.$fileTitle.'.pdf';

        if(Storage::disk('public_vendor')->put($filePath, file_get_contents($file))){

            $perusahaan->$fileTitle = $filePath;

            if($perusahaan->save()){
                return new PostResource(true, "Upload ".$fileTitle." Berhasil", []);
            }else{
                return new PostResource(false, "Upload ".$fileTitle." Gagal", []);
            }

        }else{
            return new PostResource(false, "Upload ".$fileTitle." Gagal", []);
        }

        // if($request->whatfile=='struktur'){
        //     $file = base64_decode($request->file, true);
        //     $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id .'/struktur.pdf';
        //     if(Storage::disk('public_vendor')->put($filename, $file)){
        //         $p=Perusahaan::find(ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id);
        //         $p->struktur_organisasi=$filename;
        //         if($p->save()){
        //             return new PostResource(true, "Upload ".$request->whatfile." Berhasil", []);
        //         }else{
        //             return new PostResource(false, "Upload ".$request->whatfile." Gagal", []);
        //         }
        //     }else{
        //         return new PostResource(false, "Upload ".$request->whatfile." Gagal", []);
        //     }
        // }
        
       
        // else if($request->whatfile=='logo'){
        //     $dataImage = explode(',', $request->file);
        //     $file=base64_decode($dataImage[1], true);
        //     $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id .'/logo.png';
        //     if(Storage::disk('public_vendor')->put($filename, $file)){
        //         $p=Perusahaan::find(ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id);
        //         $p->logo_perusahaan=$filename;
        //         if($p->save()){
        //             return new PostResource(true, "Upload ".$request->whatfile." Berhasil", []);
        //         }else{
        //             return new PostResource(false, "Upload ".$request->whatfile." Gagal", []);
        //         }
        //     }else{
        //         return new PostResource(false, "Upload ".$request->whatfile." Gagal", []);
        //     }
        // }
        // else if($request->whatfile=='npwp'){
        //     $file = base64_decode($request->file, true);
        //     $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id .'/npwp.pdf';
        //     if(Storage::disk('public_vendor')->put($filename, $file)){
        //         $p=Perusahaan::find(ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id);
        //         $p->file_npwp=$filename;
        //         if($p->save()){
        //             return new PostResource(true, "Upload ".$request->whatfile." Berhasil", []);
        //         }else{
        //             return new PostResource(false, "Upload ".$request->whatfile." Gagal", []);
        //         }
        //     }else{
        //         return new PostResource(false, "Upload ".$request->whatfile." Gagal", []);
        //     }
        // }

        // else if($request->whatfile=='pvd'){
        //     $file = base64_decode($request->file, true);
        //     $filename = ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id .'/pvd.pdf';
        //     if(Storage::disk('public_vendor')->put($filename, $file)){
        //         $p=Perusahaan::find(ViewPerusahaan::where('user_id', Auth::user()->id)->get()->first()->id);
        //         $p->file_pvd=$filename;
        //         if($p->save()){
        //             return new PostResource(true, "Upload ".$request->whatfile." Berhasil", []);
        //         }else{
        //             return new PostResource(false, "Upload ".$request->whatfile." Gagal", []);
        //         }
        //     }else{
        //         return new PostResource(false, "Upload ".$request->whatfile." Gagal", []);
        //     }
        // }
        // else{
        //     return new PostResource(false, "Please Define what file on request", []);
        // }

    }
}
