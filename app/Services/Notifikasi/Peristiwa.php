<?php

namespace App\Services\Notifikasi;

use App\Models\Adm\CC;
use App\Models\Adm\Disposisi;
use App\Models\Adm\Surat;
use App\Models\Adm\SuratMasuk;
use App\Models\Adm\SuratTtdBasah;
use App\Models\Adm\SuratVoid;
use App\Models\Employe;
use App\Models\ESign\VerifStep;
use App\Models\Mobil\Permintaan;
use App\Models\Sppd\Ekstend;
use App\Models\Sppd\Realisasi;
use App\Models\Sppd\Sppd;
use App\Models\Structure;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pengganti trigger notifikasi lama di database. Setiap perubahan data yang dulu memicu trigger sekarang dipantau lewat event model,
 * lalu diteruskan ke Hub dengan isi yang rinci (nomor dokumen, pengaju, perihal). Tidak berjalan selama NOTIF_BARU_AKTIF=false.
 */
class Peristiwa
{
    public static function daftar(): void
    {
        // ADM: surat keluar
        Surat::created(fn ($s) => self::aman(fn () => self::suratReview($s)));
        Surat::updated(fn ($s) => self::aman(fn () => self::suratReview($s)));
        VerifStep::updated(fn ($v) => self::aman(fn () => self::langkahSelesai($v)));
        // ADM: surat masuk, disposisi, cc
        SuratMasuk::created(fn ($m) => self::aman(fn () => self::suratMasuk($m)));
        Disposisi::created(fn ($d) => self::aman(fn () => self::disposisi($d)));
        CC::created(fn ($c) => self::aman(fn () => self::cc($c)));
        // SPPD
        Sppd::created(fn ($s) => self::aman(fn () => self::sppdDiajukan($s)));
        Sppd::updated(fn ($s) => self::aman(fn () => self::sppdDiajukan($s, true)));
        Realisasi::created(fn ($r) => self::aman(fn () => self::realisasiDiajukan($r, true)));
        Realisasi::updated(fn ($r) => self::aman(fn () => self::realisasiDiajukan($r, false)));
        Ekstend::created(fn ($e) => self::aman(fn () => self::ekstendDiajukan($e)));
        // Mobil operasional
        Permintaan::created(fn ($p) => self::aman(fn () => self::mobilBaru($p)));
        Permintaan::updated(fn ($p) => self::aman(fn () => self::mobilDireview($p)));
    }

    private static function aman(callable $f): void
    {
        if (!Hub::aktif()) {
            return;
        }
        try {
            $f();
        } catch (\Throwable $e) {
            Log::warning('Peristiwa notifikasi gagal', ['error' => $e->getMessage(), 'at' => $e->getFile() . ':' . $e->getLine()]);
        }
    }

    private static function nama(?string $employeId): string
    {
        return $employeId ? (string) (Employe::where('employe_id', $employeId)->value('first_name') ?: $employeId) : '-';
    }

    private static function tgl($t): string
    {
        return $t ? Carbon::parse($t)->locale('id')->translatedFormat('d M Y') : '-';
    }

    /** employe_id dari posisi (position) atau PUK sebuah divisi (organization), sesuai pola penerima disposisi dan cc */
    private static function penerimaTujuan(?string $jenis, $id): ?string
    {
        if ($jenis === 'position') {
            return Structure::where('position_id', $id)->value('employe_id');
        }

        return DB::table('divisions')->where('organization_id', $id)->value('PUK');
    }

    // ---------- ADM surat keluar ----------

