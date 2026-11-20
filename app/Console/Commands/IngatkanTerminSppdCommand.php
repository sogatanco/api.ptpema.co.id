<?php

namespace App\Console\Commands;

use App\Models\Notif\Notifikasi;
use App\Models\Structure;
use App\Models\Sppd\HitunganBiaya;
use App\Models\Sppd\ListSppdRingan;
use App\Models\Sppd\ProsesTermin;
use App\Models\Sppd\Sppd;
use App\Models\Sppd\SppdVoid;
use App\Services\Notifikasi\Hub;
use App\Services\Sppd\UangMuka;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sehari sebelum jatuh tempo tiap termin uang muka, ingatkan divisi keuangan agar memproses transfer dan mengupload bukti transfer.
 * Termin dibayar pada tanggal berangkat (termin 1) lalu tiap 4 hari. Pengingat hanya dikirim sekali per termin
 * dan hanya untuk termin yang belum punya bukti, pada SPPD yang sudah siap diproses divisi keuangan.
 */
class IngatkanTerminSppdCommand extends Command
{
    protected $signature = 'sppd:ingatkan-termin {--besok= : tanggal jatuh tempo yang diingatkan (Y-m-d), bawaan: besok}';

    protected $description = 'Ingatkan divisi keuangan H-1 sebelum termin uang muka SPPD: proses transfer dan upload bukti transfer.';

    public function handle(): int
    {
        if (!Hub::aktif()) {
            return self::SUCCESS;
        }

        $besok = $this->option('besok') ? Carbon::parse($this->option('besok'))->startOfDay() : now()->addDay()->startOfDay();
        $tgl = $besok->toDateString();
        $ada = Schema::connection('mysql4')->hasTable('proses_termin');
        $void = SppdVoid::ids();
        $keuangan = Structure::where('roles', 'like', '%KeuanganSppd%')->pluck('employe_id')->all();
        if (!$keuangan) {
            return self::SUCCESS;
        }

        // SPPD disetujui dan uang mukanya belum selesai diproses; termin jatuh tempo <= tanggal kembali
        $sppds = ListSppdRingan::where('current_status', 'signed')->where('uangmuka', 0)
            ->when($void, fn ($q) => $q->whereNotIn('id', $void))
            ->whereIn('id', DB::connection('mysql4')->table('tujuan_sppds')
                ->whereRaw('DATE(waktu_berangkat) <= ?', [$tgl])->whereRaw('DATE(waktu_kembali) >= ?', [$tgl])->pluck('id_sppd'))
            ->get();

        $terkirim = 0;
        foreach ($sppds as $r) {
            // SPPD lama: baru bisa diproses divisi keuangan setelah divisi umum selesai (bila perlu tiket); SPPD baru bersamaan
            if (strtotime((string) $r->created_at) < strtotime((string) config('sppd.proses_paralel_mulai')) && (int) $r->by_umum === 0
                && DB::connection('mysql4')->table('tujuan_sppds')->where('id_sppd', $r->id)
                    ->when(Schema::connection('mysql4')->hasColumn('tujuan_sppds', 'perlu_tiket'), fn ($q) => $q->where('perlu_tiket', 1))->exists()) {
                continue;
            }
            $sppd = Sppd::find($r->id);
            if (!$sppd) {
                continue;
            }
            $buktiAda = $ada ? ProsesTermin::where('id_sppd', $r->id)->get()->keyBy(fn ($x) => $x->id_tujuan . ':' . $x->termin) : collect();

            foreach (HitunganBiaya::where('id_sppd', $r->id)->get() as $t) {
                $rincianUm = UangMuka::rincian($t);
                $bertermin = !empty($rincianUm['termin_aktif']);
                foreach ($rincianUm['termin'] as $x) {
                    if ($x['tgl_bayar'] !== $tgl || $buktiAda->has($t->id . ':' . $x['no'])) {
                        continue;
                    }
                    $ref = "termin-{$r->id}-{$t->id}-{$x['no']}";
                    if (Notifikasi::where('event', 'sppd.pengingat_termin')->where('ref', $ref)->exists()) {
                        continue;
                    }
                    $rp = 'Rp ' . number_format($x['jumlah'], 0, ',', '.');
                    $tglIndo = $besok->locale('id')->translatedFormat('d F Y');
                    Hub::kirim($keuangan, null, [
                        'modul' => 'sppd',
                        'event' => 'sppd.pengingat_termin',
                        'judul' => $bertermin ? "Besok jatuh tempo termin {$x['no']} uang muka SPPD {$r->nomor_sppd}" : "Besok jatuh tempo uang muka SPPD {$r->nomor_sppd}",
                        'isi' => ($bertermin ? "Termin {$x['no']} uang muka" : 'Uang muka') . " SPPD {$r->nomor_sppd} a.n. {$r->nama} ({$t->detail_tujuan}) sebesar {$rp} jatuh tempo besok, {$tglIndo}" . ($bertermin ? " ({$x['keterangan']})" : '') . '. '
                            . 'Mohon proses transfer dan upload bukti transfer hari ini.',
                        // tab PERLU DIPROSES (#4) dan membuka SPPD-nya
                        'url' => 'sppd?dok=' . rawurlencode((string) $sppd->nomor_dokumen) . '&aksi=proses#4',
                        'ref' => $ref,
                        'data' => ['id_sppd' => $r->id, 'id_tujuan' => $t->id, 'termin' => $x['no'], 'tgl_bayar' => $tgl, 'jumlah' => $x['jumlah']],
                    ]);
                    $terkirim++;
                    $this->info("Pengingat termin {$x['no']} SPPD {$r->nomor_sppd} ({$rp}, {$tgl})");
                }
            }
        }
        $this->info("Selesai: {$terkirim} pengingat untuk tanggal {$tgl}.");

        return self::SUCCESS;
    }
}
