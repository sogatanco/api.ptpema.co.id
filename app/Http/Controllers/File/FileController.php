<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor\Perusahaan;

class FileController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth:api_vendor');
    }
    
    public function download(Request $request)
    {
        $fileType = $request->query('type');
        $fileName = $request->query('file');

        $userId = Auth::user()->id;
        $perusahaan = Perusahaan::where('user_id', $userId)->first();

        // file path
        $file = public_path('vendor_file/87/company_profile.pdf');
        
        if(fileType != ''){
            $filePath = public_path('vendor_file/' . $perusahaan->id . '/' . $fileType . '/' . $fileName);
        }else{
            $filePath = public_path('vendor_file/' . $perusahaan->id . '/' . $fileName);
        }

        $headers = array(
            'Content-Type: application/pdf',
        );

        return Response::download($file, $fileName, $headers);
    }
}
