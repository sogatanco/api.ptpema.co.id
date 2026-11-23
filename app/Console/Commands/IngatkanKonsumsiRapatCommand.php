<?php

namespace App\Console\Commands;

use App\Models\Employe;
use App\Models\Meeting\BookingRoom;
use App\Models\Structure;
use App\Services\Notifikasi\Hub;
use App\Services\Notifikasi\PicKonsumsi;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pengingat untuk PIC Konsumsi: 1 jam sebelum rapat yang meminta konsumsi dimulai.
 * Hanya untuk rapat yang dibuat lebih dari 1 jam sebelum mulai; rapat yang dibuat mendadak (kurang dari 1 jam)
 * sudah langsung diberitahukan saat dibuat sehingga tidak perlu diingatkan lagi.
 */
class IngatkanKonsumsiRapatCommand extends Command
{
    protected $signature = 'meeting:ingatkan-konsumsi {--menit=60 : berapa menit sebelum rapat dimulai} {--kering : hanya tampilkan rapat dan penerima yang akan diingatkan, tanpa mengirim dan tanpa menandai}';

    protected $description = 'Ingatkan PIC Konsumsi 1 jam sebelum rapat yang meminta konsumsi (bila rapat dibuat lebih dari 1 jam sebelumnya).';

    public function handle(): int
    {
        // kolom penanda dibuat oleh db/2026-11-23/meeting_pengingat_konsumsi.sql; sebelum itu dijalankan, perintah ini tidak melakukan apa pun
        if (!Schema::hasColumn('meetings', 'pengingat_konsumsi_at')) {
            $this->warn('Kolom meetings.pengingat_konsumsi_at belum ada (jalankan db/2026-11-23/meeting_pengingat_konsumsi.sql).');

            return self::SUCCESS;
        }

        $menit = (int) $this->option('menit');
        $sekarang = now();

        $daftar = BookingRoom::where('consumption_required', 1)
            ->whereNull('canceled_at')
            ->whereNull('pengingat_konsumsi_at')
            ->where('start_time', '>', $sekarang)
            ->where('start_time', '<=', $sekarang->copy()->addMinutes($menit))
            ->orderBy('start_time')
            ->get()
            // dibuat kurang dari 1 jam sebelum mulai: PIC sudah diberi tahu langsung saat dibuat
            ->filter(fn ($r) => $r->created_at && Carbon::parse($r->created_at)->lt(Carbon::parse($r->start_time)->subMinutes($menit)));

        if ($daftar->isEmpty()) {
            return self::SUCCESS;
        }

        $penerima = PicKonsumsi::daftar();

        if ($this->option('kering')) {
            foreach ($daftar as $rapat) {
                $this->line("[kering] rapat #{$rapat->id} \"{$rapat->topic}\" mulai {$rapat->start_time} -> " . count($penerima) . ' PIC Konsumsi');
            }
            $this->line('[kering] tidak ada pesan yang dikirim.');

            return self::SUCCESS;
        }

        foreach ($daftar as $rapat) {
            // ditandai lebih dulu agar tidak terkirim dua kali bila perintah berjalan tumpang tindih
            if (!BookingRoom::where('id', $rapat->id)->whereNull('pengingat_konsumsi_at')->update(['pengingat_konsumsi_at' => now()])) {
                continue;
            }

            if (!$penerima) {
                Log::warning('Pengingat konsumsi: tidak ada PIC Konsumsi', ['rapat' => $rapat->id]);

                continue;
            }

            $pembuat = Employe::where('employe_id', $rapat->created_by)->value('first_name') ?: '-';
            $hp = (string) (Structure::where('employe_id', $rapat->created_by)->value('phone_number') ?? '');
            $mulai = Carbon::parse($rapat->start_time);
            $selesai = Carbon::parse($rapat->end_time);

            Hub::kirim($penerima, null, [
                'modul' => 'meeting',
                'event' => 'meeting.ingatkan_konsumsi',
                'judul' => 'Pengingat Konsumsi: rapat ' . $mulai->format('H:i') . ' - ' . $rapat->topic,
                'isi' => "Rapat \"{$rapat->topic}\" dimulai pukul " . $mulai->format('H:i') . ' WIB (sekitar 1 jam lagi).' . "\n"
                    . "Ruangan: {$rapat->room}\n"
                    . 'Waktu: ' . $mulai->locale('id')->translatedFormat('l, d F Y H:i') . ' - ' . $selesai->format('H:i') . " WIB\n"
                    . "Peserta: {$rapat->participants}\n"
                    . 'Detail konsumsi: ' . ($rapat->consumption_detail ?: '-') . "\n"
                    . 'Project / Renbis: ' . ($rapat->renbis_name ?: '-') . "\n"
                    . "PIC: {$pembuat}" . ($hp !== '' ? " ({$hp})" : ''),
                'url' => 'book-meeting',
                'ref' => 'rapat-' . $rapat->id,
                'data' => ['rapat_id' => $rapat->id, 'ruangan' => $rapat->room, 'mulai' => $rapat->start_time],
                'paksa' => true,
            ]);

            $this->info("Pengingat konsumsi rapat #{$rapat->id} ({$rapat->topic}) dikirim ke " . count($penerima) . ' PIC');
        }

        return self::SUCCESS;
    }
}
