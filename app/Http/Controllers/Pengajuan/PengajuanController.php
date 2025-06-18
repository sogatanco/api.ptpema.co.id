<?php

namespace App\Http\Controllers\Pengajuan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Pengajuan\Pengajuan;
use App\Models\Pengajuan\SubPengajuan;
use App\Models\Pengajuan\ApprovedUmumPengajuan;
use App\Models\Employe;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Notification\NotificationController;
use App\Models\Pengajuan\PengajuanTax;


class PengajuanController extends Controller
{
    public function index()
    {
        // $pengajuan = Pengajuan::join('approved_umum_pengajuan', 'approved_umum_pengajuan.pengajuan_id', '=', 'umum_pengajuan.id')->get();

        $pengajuan = Pengajuan::latest()->whereHas('approvals', function ($query) {
            $query->where('status', '!=', 1)->orWhereNull('status');
        })
        ->with([
            'approvals',
            'sub_pengajuan.taxes' // tambahkan relasi taxes di dalam sub_pengajuan
        ])
        ->get();


        return response()->json([
            'success' => true,
            'data' => $pengajuan
        ], 200);
    }

    public function show($id)
    {
        $ApprovedUmumPengajuan = ApprovedUmumPengajuan::where('pengajuan_id', $id)->get();

        foreach ($ApprovedUmumPengajuan as $key => $value) {
           $ApprovedUmumPengajuan[$key]->full_name = Employe::find($value->employe_id)->first_name . ' ' . Employe::find($value->employe_id)->last_name;
        }


        return response()->json([
            'success' => true,
            'message' => 'From Show Controller',
            'data' => $ApprovedUmumPengajuan
        ], 200);
    }

    public function store(Request $request)
    {
        // Validasi utama
        $validated = $request->validate([
            'jenis_permohonan' => 'required|string|max:255',
            'no_dokumen' => 'required|string|max:255',
            'lampiran' => 'required|file|mimes:pdf',
            'items' => 'required',
        ]);

        // Ambil dan decode items jika perlu
        $items = $validated['items'];
        if (is_string($items)) {
            $items = json_decode($items, true);
        }

        // Dump semua data untuk pengecekan
        // return response()->json([
        //     'validated' => $validated,
        //     'items' => $items,
        //     'request_all' => $request->all()
        // ], 200);

        // Validasi isi dari items (secara manual per item)
        foreach ($items as $index => $item) {
            if (
                empty($item['itemName']) ||
                !isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] < 1 ||
                empty($item['unit']) ||
                !isset($item['price']) || !is_numeric($item['price']) || $item['price'] < 0
            ) {
                return response()->json([
                    'success' => false,
                    'message' => "Validasi item ke-" . ($index + 1) . " gagal. Pastikan semua field benar."
                ], 422);
            }
        }

        // Pastikan folder pengajuan ada
        $pengajuanPath = public_path('pengajuan');
        if (!file_exists($pengajuanPath)) {
            mkdir($pengajuanPath, 0755, true);
        }

        // Proses upload file jika ada
        $fileName = null;
        if ($request->hasFile('lampiran')) {
            $file = $request->file('lampiran');
            $fileName = now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
            $file->move($pengajuanPath, $fileName);
        }

        // Simpan pengajuan
        $pengajuan = Pengajuan::create([
            'pengajuan' => $validated['jenis_permohonan'],
            'no_dokumen' => $validated['no_dokumen'],
            'lampiran' => $fileName,
            'created_by' => Employe::employeId()
        ]);

        if ($pengajuan) {
            foreach ($items as $item) {
                $subPengajuan = SubPengajuan::create([
                    'pengajuan_id' => $pengajuan->id,
                    'nama_item' => $item['itemName'],
                    'jumlah' => $item['quantity'],
                    'satuan' => $item['unit'],
                    'biaya_satuan' => $item['price'],
                    'total_biaya' => $item['price'] * $item['quantity'],
                    'keterangan' => $item['description'] ?? null,
                ]);

                // Debug pajak yang akan disimpan
                // \Log::info('TAXES', ['taxes' => $item['taxes'] ?? null]);

                // Simpan pajak jika ada
                if (isset($item['taxes']) && is_array($item['taxes']) && count($item['taxes']) > 0) {
                    foreach ($item['taxes'] as $tax) {
                        PengajuanTax::create([
                            'sub_pengajuan_id' => $subPengajuan->id,
                            'nama_pajak' => $tax['name'] ?? '',
                            'persentase' => isset($tax['percentage']) ? (float)$tax['percentage'] : 0,
                            'calculation' => (isset($tax['type']) && $tax['type'] == 'increment') ? 'increase' : 'decrease',
                        ]);
                    }
                }
            }

            $recipients = ApprovedUmumPengajuan::where('pengajuan_id', $pengajuan->id)
                            ->where('step', 1)
                            ->get(['employe_id']);

            // Kirim notifikasi ke user
            NotificationController::new('APPROVED_PENGAJUAN', $recipients, '');

            return response()->json([
                'success' => true,
                'message' => 'Data Berhasil Disimpan',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Data Gagal Disimpan'
        ], 500);
    }
    