    private static function suratReview(Surat $s): void
    {
        $id = (int) $s->id;
        $s->getConnection()->afterCommit(function () use ($id) {
            // surat TTD manual tidak melewati alur review, surat void sudah tidak dipakai
            if (array_key_exists($id, SuratTtdBasah::peta()) || in_array($id, SuratVoid::ids(), true)) {
                return;
            }
            self::aman(function () use ($id) {
                $surat = Surat::find($id);
                $penerima = $surat ? VerifStep::where('id_doc', $surat->no_document)->orderBy('step')->value('id_employe') : null;
                if (!$penerima) {
                    return;
                }
                $pengaju = self::nama($surat->submitted_by);
                Hub::kirim($penerima, $surat->submitted_by, [
                    'modul' => 'adm',
                    'event' => 'adm.review',
                    'judul' => 'Mohon approval surat ' . $surat->nomor_surat,
                    'isi' => "Mohon approval dokumen surat nomor {$surat->nomor_surat} perihal \"{$surat->perihal}\" kepada {$surat->kepada}. Diajukan oleh {$pengaju}.",
                    'url' => 'adm',
                    'ref' => $surat->no_document,
                    'data' => ['nomor_surat' => $surat->nomor_surat],
                ]);
            });
        });
    }

    /** satu langkah verifikasi selesai: surat (ADM) memberi kabar ke pembuat, SPPD ke pemohon dan reviewer berikutnya */
    private static function langkahSelesai(VerifStep $v): void
    {
        if (!$v->wasChanged('status') || !in_array($v->status, ['approve', 'reject'], true)) {
            return;
        }
        $setuju = $v->status === 'approve';
        $dok = DB::connection('esign')->table('documents')->where('doc_id', $v->id_doc)->first(['created_by', 'jenis_doc']);
        if (!$dok) {
            return;
        }
        $pelaku = self::nama($v->id_employe);
        $catatan = trim((string) $v->ket) !== '' ? " Catatan: {$v->ket}" : '';

        if ((int) $dok->jenis_doc !== 1) {
            $surat = Surat::where('no_document', $v->id_doc)->first(['nomor_surat', 'perihal']);
            $nomor = $surat->nomor_surat ?? $v->id_doc;
            $aksi = $v->type === 'sign' ? ($setuju ? 'menandatangani' : 'tidak menandatangani') : ($setuju ? 'memberikan paraf pada' : 'tidak menyetujui');
            Hub::kirim($dok->created_by, $v->id_employe, [
                'modul' => 'adm',
                'event' => $setuju ? 'adm.disetujui' : 'adm.ditolak',
                'judul' => ($setuju ? 'Surat disetujui: ' : 'Surat tidak disetujui: ') . $nomor,
                'isi' => "{$pelaku} {$aksi} surat nomor {$nomor}" . (!empty($surat->perihal) ? " perihal \"{$surat->perihal}\"." : '.') . $catatan,
                'url' => 'adm',
                'ref' => $v->id_doc,
            ]);

            return;
        }

        $sppd = Sppd::where('nomor_dokumen', $v->id_doc)->first();
        if (!$sppd) {
            return;
        }
        $laporan = in_array($v->type, ['check_document', 'verif_realisasi'], true);
        $objek = $laporan ? 'laporan realisasi SPPD' : 'SPPD';
        $penerima = [$sppd->submitted_by, $sppd->employe_id];
        Hub::kirim($penerima, $v->id_employe, [
            'modul' => 'sppd',
            'event' => ($laporan ? 'sppd.laporan_' : 'sppd.') . ($setuju ? 'disetujui' : 'ditolak'),
            'judul' => ucfirst($objek) . ($setuju ? ' disetujui' : ' ditolak'),
            'isi' => "{$pelaku} " . ($setuju ? 'menyetujui' : 'menolak') . " {$objek} a.n. {$sppd->nama} (nomor {$sppd->nomor_sppd}).{$catatan}",
            'url' => 'sppd',
            'ref' => $v->id_doc,
        ]);
        if ($setuju) {
            $berikut = VerifStep::where('id_doc', $v->id_doc)->where('step', $v->step + 1)->value('id_employe');
            if ($berikut) {
                self::reviewSppd($sppd, $berikut, $sppd->submitted_by, $laporan ? 'laporan realisasi SPPD' : 'dokumen SPPD');
            }
        }
    }

