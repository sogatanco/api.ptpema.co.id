<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Vendor\Perusahaan;
use Illuminate\Http\Exceptions\HttpResponseException;

class FileController extends Controller
{

    public function download(Request $request)
    {
        $fileType = $request->query('type');
        $fileName = $request->query('file');

        $userId = Auth::user()->id;
        $perusahaan = Perusahaan::where('user_id', $userId)->first();

        if($fileType != 'null'){
            $filePath = public_path('vendor_file/' . $perusahaan->id . '/' . $fileType . '/' . $fileName);
        }else{
            $filePath = public_path('vendor_file/' . $perusahaan->id . '/' . $fileName);
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

    public function filePreview(Request $request, $companyId)
    {
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
