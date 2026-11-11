<?php

namespace App\Http\Controllers\Keamanan;

use App\Http\Controllers\Controller;
use App\Models\Employe;
use App\Services\PengingatKeamanan;

class PengingatController extends Controller
{
    public function pengingat()
    {
        return response()->json(['status' => true] + PengingatKeamanan::periksa(Employe::employeId()), 200);
    }
}