    // ---------- ADM surat masuk ----------

    private static function suratMasuk(SuratMasuk $m): void
    {
        // baris disposisi pertama dibuat trigger surat_masuks (tidak memicu event), penerimanya direksi tujuan
        $penerima = Structure::where('position_id', $m->id_direksi)->value('employe_id');
        Hub::kirim($penerima, $m->insert_by, [
            'modul' => 'adm',
            'event' => 'adm.surat_masuk',
            'judul' => 'Surat masuk baru' . ($m->nomor ? ': ' . $m->nomor : ''),
            'isi' => "Ada surat masuk baru dari {$m->pengirim} perihal \"{$m->perihal}\"" . ($m->nomor ? " nomor {$m->nomor}" : '') . ', tanggal surat ' . self::tgl($m->tgl_surat) . '. Mohon segera diperiksa. Dicatat oleh ' . self::nama($m->insert_by) . '.',
            'url' => 'adm',
            'ref' => (string) $m->id,
        ]);
    }

    private static function disposisi(Disposisi $d): void
    {
        $masuk = SuratMasuk::find($d->id_surat);
        Hub::kirim(self::penerimaTujuan($d->dispo_to, $d->id_penerima), $d->employe_id, [
            'modul' => 'adm',
            'event' => 'adm.surat_masuk',
            'judul' => 'Disposisi surat masuk' . ($masuk && $masuk->nomor ? ': ' . $masuk->nomor : ''),
            'isi' => self::nama($d->employe_id) . ' mendisposisikan surat masuk' . ($masuk ? " dari {$masuk->pengirim} perihal \"{$masuk->perihal}\"" : '') . '.' . (trim((string) $d->catatan) !== '' ? " Catatan: {$d->catatan}" : '') . ' Mohon segera diperiksa.',
            'url' => 'adm',
            'ref' => (string) $d->id_surat,
        ]);
    }

    private static function cc(CC $c): void
    {
        $d = Disposisi::find($c->id_dispo);
        $masuk = $d ? SuratMasuk::find($d->id_surat) : null;
        Hub::kirim(self::penerimaTujuan($c->cc_to, $c->id_penerima), $d->employe_id ?? null, [
            'modul' => 'adm',
            'event' => 'adm.cc',
            'judul' => 'CC surat masuk' . ($masuk && $masuk->nomor ? ': ' . $masuk->nomor : ''),
            'isi' => 'Anda mendapat CC surat masuk' . ($masuk ? " dari {$masuk->pengirim} perihal \"{$masuk->perihal}\"" : '') . ($d ? ', disposisi dari ' . self::nama($d->employe_id) : '') . '. Mohon segera diperiksa.',
            'url' => 'adm',
            'ref' => $d ? (string) $d->id_surat : null,
        ]);
    }

    // ---------- SPPD ----------

    private static function ringkasSppd(Sppd $s): string
    {
        $t = DB::connection('mysql4')->table('tujuan_sppds')->where('id_sppd', $s->id)->orderBy('id')->first(['detail_tujuan', 'waktu_berangkat', 'waktu_kembali']);

        return "a.n. {$s->nama} nomor {$s->nomor_sppd}" . ($t ? ", tujuan {$t->detail_tujuan} (" . self::tgl($t->waktu_berangkat) . ' s.d. ' . self::tgl($t->waktu_kembali) . ')' : '');
    }

    private static function reviewSppd(Sppd $s, ?string $penerima, ?string $actor, string $objek): void
    {
        Hub::kirim($penerima, $actor, [
            'modul' => 'sppd',
            'event' => 'sppd.review',
            'judul' => 'Mohon review ' . $objek . ' ' . $s->nomor_sppd,
            'isi' => 'Mohon review ' . $objek . ' ' . self::ringkasSppd($s) . '. Diajukan oleh ' . self::nama($actor) . '.',
            'url' => 'sppd',
            'ref' => $s->nomor_dokumen,
        ]);
    }

