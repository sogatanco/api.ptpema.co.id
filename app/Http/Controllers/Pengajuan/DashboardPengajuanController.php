<?php

namespace App\Http\Controllers\Pengajuan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pengajuan\Pengajuan;
use App\Models\Employe;

class DashboardPengajuanController extends Controller
{
    public function index()
    {
        // Login Role AdminPengajuan
        if(in_array('AdminPengajuan', auth()->user()->roles)) {
            $pengajuan = Pengajuan::all();
            $totalPengajuan = $pengajuan->count();
    
            $totalPengajuanSelesai = Pengajuan::withCount([
                'approvals',
                'approvals as approvals_approved_count' => function ($query) {
                    $query->where('status', 1);
                }
            ])
            ->having('approvals_count', '>', 0) // Harus ada approval
            ->havingRaw('approvals_count = approvals_approved_count') // Semua approval harus status 1
            ->count();

            $totalPengajuanBelumSelesai = $totalPengajuan - $totalPengajuanSelesai;

            $totalPengajuanDitolak = Pengajuan::whereHas('approvals', function ($query) {
                $query->where('status', 0);
            })
            ->count();
        }

        // Login Role ManagerUmum | DirekturUmumKeuangan | Presdir
        if(in_array('ManagerUmum', auth()->user()->roles) || in_array('DirekturUmumKeuangan', auth()->user()->roles) || in_array('Presdir', auth()->user()->roles)) {
            $pengajuan = Pengajuan::all();
            $totalPengajuan = $pengajuan->count();

            $totalPengajuanSelesai = Pengajuan::withCount([
                'approvals',
                'approvals as approvals_approved_count' => function ($query) {
                    $query->where('status', 1);
                }
            ])
            ->having('approvals_count', '>', 0) // Harus ada approval
            ->havingRaw('approvals_count = approvals_approved_count') // Semua approval harus status 1
            ->count();


            $totalPengajuanBelumSelesai = Pengajuan::whereHas('approvals', function ($query) {
                $query->whereNull('status')
                      ->where('employe_id', Employe::employeId());
            })
            ->with(['approvals' => function ($q) {
                $q->orderBy('step');
            }, 'sub_pengajuan'])
            ->get()
            ->filter(function ($pengajuan) {
                $currentApproval = $pengajuan->approvals->firstWhere('employe_id', Employe::employeId());
            
                if (!$currentApproval) return false;
            
                $currentStep = $currentApproval->step;
            
                if ($currentStep == 1) {
                    return is_null($currentApproval->status);
                }
            
                $previousStep = $pengajuan->approvals->firstWhere('step', $currentStep - 1);
            
                return $previousStep && $previousStep->status == 1 && is_null($currentApproval->status);
            })
            ->count();

            $totalPengajuanDitolak = Pengajuan::whereHas('approvals', function ($query) {
                $query->where('status', 0)
                      ->where('employe_id', Employe::employeId());
            })
            ->with(['approvals' => function ($q) {
                $q->orderBy('step');
            }, 'sub_pengajuan'])
            ->get()
            ->filter(function ($pengajuan) {
                $currentApproval = $pengajuan->approvals->firstWhere('employe_id', Employe::employeId());
            
                if (!$currentApproval) return false;
            
                $currentStep = $currentApproval->step;
            
                if ($currentStep == 1) {
                    return is_null($currentApproval->status);
                }
            
                $previousStep = $pengajuan->approvals->firstWhere('step', $currentStep - 1);
            
                return $previousStep && $previousStep->status == 1 && is_null($currentApproval->status);
            })
            ->count();
        }



    

        return response()->json([
            'success' => true,
            'data' => [
                'total_pengajuan' => $totalPengajuan ?? 0,
                'total_pengajuan_selesai' => $totalPengajuanSelesai ?? 0,
                'total_pengajuan_belum_selesai' => $totalPengajuanBelumSelesai ?? 0,
                'total_pengajuan_ditolak' => $totalPengajuanDitolak ?? 0,
            ]
        ], 200);
    }
}
