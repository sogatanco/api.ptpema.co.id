<?php

namespace App\Http\Controllers\Pengajuan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pengajuan\Pengajuan;
use App\Models\Pengajuan\ApprovedUmumPengajuan;

class PengajuanSelesaiController extends Controller
{
    public function index()
    {
        $pengajuan = Pengajuan::withCount([
            'approvals',
            'approvals as approvals_approved_count' => function ($query) {
                $query->where('status', 1);
            }
        ])
        ->having('approvals_count', '>', 0) // Harus ada approval
        ->havingRaw('approvals_count = approvals_approved_count') // Semua approval harus status 1
        ->with([
            'approvals' => function ($query) {
                $query->where('status', 1)->with([
                    'employe' => function ($q) {
                        $q->select('employe_id', 'first_name', 'last_name');
                    }
                ]);
            },
            'sub_pengajuan.taxes'
        ])
        ->latest()
        ->get();
    
    
    
    

        return response()->json([
            'success' => true,
            'data' => $pengajuan
        ], 200);
    }
}
