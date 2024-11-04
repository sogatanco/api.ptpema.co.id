<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PreviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function getFile(Request $request, $companyId){
        $fileType = $request->query('type');
        $fileName = $request->query('file');


        if($fileType != 'null'){
            $filePath = public_path('vendor_file/' . $companyId . '/' . $fileType . '/' . $fileName);
        }else{
            $filePath = public_path('vendor_file/' . $companyId . '/' . $fileName);
        }

        if (!file_exists($filePath)) {
            throw new HttpResponseException(response([
                'status' => false,
                'message' => "File Tidak Ditemukan",
            ], 404));
        }

        $headers = array(
            'Content-Type: application/pdf',
        );

        return Response::download($filePath, $fileName, $headers);
    }
}
