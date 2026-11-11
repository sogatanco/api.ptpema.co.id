<?php

namespace App\Console\Commands;

use App\Models\Notif\Notifikasi;
use App\Models\Sppd\ListSppdRingan;
use App\Models\Sppd\SppdVoid;
use App\Services\Notifikasi\Hub;
use Illuminate\Console\Command;

class IngatkanVoidSppdCommand extends Command
{
    protected $signature = 'sppd:ingatkan-void {--hari=2 : lewat berapa hari dari tanggal kembali} {--ulang=7 : jarak minimal pengingat ke admin yang sama (hari)}';

    protected $description = 'Ingatkan admin pengaju SPPD agar segera void SPPD yang sudah lewat tanggal kembali tetapi belum ada realisasi (tidak jadi berangkat).';

    public function handle(): int
    {
        if (!Hub::aktif()) {
            return self::SUCCESS;
        }

        $void = SppdVoid::ids();
        $daftar = ListSppdRingan::whereIn('current_status', ['signed', 'under_review'])
            ->whereNull('realisasi_status')
            ->where('selesai_tugas', '<', now()->subDays((int) $this->option('hari')))
            ->when($void, fn ($q) => $q->whereNotIn('id', $void))
            ->orderBy('selesai_tugas')
            ->get(['id', 'nomor_sppd', 'nama', 'submitted_by', 'selesai_tugas'])
            ->groupBy('submitted_by');

        foreach ($daftar as $admin => $baris) {
            // satu ringkasan per admin, bukan satu pesan per SPPD, supaya tidak membanjiri bel dan antrean WhatsApp
            $sudah = Notifikasi::where('recipient', $admin)->where('event', 'sppd.ingatkan_void')
                ->where('created_at', '>', now()->subDays((int) $this->option('ulang')))->exists();
            if ($sudah) {
                continue;
            }
            $contoh = $baris->take(5)->map(fn ($s) => "{$s->nomor_sppd} a.n. {$s->nama}")->implode('; ');
            $sisa = $baris->count() - 5;
            Hub::kirim($admin, null, [
                'modul' => 'sppd',
                'event' => 'sppd.ingatkan_void',
                'judul' => $baris->count() . ' SPPD belum ada realisasi, segera void bila tidak jadi berangkat',
                'isi' => "Ada {$baris->count()} SPPD yang sudah lewat tanggal kembali tetapi belum ada realisasi: {$contoh}" . ($sisa > 0 ? " dan {$sisa} lainnya" : '')
                    . '. Jika perjalanan dinas tidak jadi berangkat, segera lakukan void beserta alasannya.',
                'url' => 'sppd#1',
                'ref' => 'ingatkan-void',
                'data' => ['jumlah' => $baris->count(), 'id' => $baris->pluck('id')->all()],
            ]);
            $this->info("Pengingat ke {$admin}: {$baris->count()} SPPD");
        }

        return self::SUCCESS;
    }
}