    public function update(Request $request, $id)
{
    $pengajuan = Pengajuan::find($id);

    if (!$pengajuan) {
        return response()->json([
            'success' => false,
            'message' => 'Pengajuan tidak ditemukan',
        ], 404);
    }

    // Validasi data utama
    $validated = $request->validate([
        'jenis_permohonan' => 'required|string|max:255',
        'no_dokumen' => 'required|string|max:255',
        'lampiran' => 'nullable|file|mimes:pdf',
        'items' => 'required',
    ]);

    // Ambil dan decode items jika perlu
    $items = $validated['items'];
    if (is_string($items)) {
        $items = json_decode($items, true);
    }

    // Dump data untuk pengecekan
    // return response()->json([
    //     'validated' => $validated,
    //     'items' => $items,
    //     'request_all' => $request->all()
    // ], 200);

    $fileName = $pengajuan->lampiran; // default: tetap pakai file lama

    // Handle file baru jika di-upload
    if ($request->hasFile('lampiran') && $request->file('lampiran') != 'undefined') {
        // Hapus file lama jika ada
        if ($pengajuan->lampiran && file_exists(public_path('pengajuan/' . $pengajuan->lampiran))) {
            unlink(public_path('pengajuan/' . $pengajuan->lampiran));
        }

        // Simpan file baru
        $file = $request->file('lampiran');
        $fileName = now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();
        $file->move(public_path('pengajuan'), $fileName);
    }

    // Update data utama
    $updated = $pengajuan->update([
        'pengajuan' => $validated['jenis_permohonan'],
        'no_dokumen' => $validated['no_dokumen'],
        'lampiran' => $fileName,
        'updated_by' => Employe::employeId(),
        'updated_at' => now()
    ]);

    if ($updated) {
        // Hapus semua sub pengajuan dan pajaknya lama
        $oldSubs = SubPengajuan::where('pengajuan_id', $id)->get();
        foreach ($oldSubs as $oldSub) {
            // Hapus pajak untuk setiap sub pengajuan lama
            PengajuanTax::where('sub_pengajuan_id', $oldSub->id)->delete();
        }
        // Hapus semua sub pengajuan lama
        SubPengajuan::where('pengajuan_id', $id)->delete();

        // Simpan ulang sub pengajuan dan pajak
        foreach ($items as $item) {
            $subPengajuan = SubPengajuan::create([
                'pengajuan_id' => $pengajuan->id,
                'nama_item' => $item['itemName'],
                'jumlah' => $item['quantity'],
                'satuan' => $item['unit'],
                'biaya_satuan' => $item['price'],
                'total_biaya' => $item['price'] * $item['quantity'],
                'keterangan' => $item['description'] ?? null,
            ]);

            // Hapus pajak sebelumnya untuk sub pengajuan baru (antisipasi jika ada duplikasi)
            PengajuanTax::where('sub_pengajuan_id', $subPengajuan->id)->delete();

            // Simpan pajak jika ada
            if (isset($item['taxes']) && is_array($item['taxes']) && count($item['taxes']) > 0) {
                foreach ($item['taxes'] as $tax) {
                    PengajuanTax::create([
                        'sub_pengajuan_id' => $subPengajuan->id,
                        'nama_pajak' => $tax['name'] ?? '',
                        'persentase' => isset($tax['percentage']) ? (float)$tax['percentage'] : 0,
                        'calculation' => (isset($tax['type']) && $tax['type'] == 'increment') ? 'increase' : 'decrease',
                    ]);
                }
            }
        }

        $recipients = ApprovedUmumPengajuan::where('pengajuan_id', $pengajuan->id)
                        ->where('step', 1)
                        ->get(['employe_id']);

        // Kirim notifikasi ke user
        NotificationController::new('APPROVED_PENGAJUAN', $recipients, '');

        return response()->json([
            'success' => true,
            'message' => 'Data Berhasil Diupdate',
        ]);
    }

    return response()->json([
        'success' => false,
        'message' => 'Data Gagal Diupdate',
    ], 500);
}

    


}
