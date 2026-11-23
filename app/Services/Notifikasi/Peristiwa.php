<?php

namespace App\Services\Notifikasi;

use App\Models\Adm\CC;
use App\Models\Adm\Disposisi;
use App\Models\Adm\Surat;
use App\Models\Adm\SuratBackdate;
use App\Models\Adm\SuratMasuk;
use App\Models\Adm\SuratTtdBasah;
use App\Models\Adm\SuratVoid;
use App\Models\Employe;
use App\Models\ESign\VerifStep;
use App\Models\Mobil\Permintaan;
use App\Models\Sppd\Ekstend;
use App\Models\Sppd\Realisasi;
use App\Models\Sppd\Sppd;
use App\Models\Sppd\SppdVoid;
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
        Surat::updated(fn ($s) => self::aman(fn () => self::suratReview($s, true)));
        VerifStep::updated(fn ($v) => self::aman(fn () => self::langkahSelesai($v)));
        // ADM: permintaan nomor surat bertanggal mundur (backdate)
        SuratBackdate::created(fn ($r) => self::aman(fn () => self::backdateDiajukan($r)));
        SuratBackdate::updated(fn ($r) => self::aman(fn () => self::backdateDiproses($r)));
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

    private static function suratReview(Surat $s, bool $diperbarui = false): void
    {
        $id = (int) $s->id;
        $s->getConnection()->afterCommit(function () use ($id, $diperbarui) {
            // surat TTD manual tidak melewati alur review, surat void sudah tidak dipakai
            if (array_key_exists($id, SuratTtdBasah::peta()) || in_array($id, SuratVoid::ids(), true)) {
                return;
            }
            self::aman(function () use ($id, $diperbarui) {
                $surat = Surat::find($id);
                $penerima = $surat ? VerifStep::where('id_doc', $surat->no_document)->orderBy('step')->value('id_employe') : null;
                if ($penerima) {
                    self::reviewSurat($surat, $penerima, $surat->submitted_by, $diperbarui);
                }
            });
        });
    }

    /** permintaan approval surat ke satu reviewer (langkah pertama saat diajukan atau diperbarui, langkah berikutnya setelah reviewer sebelumnya approve) */
    private static function reviewSurat(Surat $surat, string $penerima, ?string $actor, bool $diperbarui = false): void
    {
        $pengaju = self::nama($surat->submitted_by);
        Hub::kirim($penerima, $actor, [
            'modul' => 'adm',
            'event' => 'adm.review',
            'judul' => ($diperbarui ? 'Surat diperbarui, mohon approval ulang ' : 'Mohon approval surat ') . $surat->nomor_surat,
            'isi' => ($diperbarui ? "Surat nomor {$surat->nomor_surat} telah diperbarui oleh {$pengaju} dan perlu di-approve ulang. " : '') . "Mohon approval dokumen surat nomor {$surat->nomor_surat} perihal \"{$surat->perihal}\" kepada {$surat->kepada}. Diajukan oleh {$pengaju}.",
            // tab Review (#2) dan membuka dokumennya langsung
            'url' => 'adm?dok=' . rawurlencode((string) $surat->no_document) . '#2',
            'ref' => $surat->no_document,
            'data' => ['nomor_surat' => $surat->nomor_surat],
        ]);
    }

    /** satu langkah verifikasi selesai: surat (ADM) memberi kabar ke pembuat, SPPD ke pemohon dan reviewer berikutnya */
    private static function langkahSelesai(VerifStep $v): void
    {
        // frontend ADM dan SPPD mengirim 'rejected'
        if (!$v->wasChanged('status') || !in_array($v->status, ['approve', 'reject', 'rejected'], true)) {
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
                // tab Surat Saya (#1) dan membuka progres approval dokumennya
                'url' => 'adm?dok=' . rawurlencode((string) $v->id_doc) . '#1',
                'ref' => $v->id_doc,
            ]);
            // reviewer langkah berikutnya baru diminta approval setelah langkah ini disetujui
            if ($setuju && $surat) {
                $surat = Surat::where('no_document', $v->id_doc)->first();
                $berikut = VerifStep::where('id_doc', $v->id_doc)->where('step', '>', $v->step)->orderBy('step')->value('id_employe');
                if ($surat && $berikut) {
                    self::reviewSurat($surat, $berikut, $v->id_employe);
                }
            }

            return;
        }

        $sppd = Sppd::where('nomor_dokumen', $v->id_doc)->first();
        if (!$sppd) {
            return;
        }
        $laporan = in_array($v->type, ['check_document', 'verif_realisasi'], true);
        $ekstendLangkah = self::tipeEkstend((string) $v->type);
        $noEks = $ekstendLangkah && preg_match('/^extend-(\d+)$/', (string) $v->type, $mm) ? (int) $mm[1] : null;
        $objek = $laporan ? 'laporan realisasi SPPD' : ($ekstendLangkah ? 'penambahan hari (' . ($noEks ? 'extend ' . $noEks : 'extend') . ') SPPD' : 'SPPD');
        $pesan = [
            'modul' => 'sppd',
            'event' => ($laporan ? 'sppd.laporan_' : ($ekstendLangkah ? 'sppd.extend_' : 'sppd.')) . ($setuju ? 'disetujui' : 'ditolak'),
            'judul' => ucfirst($objek) . ($setuju ? ' disetujui' : ' ditolak'),
            'isi' => "{$pelaku} " . ($setuju ? 'menyetujui' : 'menolak') . " {$objek} a.n. {$sppd->nama} (nomor {$sppd->nomor_sppd}).{$catatan}",
            'ref' => $v->id_doc,
        ];
        // pengaju melihatnya di tab pengajuan (#1), pemilik SPPD di tab SPPD saya (#2)
        Hub::kirim($sppd->submitted_by, $v->id_employe, $pesan + ['url' => self::urlSppd($v->id_doc, 1)]);
        if ($sppd->employe_id && $sppd->employe_id !== $sppd->submitted_by) {
            Hub::kirim($sppd->employe_id, $v->id_employe, $pesan + ['url' => self::urlSppd($v->id_doc, 2)]);
        }
        if ($setuju) {
            // extend: langkah berikutnya hanya dari extend yang sama (tiap extend punya approval sendiri)
            $berikut = VerifStep::where('id_doc', $v->id_doc)->where('step', '>', $v->step)->when($ekstendLangkah, fn ($q) => $q->where('type', $v->type))->orderBy('step')->value('id_employe');
            if ($berikut) {
                self::reviewSppd($sppd, $berikut, $sppd->submitted_by, $laporan ? 'laporan realisasi SPPD' : ($ekstendLangkah ? $objek : 'dokumen SPPD'));
            }
            // langkah 'sign' = tanda tangan akhir: SPPD resmi disetujui (current_status 'signed') dan masuk tab PERLU DIPROSES divisi umum
            if (!$laporan && $v->type === 'sign') {
                self::sppdSiapDiproses($sppd, $v->id_employe);
            }
        }
    }

    /**
     * divisi umum selesai memproses SPPD ($kejadian 'diproses') atau mengubah tiketnya ('tiket_diubah').
     * Pelaksana dan admin pengaju dikabari; divisi keuangan tidak diberi pemberitahuan dari proses ini.
     */
    public static function sppdProsesUmum(Sppd $s, string $kejadian, string $detail, ?string $actor, bool $ekstend = false): void
    {
        $diubah = $kejadian === 'tiket_diubah';
        $pelaku = self::nama($actor);
        $ringkas = self::ringkasSppd($s);
        $pesan = [
            'modul' => 'sppd',
            'event' => $diubah ? 'sppd.tiket_diubah' : 'sppd.proses_umum',
            'judul' => ($diubah ? 'Tiket SPPD diperbarui divisi umum: ' : 'Tiket SPPD diproses divisi umum: ') . $s->nomor_sppd,
            'isi' => "{$pelaku} (divisi umum) " . ($diubah ? 'memperbarui' : 'memproses') . " tiket untuk SPPD {$ringkas}. {$detail}",
            'ref' => $s->nomor_dokumen,
        ];
        // pengaju melihatnya di tab pengajuan (#1), pelaksana di tab SPPD saya (#2)
        self::kabariPelaksanaDanPengaju($s, $actor, $pesan);
    }

    /**
     * Kabari pelaksana SPPD (tab SPPD Saya) dan admin pengaju (tab Pengajuan) tentang proses divisi umum atau divisi keuangan.
     * Pelaksana selalu ikut dikabari; bila pelaksana sekaligus pengaju, ia menerima satu pemberitahuan.
     */
    private static function kabariPelaksanaDanPengaju(Sppd $s, ?string $actor, array $pesan): void
    {
        $nomor = (string) $s->nomor_dokumen;
        Hub::kirim($s->submitted_by, $actor, $pesan + ['url' => self::urlSppd($nomor, 1)]);
        if ($s->employe_id && $s->employe_id !== $s->submitted_by) {
            Hub::kirim($s->employe_id, $actor, $pesan + ['url' => self::urlSppd($nomor, 2)]);
        }
    }

    /** divisi keuangan mengupload bukti transfer uang muka per termin: kabari pelaksana dan admin pengaju (progres, dan selesai bila semua termin sudah) */
    public static function sppdBuktiTermin(Sppd $s, string $daftarTermin, int $sudah, int $total, bool $selesai, ?string $actor): void
    {
        $pesan = [
            'modul' => 'sppd',
            'event' => 'sppd.uangmuka_termin',
            'judul' => $selesai ? 'Uang muka SPPD selesai ditransfer: ' . $s->nomor_sppd : "Uang muka SPPD ditransfer (termin {$sudah}/{$total}): " . $s->nomor_sppd,
            'isi' => self::nama($actor) . ' (divisi keuangan) telah mentransfer uang muka SPPD ' . self::ringkasSppd($s) . ": {$daftarTermin}. Bukti transfer sudah diupload."
                . ($selesai ? ' Seluruh termin uang muka telah ditransfer.' : " Progres {$sudah} dari {$total} termin."),
            'ref' => $s->nomor_dokumen,
        ];
        self::kabariPelaksanaDanPengaju($s, $actor, $pesan);
    }

    /** divisi keuangan memproses uang muka ($jenis 'uangmuka') atau pembayaran realisasi ('realisasi'): kabari pelaksana dan admin pengaju */
    public static function sppdProsesKeuangan(Sppd $s, string $jenis, bool $adaLampiran, ?string $actor): void
    {
        $uangMuka = $jenis === 'uangmuka';
        $label = $uangMuka ? 'uang muka' : 'pembayaran realisasi';
        $pesan = [
            'modul' => 'sppd',
            'event' => $uangMuka ? 'sppd.proses_uangmuka' : 'sppd.proses_realisasi',
            'judul' => ucfirst($label) . ' SPPD diproses divisi keuangan: ' . $s->nomor_sppd,
            'isi' => self::nama($actor) . " (divisi keuangan) telah memproses {$label} SPPD " . self::ringkasSppd($s) . '. ' . ($adaLampiran ? 'Bukti bayar dilampirkan.' : 'Tanpa lampiran bukti bayar.'),
            'ref' => $s->nomor_dokumen,
        ];
        self::kabariPelaksanaDanPengaju($s, $actor, $pesan);
    }

    /** KeuanganSppd menandai termin uang muka tidak dibayarkan di muka: pelaksana dan admin pengaju diberi tahu bahwa biayanya dibayar saat realisasi */
    public static function sppdUangMukaAktual(Sppd $s, string $daftarTermin, string $alasan, ?string $actor, bool $ekstend = false): void
    {
        self::aman(function () use ($s, $daftarTermin, $alasan, $actor, $ekstend) {
            $objek = $ekstend ? 'Uang muka extend SPPD' : 'Uang muka SPPD';
            $pesan = [
                'modul' => 'sppd',
                'event' => 'sppd.uangmuka_aktual',
                'judul' => $objek . ' tidak dibayarkan di muka: ' . $s->nomor_sppd,
                'isi' => self::nama($actor) . ' (divisi keuangan) menandai ' . $daftarTermin . ' pada ' . strtolower($objek) . ' ' . self::ringkasSppd($s) . ' tidak dibayarkan di muka. Biayanya dibayarkan saat realisasi (aktual).' . ($alasan !== '' ? ' Alasan: ' . $alasan : ''),
                'ref' => $s->nomor_dokumen,
            ];
            self::kabariPelaksanaDanPengaju($s, $actor, $pesan);
        });
    }

    /** SPPD sudah disetujui penuh: kabari semua user divisi umum agar memproses tiket (konfirmasi ke pelaksana dan admin pengaju) */
    public static function sppdSiapDiproses(Sppd $s, ?string $actor): void
    {
        if (array_key_exists((int) $s->id, SppdVoid::peta())) {
            return;
        }
        // tidak ada tujuan yang perlu tiket: tidak lewat divisi umum, langsung ke divisi keuangan
        $perluTiket = true;
        try {
            if (\Illuminate\Support\Facades\Schema::connection('mysql4')->hasColumn('tujuan_sppds', 'perlu_tiket')) {
                $perluTiket = DB::connection('mysql4')->table('tujuan_sppds')->where('id_sppd', $s->id)->where('perlu_tiket', 1)->exists();
            }
        } catch (\Throwable $e) {
            $perluTiket = true;
        }
        // SPPD baru: divisi keuangan (uang muka) dan divisi umum (tiket) bekerja bersamaan, keduanya dikabari begitu SPPD disetujui.
        // SPPD lama (dibuat sebelum config sppd.proses_paralel_mulai): hanya divisi keuangan bila tanpa tiket, selebihnya divisi umum dulu.
        $paralel = $s->created_at && strtotime((string) $s->created_at) >= strtotime((string) config('sppd.proses_paralel_mulai'));
        if ($paralel || !$perluTiket) Hub::kirim(Structure::where('roles', 'like', '%KeuanganSppd%')->pluck('employe_id')->all(), $actor, [
            'modul' => 'sppd',
            'event' => 'sppd.siap_keuangan',
            'judul' => 'SPPD disetujui, siap diproses divisi keuangan: ' . $s->nomor_sppd,
            'isi' => 'SPPD ' . self::ringkasSppd($s) . ' telah disetujui. Mohon proses uang muka' . ($perluTiket ? ' (tiket diproses divisi umum secara bersamaan).' : ' (tidak memerlukan tiket).'),
            'url' => self::urlSppd((string) $s->nomor_dokumen, 4, true),
            'ref' => $s->nomor_dokumen,
            'data' => ['nomor_sppd' => $s->nomor_sppd],
        ]);
        if (!$perluTiket) {
            return;
        }
        $umum = Structure::where('roles', 'like', '%UmumSppd%')->pluck('employe_id')->all();
        Hub::kirim($umum, $actor, [
            'modul' => 'sppd',
            // UmumSppd tetap diberi tahu walau penyetuju terakhir adalah dirinya sendiri (kalau tidak, tiket tidak ada yang memproses)
            'termasuk_pelaku' => true,
            'event' => 'sppd.proses_tiket',
            'judul' => 'SPPD disetujui, mohon proses tiket ' . $s->nomor_sppd,
            'isi' => 'SPPD ' . self::ringkasSppd($s) . ' telah disetujui. Mohon proses tiket perjalanan: hubungi pelaksana SPPD (' . self::nama($s->employe_id)
                . ') dan admin pengaju (' . self::nama($s->submitted_by) . '), lalu tandai selesai di tab Perlu Diproses.',
            // tab PERLU DIPROSES (#4) dan membuka detail SPPD-nya
            'url' => self::urlSppd((string) $s->nomor_dokumen, 4, true),
            'ref' => $s->nomor_dokumen,
            'data' => ['nomor_sppd' => $s->nomor_sppd],
        ]);
    }

    // ---------- ADM backdate ----------

    private static function backdateDiajukan(SuratBackdate $r): void
    {
        $id = (int) $r->id;
        $r->getConnection()->afterCommit(function () use ($id) {
            self::aman(function () use ($id) {
                $r = SuratBackdate::find($id);
                if (!$r) {
                    return;
                }
                $p = json_decode((string) $r->payload, true) ?: [];
                $tgl = self::tgl(substr((string) $r->getRawOriginal('tanggal'), 0, 10));
                Hub::kirim(Structure::where('roles', 'like', '%PICAdm%')->pluck('employe_id')->all(), $r->requested_by, [
                    'modul' => 'adm',
                    'event' => 'adm.backdate_diajukan',
                    'judul' => 'Permintaan nomor surat backdate ' . $tgl,
                    'isi' => 'Mohon pilih nomor untuk surat bertanggal mundur ' . $tgl . ' perihal "' . ($p['perihal'] ?? '-') . '" kepada ' . ($p['kepada'] ?? '-') . '. Diajukan oleh ' . self::nama($r->requested_by) . '.',
                    // tab PICAdm (#7) dan membuka panel "Pilih nomor" untuk permintaan ini
                    'url' => 'adm?backdate=' . $id . '#7',
                    'ref' => 'backdate-' . $id,
                    'termasuk_pelaku' => true,
                ]);
            });
        });
    }

    /** permintaan backdate selesai diproses PICAdm: kabari admin yang mengajukan */
    private static function backdateDiproses(SuratBackdate $r): void
    {
        if (!$r->wasChanged('status') || !in_array($r->status, ['selesai', 'ditolak'], true)) {
            return;
        }
        $id = (int) $r->id;
        $r->getConnection()->afterCommit(function () use ($id) {
            self::aman(function () use ($id) {
                $r = SuratBackdate::find($id);
                if (!$r) {
                    return;
                }
                $selesai = $r->status === 'selesai';
                // sudah bernomor: buka progres approval suratnya (tab Surat Saya); ditolak: tab Backdate, permintaan beserta alasannya
                $noDokumen = $selesai && $r->surat_id ? Surat::where('id', $r->surat_id)->value('no_document') : null;
                $url = $noDokumen ? 'adm?dok=' . rawurlencode((string) $noDokumen) . '#1' : ($selesai ? 'adm#1' : 'adm?sub=backdate#1');
                Hub::kirim($r->requested_by, $r->processed_by, [
                    'modul' => 'adm',
                    'event' => $selesai ? 'adm.backdate_disetujui' : 'adm.backdate_ditolak',
                    'judul' => $selesai ? 'Permintaan backdate disetujui: ' . $r->nomor_surat : 'Permintaan backdate ditolak',
                    'isi' => $selesai
                        ? self::nama($r->processed_by) . " memilih nomor {$r->nomor_surat} untuk surat bertanggal mundur Anda. Surat sudah masuk alur approval."
                        : self::nama($r->processed_by) . ' menolak permintaan nomor surat backdate Anda.' . (trim((string) $r->alasan_tolak) !== '' ? " Alasan: {$r->alasan_tolak}" : ''),
                    'url' => $url,
                    'ref' => 'backdate-' . $id,
                    'termasuk_pelaku' => true,
                ]);
            });
        });
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
            'url' => 'adm?masuk=' . $m->id . '#4',
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
            'url' => 'adm?masuk=' . $d->id_surat . '#4',
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
            'url' => $d ? 'adm?masuk=' . $d->id_surat . '#5' : 'adm#5',
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
            'url' => self::urlSppd($s->nomor_dokumen, 3),
            'ref' => $s->nomor_dokumen,
        ]);
    }

    /**
     * Extend (tambah hari / tujuan baru) selesai disetujui: bila perlu tiket masuk proses divisi umum, bila tidak langsung ke divisi keuangan (uang muka).
     * @param \Illuminate\Support\Collection $daftar kumpulan model Ekstend yang baru disetujui
     */
    public static function sppdExtendSiapDiproses(Sppd $s, $daftar, ?string $actor): void
    {
        self::aman(function () use ($s, $daftar, $actor) {
            $ringkas = fn ($rows) => $rows->map(fn ($e) => self::labelEkstend($e) . ' - ' . \App\Services\Sppd\EkstendProses::judul($e))->implode('; ');
            $perlu = $daftar->where('perlu_tiket', 1);
            if ($perlu->isNotEmpty()) {
                Hub::kirim(Structure::where('roles', 'like', '%UmumSppd%')->pluck('employe_id')->all(), $actor, [
                    'modul' => 'sppd',
                    'termasuk_pelaku' => true, // UmumSppd tetap dikabari walau penyetuju terakhir adalah dirinya sendiri
                    'event' => 'sppd.extend_proses_tiket',
                    'judul' => 'Extend SPPD disetujui, mohon proses tiket ' . $s->nomor_sppd,
                    'isi' => 'Extend SPPD ' . self::ringkasSppd($s) . ' telah disetujui: ' . $ringkas($perlu) . '. Mohon proses tiket perjalanan: hubungi pelaksana SPPD ('
                        . self::nama($s->employe_id) . ') dan admin pengaju (' . self::nama($s->submitted_by) . ').',
                    'url' => self::urlSppd((string) $s->nomor_dokumen, 4, true, true),
                    'ref' => $s->nomor_dokumen,
                    'data' => ['nomor_sppd' => $s->nomor_sppd],
                ]);
            }
            if ($daftar->isNotEmpty()) {
                Hub::kirim(Structure::where('roles', 'like', '%KeuanganSppd%')->pluck('employe_id')->all(), $actor, [
                    'modul' => 'sppd',
                    'event' => 'sppd.extend_siap_keuangan',
                    'judul' => 'Extend SPPD disetujui, siap diproses divisi keuangan: ' . $s->nomor_sppd,
                    'isi' => 'Extend SPPD ' . self::ringkasSppd($s) . ' telah disetujui: ' . $ringkas($daftar) . '. Mohon proses uang muka' . ($perlu->isNotEmpty() ? ' (tiket diproses divisi umum secara bersamaan).' : ' (tidak memerlukan tiket).') . ($daftar->sum(fn ($e) => (float) ($e->bbm ?? 0)) > 0 ? ' Termasuk uang BBM Rp ' . number_format((float) $daftar->sum(fn ($e) => (float) ($e->bbm ?? 0)), 0, ',', '.') . '.' : ''),
                    'url' => self::urlSppd((string) $s->nomor_dokumen, 4, true, true),
                    'ref' => $s->nomor_dokumen,
                    'data' => ['nomor_sppd' => $s->nomor_sppd],
                ]);
            }
        });
    }

    /** halaman SPPD dengan tab (1 pengajuan, 2 SPPD saya, 3 review) dan dokumen yang dibuka otomatis */
    /** $proses = true: tautan cepat yang langsung membuka lembar proses (divisi umum/divisi keuangan) pada tab PERLU DIPROSES */
    private static function urlSppd(string $nomorDokumen, int $tab, bool $proses = false, bool $ekstend = false): string
    {
        return 'sppd?dok=' . rawurlencode($nomorDokumen) . ($proses ? '&aksi=proses' : '') . ($proses && $ekstend ? '&jenis=extend' : '') . '#' . $tab;
    }

    /** 'Extend 2' untuk extend bernomor, 'Extend' untuk extend lama */
    private static function labelEkstend($e): string
    {
        return !empty($e->no_urut) ? 'Extend ' . $e->no_urut : 'Extend';
    }

    /** langkah approval extend: extend-<n> (per pengajuan) atau extend (lama) */
    private static function tipeEkstend(string $tipe): bool
    {
        return $tipe === 'extend' || str_starts_with($tipe, 'extend-');
    }

    /** SPPD di-void: pemilik, pengaju dan reviewer yang sedang menunggu diberi tahu beserta alasannya */
    public static function sppdVoid(Sppd $s, string $alasan, ?string $actor): void
    {
        self::aman(function () use ($s, $alasan, $actor) {
            $menunggu = VerifStep::where('id_doc', $s->nomor_dokumen)->whereNull('status')->orderBy('step')->value('id_employe');
            $pesan = [
                'modul' => 'sppd',
                'event' => 'sppd.void',
                'judul' => 'SPPD di-void: ' . $s->nomor_sppd,
                'isi' => 'SPPD ' . self::ringkasSppd($s) . ' ditandai void oleh ' . self::nama($actor) . ". Alasan: {$alasan}",
                'ref' => $s->nomor_dokumen,
            ];
            Hub::kirim($s->submitted_by, $actor, $pesan + ['url' => self::urlSppd($s->nomor_dokumen, 1)]);
            if ($s->employe_id && $s->employe_id !== $s->submitted_by) {
                Hub::kirim($s->employe_id, $actor, $pesan + ['url' => self::urlSppd($s->nomor_dokumen, 2)]);
            }
            if ($menunggu && $menunggu !== $s->employe_id && $menunggu !== $s->submitted_by) {
                Hub::kirim($menunggu, $actor, $pesan + ['url' => 'sppd#3']);
            }
        });
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
                        'url' => self::urlSppd($sppd->nomor_dokumen, 2),
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
            'url' => self::urlSppd($sppd->nomor_dokumen, 2),
            'ref' => $sppd->nomor_dokumen,
        ]);
    }

    private static function ekstendDiajukan(Ekstend $e, bool $diubah = false): void
    {
        // nomor extend diisi trigger database saat insert: baca ulang supaya no_urut terisi
        $e = Ekstend::find($e->id) ?? $e;
        $sppdId = DB::connection('mysql4')->table('tujuan_sppds')->where('id', $e->id_tujuan)->value('id_sppd');
        $sppd = $sppdId ? Sppd::find($sppdId) : null;
        if (!$sppd) {
            return;
        }
        $label = self::labelEkstend($e);
        $tipeLangkah = !empty($e->no_urut) ? 'extend-' . $e->no_urut : 'extend';
        // penyetuju extend ini (langkah pertama yang menunggu); cadangan: penandatangan SPPD
        $penyetuju = VerifStep::where('id_doc', $sppd->nomor_dokumen)->where('type', $tipeLangkah)->whereNull('status')->orderBy('step')->value('id_employe')
            ?: VerifStep::where('id_doc', $sppd->nomor_dokumen)->where('type', 'sign')->value('id_employe');
        $rincian = \App\Services\Sppd\EkstendProses::judul($e) . ' (' . self::tgl($e->start) . ' s.d. ' . self::tgl($e->end) . ')'
            . ($e->perlu_tiket ? ', perlu tiket' : ', tanpa tiket')
            . ((float) ($e->bbm ?? 0) > 0 ? ', uang BBM Rp ' . number_format((float) $e->bbm, 0, ',', '.') : '');
        $isi = 'penambahan hari (' . strtolower($label) . ') untuk SPPD ' . self::ringkasSppd($sppd) . ": {$rincian}. Alasan: {$e->alasan}";
        $pengaju = $e->submitted_by;
        Hub::kirim($penyetuju, $pengaju, [
            'modul' => 'sppd',
            'event' => 'sppd.review',
            'judul' => ($diubah ? 'Mohon review ulang ' : 'Mohon review ') . strtolower($label) . ' SPPD ' . $sppd->nomor_sppd,
            'isi' => 'Mohon review ' . $isi . '. ' . ($diubah ? 'Diubah' : 'Diajukan') . ' oleh ' . self::nama($pengaju) . '.',
            'url' => self::urlSppd($sppd->nomor_dokumen, 3),
            'ref' => $sppd->nomor_dokumen,
        ]);
        // pemilik SPPD (tab SPPD saya) dan admin pengaju (tab pengajuan) tahu pengajuannya tercatat
        $pesan = [
            'modul' => 'sppd',
            'event' => $diubah ? 'sppd.ekstend_diubah' : 'sppd.ekstend_diajukan',
            'judul' => ($diubah ? $label . ' SPPD diubah ' : 'Pengajuan ' . strtolower($label) . ' SPPD ') . $sppd->nomor_sppd,
            'isi' => 'Pengajuan ' . $isi . ($diubah ? '. Persetujuan diulang dari awal.' : '.'),
            'ref' => $sppd->nomor_dokumen,
        ];
        Hub::kirim($sppd->employe_id, $pengaju, $pesan + ['url' => self::urlSppd($sppd->nomor_dokumen, 2)]);
        if ($sppd->submitted_by && $sppd->submitted_by !== $sppd->employe_id) {
            Hub::kirim($sppd->submitted_by, $pengaju, $pesan + ['url' => self::urlSppd($sppd->nomor_dokumen, 1)]);
        }
    }

    /** extend yang masih menunggu persetujuan diedit: penyetuju diminta review ulang */
    public static function sppdExtendDiubah(Ekstend $e): void
    {
        self::aman(fn () => self::ekstendDiajukan($e, true));
    }

    /** extend dibatalkan admin/pengaju: pemilik, pengaju, dan penyetuju yang sedang menunggu diberi tahu beserta alasannya */
    public static function sppdExtendDibatalkan(Sppd $s, Ekstend $e, string $alasan, ?string $actor, array $penyetujuMenunggu = []): void
    {
        self::aman(function () use ($s, $e, $alasan, $actor, $penyetujuMenunggu) {
            $label = self::labelEkstend($e);
            $pesan = [
                'modul' => 'sppd',
                'event' => 'sppd.ekstend_dibatalkan',
                'judul' => $label . ' SPPD dibatalkan ' . $s->nomor_sppd,
                'isi' => self::nama($actor) . ' membatalkan ' . strtolower($label) . ' SPPD ' . self::ringkasSppd($s) . ': ' . \App\Services\Sppd\EkstendProses::judul($e) . '. Alasan: ' . $alasan,
                'ref' => $s->nomor_dokumen,
            ];
            Hub::kirim($s->employe_id, $actor, $pesan + ['url' => self::urlSppd($s->nomor_dokumen, 2)]);
            if ($s->submitted_by && $s->submitted_by !== $s->employe_id) {
                Hub::kirim($s->submitted_by, $actor, $pesan + ['url' => self::urlSppd($s->nomor_dokumen, 1)]);
            }
            if ($penyetujuMenunggu) {
                Hub::kirim($penyetujuMenunggu, $actor, $pesan + ['url' => self::urlSppd($s->nomor_dokumen, 3)]);
            }
        });
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
                    'url' => 'asset?servis=' . $servis->id . '#request', 'ref' => (string) $servis->id,
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
                'url' => 'asset#request', 'ref' => (string) $servis->id,
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
            'url' => 'mobil-operational?permintaan=' . $p->id . '#2',
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
            'url' => 'mobil-operational?permintaan=' . $p->id . '#2',
            'ref' => (string) $p->id,
        ]);
    }
}
