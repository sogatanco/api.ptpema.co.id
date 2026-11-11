<?php

namespace App\Console\Commands;

use App\Services\Notifikasi\Hub;
use App\Services\Notifikasi\Pengaturan;
use App\Services\Notifikasi\PeringatanWa;
use App\Services\Notifikasi\Pengirim;
use Illuminate\Console\Command;

class KirimWaNotifikasiCommand extends Command
{
    protected $signature = 'notifikasi:kirim-wa';

    protected $description = 'Kirim satu pesan WhatsApp notifikasi dari antrean (dijadwalkan tiap menit agar nomor pengirim tidak diblokir).';

    public function handle(): int
    {
        if (!Hub::aktif() || Pengaturan::dijeda()) {
            return self::SUCCESS;
        }
        $g = Pengaturan::gateway();
        if (!$g['aktif']) {
            // pesan tetap mengantre; IT diberi tahu (paling banyak sekali per jam)
            PeringatanWa::kirim('gateway_mati', 'Gateway WhatsApp tidak aktif',
                'Status dari gateway WhatsApp (/health): ' . ($g['pesan'] ?: 'sesi WhatsApp tidak tersambung') . ".\n\nRespons: " . ($g['respons'] ?: '-') . "\n\nPesan notifikasi mengantre dan dikirim otomatis setelah gateway aktif kembali.", 60);

            return self::SUCCESS;
        }

        Pengirim::kirimWaBerikut();

        return self::SUCCESS;
    }
}
