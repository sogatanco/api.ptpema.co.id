<?php

namespace App\Http\Controllers\Pengajuan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\File;

class PreviewFilePengajuanController extends Controller
{
    public function showLampiran($fileName)
    {
        $filePath = public_path('pengajuan/' . $fileName);
    
        if (!file_exists($filePath)) {
            return response([
                'status' => false,
                'message' => 'File Tidak Ditemukan',
            ], 404);
        }
    
        $mimeType = File::mimeType($filePath);
    
        return response()->file($filePath, [
            'Content-Type' => $mimeType,
        ]);
    }
    
}
