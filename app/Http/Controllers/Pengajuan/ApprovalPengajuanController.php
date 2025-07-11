<?php

namespace App\Http\Controllers\Pengajuan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pengajuan\Pengajuan;
use App\Models\Pengajuan\ApprovedUmumPengajuan;
use App\Models\Employe;
use App\Http\Controllers\Notification\NotificationController;

class ApprovalPengajuanController extends Controller
{
    public function index()
    {
        $pengajuan = Pengajuan::whereHas('approvals', function ($query) {
            $query->whereNull('status') // belum diapprove
                  ->where('employe_id', Employe::employeId());
        })
        ->with(['approvals' => function ($q) {
            $q->orderBy('step'); // biar urut step
        }, 'sub_pengajuan.taxes'])
        ->get()
        ->filter(function ($pengajuan) {
            $currentApproval = $pengajuan->approvals->firstWhere('employe_id', Employe::employeId());
        
            if (!$currentApproval) return false;
        
            $currentStep = $currentApproval->step;
        
            // Jika step pertama → langsung tampil kalau belum approve
            if ($currentStep == 1) {
                return is_null($currentApproval->status);
            }
        
            // Step > 1 → cek step sebelumnya
            $previousStep = $pengajuan->approvals->firstWhere('step', $currentStep - 1);
        
            return $previousStep && $previousStep->status == 1 && is_null($currentApproval->status);
        });

        // Ubah agar array dimulai dari index 0 (default)
        $data = array_values($pengajuan->toArray());
        
        return response()->json([
            'success' => true,
            'message' => 'From index Controller',
            'data' => $data
        ], 200);
    }

    public function approve(Request $request)
    {

        $pengajuan = Pengajuan::find($request->id);
    
        if (!$pengajuan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }
    
        $approval = $pengajuan->approvals()
            ->where('employe_id', Employe::employeId())
            ->first();
    
        if (!$approval) {
            return response()->json([
                'success' => false,
                'message' => 'Data approval tidak ditemukan untuk karyawan ini.',
            ], 404);
        }
    
        $approval->status = 1;
        $approval->save();

        $recipients = ApprovedUmumPengajuan::where('pengajuan_id', $pengajuan->id)
                    ->where('step', $approval->step + 1)
                    ->whereNull('status')
                    ->get(['employe_id']);
    

        // Kirim Notifikasi ke user
        if(count($recipients) > 0){
            NotificationController::new('APPROVED_PENGAJUAN', $recipients, '');
        }
    
        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diperbarui.',
            'data' => $approval,
        ], 200);
    }

    public function reject(Request $request)
    {
        $pengajuan = Pengajuan::find($request->id);
    
        if (!$pengajuan) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan tidak ditemukan.',
            ], 404);
        }
    
        $approval = $pengajuan->approvals()
            ->where('employe_id', Employe::employeId())
            ->first();
    
        if (!$approval) {
            return response()->json([
                'success' => false,
                'message' => 'Data approval tidak ditemukan untuk karyawan ini.',
            ], 404);
        }
    
        $approval->status = 0;
        $approval->keterangan = $request->alasan;
        $approval->save();
    
        return response()->json([
            'success' => true,
            'message' => 'Status berhasil diperbarui.',
            'data' => $approval,
        ], 200);
    }
    
}
