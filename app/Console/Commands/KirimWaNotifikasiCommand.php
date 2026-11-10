<?php

namespace App\Console\Commands;

use App\Services\Notifikasi\Hub;
use App\Services\Notifikasi\Pengirim;
use Illuminate\Console\Command;

class KirimWaNotifikasiCommand extends Command
{
    protected $signature = 'notifikasi:kirim-wa';

    protected $description = 'Kirim satu pesan WhatsApp notifikasi dari antrean (dijadwalkan tiap menit agar nomor pengirim tidak diblokir).';

    public function handle(): int
    {
        if (!Hub::aktif() || !config('notifikasi.wa.aktif')) {
            return self::SUCCESS;
        }

        Pengirim::kirimWaBerikut();

        return self::SUCCESS;
    }
}
