<?php

namespace App\Services\Notifikasi;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Kabar lewat email ke tim IT saat pengiriman WhatsApp notifikasi berhenti (dimatikan Super Admin atau gateway gagal)
class PeringatanWa
{
    public const TUJUAN = 'it@pema.co.id';

    /** $batas: menit minimal antar email dengan kunci yang sama (0 = selalu kirim) */
    public static function kirim(string $kunci, string $subjek, string $isi, int $batas = 0): void
    {
        try {
            if ($batas > 0 && !Cache::add('peringatan_wa:' . $kunci, 1, now()->addMinutes($batas))) {
                return;
            }
            Mail::raw($isi . "\n\nWaktu: " . now()->format('d/m/Y H:i:s') . " WIB\nPesan otomatis dari sys.pema.co.id.", function ($m) use ($subjek) {
                $m->to(self::TUJUAN)->subject($subjek);
            });
        } catch (\Throwable $e) {
            Log::warning('Email peringatan WhatsApp gagal dikirim', ['error' => $e->getMessage()]);
        }
    }
}