    private static function sppdDiajukan(Sppd $s, bool $diubah = false): void
    {
        // nomor_dokumen dan langkah verifikasi dibuat trigger sppds, jadi baca ulang barisnya
        $id = (int) $s->id;
        $s->getConnection()->afterCommit(function () use ($id, $diubah) {
            self::aman(function () use ($id, $diubah) {
                $sppd = Sppd::find($id);
                if (!$sppd) {
                    return;
                }
                if ($sppd->employe_id && $sppd->employe_id !== '-') {
                    Hub::kirim($sppd->employe_id, $sppd->submitted_by, [
                        'modul' => 'sppd',
                        'event' => 'sppd.diajukan',
                        'judul' => 'Pengajuan SPPD ' . ($diubah ? 'diperbarui' : 'diajukan'),
                        'isi' => self::nama($sppd->submitted_by) . ($diubah ? ' memperbarui' : ' mengajukan') . ' SPPD ' . self::ringkasSppd($sppd) . ' untuk Anda.',
                        'url' => 'sppd',
                        'ref' => $sppd->nomor_dokumen,
                    ]);
                }
                $pertama = VerifStep::where('id_doc', $sppd->nomor_dokumen)->orderBy('step')->value('id_employe');
                self::reviewSppd($sppd, $pertama, $sppd->submitted_by, 'dokumen SPPD');
            });
        });
    }

    private static function realisasiDiajukan(Realisasi $r, bool $baru): void
    {
        $sppd = Sppd::find($r->id_sppd);
        if (!$sppd) {
            return;
        }
        $reviewer = $baru
            ? DB::table('divisions')->where('organization_id', 20)->value('PUK')
            : Structure::where('position_id', 43)->value('employe_id');
        self::reviewSppd($sppd, $reviewer, $r->submitted_by, 'laporan realisasi SPPD');
        Hub::kirim($sppd->employe_id, $r->submitted_by, [
            'modul' => 'sppd',
            'event' => 'sppd.laporan_diajukan',
            'judul' => 'Laporan SPPD disubmit',
            'isi' => 'Laporan realisasi SPPD ' . self::ringkasSppd($sppd) . ' telah disubmit oleh ' . self::nama($r->submitted_by) . '.',
            'url' => 'sppd',
            'ref' => $sppd->nomor_dokumen,
        ]);
    }

    private static function ekstendDiajukan(Ekstend $e): void
    {
        $sppdId = DB::connection('mysql4')->table('tujuan_sppds')->where('id', $e->id_tujuan)->value('id_sppd');
        $sppd = $sppdId ? Sppd::find($sppdId) : null;
        if (!$sppd) {
            return;
        }
        $penandatangan = VerifStep::where('id_doc', $sppd->nomor_dokumen)->where('type', 'sign')->value('id_employe');
        $isi = 'penambahan hari ' . self::tgl($e->start) . ' s.d. ' . self::tgl($e->end) . ' untuk SPPD ' . self::ringkasSppd($sppd) . ". Alasan: {$e->alasan}";
        Hub::kirim($penandatangan, $e->submitted_by, [
            'modul' => 'sppd',
            'event' => 'sppd.review',
            'judul' => 'Mohon review penambahan hari SPPD ' . $sppd->nomor_sppd,
            'isi' => 'Mohon review ' . $isi . '. Diajukan oleh ' . self::nama($e->submitted_by) . '.',
            'url' => 'sppd',
            'ref' => $sppd->nomor_dokumen,
        ]);
        Hub::kirim($sppd->employe_id, $e->submitted_by, [
            'modul' => 'sppd',
            'event' => 'sppd.ekstend_diajukan',
            'judul' => 'Pengajuan penambahan hari SPPD',
            'isi' => 'Pengajuan ' . $isi . '.',
            'url' => 'sppd',
            'ref' => $sppd->nomor_dokumen,
        ]);
    }

    // ---------- Asset ----------

