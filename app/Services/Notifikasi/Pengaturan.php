<?php

namespace App\Services\Notifikasi;

use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;

// Status WhatsApp: ditentukan gateway (GET /health), bukan .env. Super Admin hanya bisa menjeda pengiriman lewat halaman Setting.
class Pengaturan
{
    /** true bila Super Admin menjeda pengiriman; tanpa catatan berarti tidak dijeda */
    public static function dijeda(): bool
    {
        try {
            return DB::connection('notif')->table('pengaturan')->where('kunci', 'wa_aktif')->value('nilai') === '0';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** status gateway dari /health; 'aktif' = gateway hidup dan whatsappReady true */
    public static function gateway(): array
    {
        $h = app(WhatsAppService::class)->health();
        $h['aktif'] = $h['hidup'] && $h['terhubung'] === true;

        return $h;
    }

    public static function waAktif(): bool
    {
        return !self::dijeda() && self::gateway()['aktif'];
    }

    public static function setDijeda(bool $jeda, ?string $oleh): void
    {
        DB::connection('notif')->table('pengaturan')->updateOrInsert(
            ['kunci' => 'wa_aktif'],
            ['nilai' => $jeda ? '0' : '1', 'diubah_oleh' => $oleh, 'updated_at' => now()]
        );
    }
}
