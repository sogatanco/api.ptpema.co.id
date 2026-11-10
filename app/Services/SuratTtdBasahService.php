<?php

namespace App\Services;

use App\Models\Adm\Surat;
use App\Models\Adm\SuratTtdBasah;
use Illuminate\Support\Facades\DB;

/**
 * Surat tanda tangan basah. Trigger surats tetap membuat dokumen dan langkah paraf (nama penandatangan di surat dibaca dari langkah "sign"),
 * jadi langkah itu dibiarkan. Surat ditandai di surat_ttd_basah, dikeluarkan dari daftar review, dan notifikasi "Mohon Review Dokumen"
 * (tipe 39) yang dibuat trigger dihapus dalam transaksi yang sama sehingga tidak pernah terkirim.
 */
class SuratTtdBasahService
{
    private const TIPE_REVIEW = 39;

    /** id notifikasi terbesar sebelum surat disimpan; dipakai untuk menghapus hanya notifikasi yang dibuat trigger pada transaksi ini */
    public static function batasNotifikasi(): int
    {
        return (int) DB::connection('adm')->table('ptpemaco_apam.notifications')->max('id');
    }

    public static function hapusNotifikasi(Surat $s, int $batas): void
    {
        DB::connection('adm')->table('ptpemaco_apam.notifications')
            ->where('id', '>', $batas)
            ->where('actor', $s->submitted_by)
            ->where('entity_type_id', self::TIPE_REVIEW)
            ->delete();
    }

    public static function tandai(Surat $s, int $batas): void
    {
        SuratTtdBasah::firstOrCreate(['surat_id' => $s->id]);
        self::hapusNotifikasi($s, $batas);
        SuratLogService::catat($s->no_document, $s->submitted_by, 'Membuat surat tanda tangan manual (tanpa paraf dan tanda tangan di sistem)');
    }
}
