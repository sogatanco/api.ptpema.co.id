<?php

namespace App\Services;

use App\Models\Structure;
use Illuminate\Support\Facades\DB;

/**
 * Catatan aktivitas surat di ptpemaco_esign.logs (yang tampil pada tombol Logs). Ditulis lewat koneksi adm dengan nama tabel lengkap supaya
 * ikut transaksi pemanggil: bila penyimpanan surat dibatalkan, catatannya ikut batal.
 */
class SuratLogService
{
    public static function catat(string $noDocument, ?string $employeId, string $aktivitas, $waktu = null): void
    {
        DB::connection('adm')->table('ptpemaco_esign.logs')->insert([
            'employe_id' => (string) $employeId,
            'id_current_position' => $employeId ? Structure::where('employe_id', $employeId)->value('position_name') : null,
            'id_document' => $noDocument,
            'activity' => mb_substr($aktivitas, 0, 255),
            'created_at' => $waktu ?? now(),
            'updated_at' => now(),
        ]);
    }
}
