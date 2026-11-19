<?php

namespace App\Services\Sppd;

use App\Models\Sppd\Ekstend;
use App\Models\Sppd\HitunganBiaya;
use App\Models\Sppd\ProsesTiket;
use App\Models\Sppd\ProsesTermin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extend SPPD (penambahan hari pada tujuan yang ada, atau tujuan baru) yang sudah disetujui masuk proses sendiri:
 *   divisi umum (tiket, bila perlu_tiket) lalu divisi keuangan (uang muka per termin).
 * Statusnya disimpan di tabel ekstends (by_umum, uangmuka); tiket dan termin di proses_tiket / proses_termin dengan id_ekstend.
 * Uang muka dihitung dengan aturan yang sama (UangMuka::rincian) memakai baris biaya sintetis yang meniru view hitungan_biaya.
 */
class EkstendProses
{
    /** kolom dari db/2026-11-18/ekstend_proses.sql sudah ada; bila belum, fitur ini mati dan alur SPPD awal tidak terpengaruh */
    public static function aktif(): bool
    {
        return Cache::remember('ekstends_has_butuh_proses', 300, fn () => Schema::connection('mysql4')->hasColumn('ekstends', 'butuh_proses'));
    }

    /** kolom ekstends.no_urut (db/2026-11-19/ekstend_per_pengajuan.sql): approval per extend bertipe extend-<no_urut>; bila belum ada, alur lama berlaku */
    public static function adaNoUrut(): bool
    {
        // hanya hasil 'ada' yang disimpan; selama kolom belum ada, dicek ulang tiap permintaan agar langsung aktif setelah script dijalankan
        if (Cache::get('ekstends_has_no_urut') === true) {
            return true;
        }
        $ada = Schema::connection('mysql4')->hasColumn('ekstends', 'no_urut');
        if ($ada) {
            Cache::put('ekstends_has_no_urut', true, 300);
        }

        return $ada;
    }

    /** kolom ekstends.bbm (db/2026-11-19/ekstend_bbm.sql); bila belum ada, BBM extend diabaikan */
    public static function adaBbm(): bool
    {
        if (Cache::get('ekstends_has_bbm') === true) {
            return true;
        }
        $ada = Schema::connection('mysql4')->hasColumn('ekstends', 'bbm');
        if ($ada) {
            Cache::put('ekstends_has_bbm', true, 300);
        }

        return $ada;
    }

    /** extend yang menghasilkan uang muka dan seluruh persetujuannya sudah selesai */
    public static function disetujui(?int $idSppd = null): Collection
    {
        if (!self::aktif()) {
            return collect();
        }
        $q = Ekstend::where('butuh_proses', 1)->whereNotNull('disetujui_at');
        // extend yang dibatalkan tidak boleh muncul lagi di daftar Perlu Diproses (umum maupun keuangan)
        if (self::adaNoUrut()) {
            $q->whereNull('dibatalkan_at');
        }
        if ($idSppd !== null) {
            $q->whereIn('id_tujuan', DB::connection('mysql4')->table('tujuan_sppds')->where('id_sppd', $idSppd)->pluck('id'));
        }

        return $q->orderBy('id')->get();
    }

    /** id SPPD dari sebuah extend (lewat tujuan acuannya) */
    public static function idSppd(Ekstend $e): ?int
    {
        $v = DB::connection('mysql4')->table('tujuan_sppds')->where('id', $e->id_tujuan)->value('id_sppd');

        return $v ? (int) $v : null;
    }

    public static function judul(Ekstend $e, ?string $namaTujuanAcuan = null): string
    {
        $tgl = fn ($d) => date('d M Y', strtotime((string) $d));
        $nama = $e->tipe === 'tujuan_baru' ? ($e->detail_tujuan ?: 'Tujuan baru') : ($namaTujuanAcuan ?? DB::connection('mysql4')->table('tujuan_sppds')->where('id', $e->id_tujuan)->value('detail_tujuan'));

        return ($e->tipe === 'tujuan_baru' ? 'Tujuan baru: ' : 'Tambah hari: ') . $nama . ' (' . $tgl($e->start) . ' - ' . $tgl($e->end) . ')';
    }

