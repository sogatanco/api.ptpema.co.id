<?php

namespace App\Services\Notifikasi;

use Illuminate\Support\Facades\DB;

// Saklar yang diatur Super Admin dari halaman Setting. Bila belum pernah diubah (atau tabelnya belum ada), nilai config/.env dipakai.
class Pengaturan
{
    public static function waAktif(): bool
    {
        try {
            $nilai = DB::connection('notif')->table('pengaturan')->where('kunci', 'wa_aktif')->value('nilai');
        } catch (\Throwable $e) {
            $nilai = null;
        }

        return $nilai === null ? (bool) config('notifikasi.wa.aktif') : $nilai === '1';
    }

    public static function setWaAktif(bool $aktif, ?string $oleh): void
    {
        DB::connection('notif')->table('pengaturan')->updateOrInsert(
            ['kunci' => 'wa_aktif'],
            ['nilai' => $aktif ? '1' : '0', 'diubah_oleh' => $oleh, 'updated_at' => now()]
        );
    }
}
