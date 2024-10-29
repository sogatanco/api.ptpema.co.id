<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FileController extends Controller
{
    public function download()
    {
        // file path
        $file = public_path('vendor_file/87/company_profile.pdf');

        $headers = array(
            'Content-Type: application/pdf',
        );

        return Response::download($file, 'company_profile.pdf', $headers);
    }
}