    /** baris biaya sintetis (bentuk sama dengan satu baris view hitungan_biaya) untuk sebuah extend */
    public static function baris(Ekstend $e): ?object
    {
        $db = DB::connection('mysql4');
        $acuan = HitunganBiaya::where('id', $e->id_tujuan)->first();
        if (!$acuan) {
            return null;
        }
        $sppd = $db->table('sppds')->where('id', $acuan->id_sppd)->first(['ketetapan', 'golongan_rate']);
        if (!$sppd) {
            return null;
        }
        $kategori = $e->tipe === 'tujuan_baru' && $e->id_kategori ? (int) $e->id_kategori : (int) $acuan->id_kategori;
        $namaKategori = $e->tipe === 'tujuan_baru' && $e->id_kategori ? $db->table('categori_sppds')->where('id', $kategori)->value('categori_sppd') : $acuan->categori_sppd;

        $mb = $db->table('master_biaya')->where('ketetapan', $sppd->ketetapan)->where('id_category', $kategori)->where('id', $sppd->golongan_rate)->first();
        $berangkat = date('Y-m-d', strtotime((string) $e->start)) . ' 00:01:00';
        $kembali = date('Y-m-d', strtotime((string) $e->end)) . ' 23:59:00';
        $waktu = fn (string $jam, string $kolom) => $db->table('master_times')->where('ketetapan', $sppd->ketetapan)->where('start', '<=', $jam)->where('end', '>=', $jam)->value($kolom);
        $rateWb = $waktu('00:01:00', 'rate_pergi');
        $rateWt = $waktu('23:59:00', 'rate_pulang');

        $selisih = (int) floor((strtotime(substr($kembali, 0, 10)) - strtotime(substr($berangkat, 0, 10))) / 86400);
        $hariHitung = $selisih - 1 + (float) $rateWb + (float) $rateWt;
        $p = fn ($v) => $v === null ? 100.0 : (float) $v;
        $share = $acuan->share_with;
        $rate = fn ($k) => $mb ? $mb->{$k} : null;

        $t = clone $acuan;
        $t->id_kategori = $kategori;
        $t->categori_sppd = $namaKategori;
        if ($e->tipe === 'tujuan_baru') {
            $t->detail_tujuan = $e->detail_tujuan;
            $t->moda = $e->moda ?: $acuan->moda;
        }
        $t->tugas = $e->alasan;
        $t->waktu_berangkat = $berangkat;
        $t->waktu_kembali = $kembali;
        $t->jumlah_hari = $selisih + 1;
        $t->rate_wb = $rateWb;
        $t->rate_wt = $rateWt;
        $t->rate_hitung_hari = $hariHitung;
        $t->rate_um = $rate('um');
        $t->rate_tr = $rate('tr');
        $t->rate_us = $rate('us');
        $t->rate_tiket = $rate('tiket');
        $t->rate_hotel = $share === null ? $rate('hotel') : 0;
        $t->fix_um = $rate('um') === null ? null : $rate('um') * $hariHitung * $p($acuan->p_um) / 100;
        $t->fix_tl = $rate('tr') === null ? null : $rate('tr') * $hariHitung * $p($acuan->p_tl) / 100;
        $t->fix_us = $rate('us') === null ? null : $rate('us') * $hariHitung * $p($acuan->p_us) / 100;
        $t->fix_tiket = 0;
        $t->fix_hotel = $share === null && $mb ? $mb->hotel * $selisih * $p($acuan->p_hotel) / 100 : 0;
        $t->bbm = self::adaBbm() ? (float) ($e->bbm ?? 0) : 0;
        $t->total_biaya = round((float) $t->fix_um + (float) $t->fix_tl + (float) $t->fix_us + (float) $t->fix_hotel + (float) $t->bbm, 2);

        return $t;
    }

    public static function tiketTotal(int $idEkstend): float
    {
        return (float) ProsesTiket::withoutGlobalScopes()->where('id_ekstend', $idEkstend)->sum('harga');
    }

    /** rincian uang muka (komponen, termin, total) untuk satu extend; termin diberi id_ekstend dan bukti transfernya */
    public static function rincian(Ekstend $e): ?array
    {
        $t = self::baris($e);
        if (!$t) {
            return null;
        }
        $r = UangMuka::rincian($t, self::tiketTotal((int) $e->id));
        $bukti = ProsesTermin::withoutGlobalScopes()->where('id_ekstend', $e->id)->get()->keyBy('termin');
        foreach ($r['termin'] as &$tm) {
            $tm['id_tujuan'] = (int) $e->id_tujuan;
            $tm['id_ekstend'] = (int) $e->id;
            $b = $bukti->get($tm['no']);
            $tm['bukti'] = $b ? ['file' => $b->file, 'tgl_upload' => (string) $b->created_at, 'oleh' => \App\Models\Employe::where('employe_id', $b->diproses_oleh)->value('first_name')] : null;
        }
        unset($tm);
        $r['total_biaya'] = (float) $t->total_biaya;

        return $r;
    }
}
