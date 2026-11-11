<?php

namespace App\Services;

use App\Models\KeamananAkun;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Pengingat keamanan akun (ganti password sys dan email). Seminggu sekali per pengguna; bila password tidak diubah dalam 30 hari
 * terakhir (atau belum pernah tercatat) tampil sehari sekali. Status disimpan di server supaya berlaku lintas perangkat.
 */
class PengingatKeamanan
{
    public const BATAS_HARI_PASSWORD = 30;
    public const JEDA_MINGGUAN = 7;

    /** catat perubahan password; tidak boleh menggagalkan penggantian password bila tabelnya belum ada */
    public static function catatPasswordDiubah(?string $employeId): void
    {
        if (!$employeId) {
            return;
        }
        try {
            KeamananAkun::updateOrCreate(['employe_id' => $employeId], ['password_diubah_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('Catatan perubahan password gagal', ['error' => $e->getMessage()]);
        }
    }

    /** harian bila password belum pernah tercatat diubah atau diubah lebih dari 30 hari lalu */
    public static function mode(?Carbon $passwordDiubah, Carbon $sekarang): string
    {
        return !$passwordDiubah || $passwordDiubah->lt($sekarang->copy()->subDays(self::BATAS_HARI_PASSWORD)) ? 'harian' : 'mingguan';
    }

    public static function waktuTampil(string $mode, ?Carbon $terakhir, Carbon $sekarang): bool
    {
        if (!$terakhir) {
            return true;
        }
        $selisihHari = $terakhir->copy()->startOfDay()->diffInDays($sekarang->copy()->startOfDay());

        return $mode === 'harian' ? $selisihHari >= 1 : $selisihHari >= self::JEDA_MINGGUAN;
    }

    /** dipanggil frontend saat sys dibuka: bila waktunya tampil, langsung ditandai sudah ditampilkan (satu kali walau banyak tab) */
    public static function periksa(?string $employeId): array
    {
        $tidak = ['tampil' => false, 'mode' => null, 'hari_sejak_ubah' => null];
        if (!$employeId) {
            return $tidak;
        }
        try {
            $sekarang = now();
            $baris = KeamananAkun::firstOrCreate(['employe_id' => $employeId]);
            $mode = self::mode($baris->password_diubah_at, $sekarang);
            $info = ['mode' => $mode, 'hari_sejak_ubah' => $baris->password_diubah_at ? (int) $baris->password_diubah_at->diffInDays($sekarang) : null];
            if (!self::waktuTampil($mode, $baris->pengingat_tampil_at, $sekarang)) {
                return ['tampil' => false] + $info;
            }
            // tandai hanya bila catatan belum diubah tab lain (pembanding nilai lama, aman untuk NULL)
            $lama = $baris->getRawOriginal('pengingat_tampil_at');
            $terpasang = KeamananAkun::where('employe_id', $employeId)
                ->where(fn ($q) => $lama === null ? $q->whereNull('pengingat_tampil_at') : $q->where('pengingat_tampil_at', $lama))
                ->update(['pengingat_tampil_at' => $sekarang, 'updated_at' => $sekarang]);

            return ['tampil' => $terpasang > 0] + $info;
        } catch (\Throwable $e) {
            // tabel belum dibuat atau galat lain: pengingat tidak tampil, aplikasi tetap jalan
            Log::warning('Pengingat keamanan gagal', ['error' => $e->getMessage()]);

            return $tidak;
        }
    }
}