    /** dipanggil InvController; $tahap: permintaan, disetujui, ditolak, selesai. Mengembalikan false bila notifikasi baru belum aktif */
    public static function asetService(string $tahap, \App\Models\Inven\AssetServis $servis, ?string $actor): bool
    {
        if (!Hub::aktif()) {
            return false;
        }
        self::aman(function () use ($tahap, $servis, $actor) {
            $nomor = \App\Models\Inven\AssetChild::where('id', $servis->asset_child)->value('asset_number') ?: ('#' . $servis->asset_child);
            $pemohon = self::nama($servis->request_by);
            if ($tahap === 'permintaan') {
                Hub::kirim(Structure::where('roles', 'like', '%PicAsset%')->pluck('employe_id')->all(), $actor, [
                    'modul' => 'asset', 'event' => 'asset.permintaan_service',
                    'judul' => 'Permintaan service aset ' . $nomor,
                    'isi' => "{$pemohon} mengajukan service untuk aset {$nomor}. Keluhan: {$servis->complaint}. Mohon ditinjau.",
                    'url' => 'asset', 'ref' => (string) $servis->id,
                ]);

                return;
            }
            $teks = [
                'disetujui' => ['Service aset disetujui', "Permintaan service aset {$nomor} disetujui dan sedang diproses."],
                'ditolak' => ['Service aset ditolak', "Permintaan service aset {$nomor} ditolak."],
                'selesai' => ['Service aset selesai', "Service aset {$nomor} telah selesai." . ($servis->cost ? ' Biaya: Rp ' . number_format((float) $servis->cost, 0, ',', '.') . '.' : '')],
            ][$tahap];
            Hub::kirim($servis->request_by, $actor, [
                'modul' => 'asset', 'event' => 'asset.service_' . $tahap,
                'judul' => $teks[0],
                'isi' => $teks[1] . ' Diproses oleh ' . self::nama($actor) . '.',
                'url' => 'asset', 'ref' => (string) $servis->id,
            ]);
        });

        return true;
    }

    // ---------- Mobil operasional ----------

    private static function mobilBaru(Permintaan $p): void
    {
        $pic = Structure::where('roles', 'like', '%PICMobil%')->pluck('employe_id')->all();
        Hub::kirim($pic, $p->created_by, [
            'modul' => 'mobil',
            'event' => 'mobil.permintaan',
            'judul' => 'Permintaan kendaraan operasional',
            'isi' => self::nama($p->created_by) . " meminta kendaraan operasional untuk \"{$p->keperluan}\", " . self::tgl($p->mulai) . ' s.d. ' . self::tgl($p->hingga) . ($p->sopir ? ', dengan sopir' : ', tanpa sopir') . '. Mohon ditinjau.',
            'url' => 'mobil-operational',
            'ref' => (string) $p->id,
        ]);
    }

    private static function mobilDireview(Permintaan $p): void
    {
        if (!$p->wasChanged('status') || !in_array((int) $p->status, [0, 1], true) || $p->status === null) {
            return;
        }
        $setuju = (int) $p->status === 1;
        Hub::kirim($p->created_by, $p->review_by, [
            'modul' => 'mobil',
            'event' => $setuju ? 'mobil.disetujui' : 'mobil.ditolak',
            'judul' => $setuju ? 'Permintaan kendaraan disetujui' : 'Permintaan kendaraan belum bisa disetujui',
            'isi' => "Permintaan kendaraan operasional untuk \"{$p->keperluan}\" (" . self::tgl($p->mulai) . ' s.d. ' . self::tgl($p->hingga) . ') ' . ($setuju ? 'telah disetujui' : 'belum bisa disetujui') . ' oleh ' . self::nama($p->review_by) . '.' . (trim((string) $p->ket) !== '' ? " Keterangan: {$p->ket}" : ''),
            'url' => 'mobil-operational',
            'ref' => (string) $p->id,
        ]);
    }
}
