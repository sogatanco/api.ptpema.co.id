<?php

namespace App\Services\Sppd;

/**
 * Perhitungan uang muka SPPD (dipakai detail SPPD, lembar proses KeuanganSppd, dan pengingat termin).
 *  - uang makan 100%, transportasi lokal 100%, hotel 100%, BBM 100% dari perkiraan biaya SPPD; komponen lain (uang saku) 0%
 *  - hotel dihitung per MALAM = jumlah hari - 1 (4 hari = 3 malam), sudah demikian di view hitungan_biaya
 *  - sampai 4 hari dibayar sekaligus; lebih dari 4 hari per termin 4 hari (BBM di termin pertama)
 *  - tiket (aktual, dibayar ke vendor) ditampilkan terpisah dan tidak termasuk uang muka tunai
 */
class UangMuka
{
    /** persentase uang muka per komponen dari perkiraan biaya SPPD (nilai fix_* sudah memuat porsi Pema/sponsor) */
    public const PERSEN = ['uang_makan' => 100, 'uang_saku' => 0, 'transport_lokal' => 100, 'hotel' => 100, 'bbm' => 100];

    /** satu termin dibayar untuk 4 hari perjalanan */
    public const HARI_TERMIN = 4;

    /** hasil per ketetapan selama satu permintaan */
    private static array $cacheTermin = [];

    /**
     * Apakah uang muka SPPD ini bertermin: pengaturan ketetapan_sppds.termin_aktif milik ketetapan SPPD-nya
     * (Setting > Biaya & Ketetapan SPPD). Tanpa kolomnya atau tanpa data, bawaannya bertermin.
     */
    public static function terminAktif($t): bool
    {
        $idSppd = (int) ($t->id_sppd ?? 0);
        if (!$idSppd) {
            return true;
        }
        $db = \Illuminate\Support\Facades\DB::connection('mysql4');
        $ket = $db->table('sppds')->where('id', $idSppd)->value('ketetapan');
        if (!$ket) {
            return true;
        }
        if (!array_key_exists($ket, self::$cacheTermin)) {
            $ada = \Illuminate\Support\Facades\Cache::remember('ketetapan_has_termin_aktif', 300, fn () => \Illuminate\Support\Facades\Schema::connection('mysql4')->hasColumn('ketetapan_sppds', 'termin_aktif'));
            self::$cacheTermin[$ket] = !$ada || (int) ($db->table('ketetapan_sppds')->where('id', $ket)->value('termin_aktif') ?? 1) === 1;
        }

        return self::$cacheTermin[$ket];
    }

    /** kosongkan cache (dipakai setelah pengaturan ketetapan diubah) */
    public static function lupakanCacheTermin(): void
    {
        self::$cacheTermin = [];
    }

    /**
     * Pilihan hotel dan transportasi lokal sebuah tujuan (tujuan_sppds.hotel_oleh / transport_lokal; skrip db/2026-11-26).
     * Hotel dipesan admin dan transportasi lokal via vendor tidak masuk uang muka. Tanpa kolom/data: hotel sendiri, transport mandiri.
     * Extend meminjam baris tujuan asalnya (id sama), jadi mengikuti pilihan tujuan itu.
     *
     * @return array{hotel_admin:bool,hotel_tidak:bool,transport_vendor:bool}
     */
    public static function pilihan($t): array
    {
        static $cache = [];
        $id = (int) ($t->id ?? 0);
        if (!$id) {
            return ['hotel_admin' => false, 'hotel_tidak' => false, 'transport_vendor' => false];
        }
        if (!isset($cache[$id])) {
            $db = \Illuminate\Support\Facades\DB::connection('mysql4');
            $ada = \Illuminate\Support\Facades\Cache::remember('tujuan_sppds_has_hotel_transport', 300, fn () => \Illuminate\Support\Facades\Schema::connection('mysql4')->hasColumn('tujuan_sppds', 'hotel_oleh')
                && \Illuminate\Support\Facades\Schema::connection('mysql4')->hasColumn('tujuan_sppds', 'transport_lokal'));
            $row = $ada ? $db->table('tujuan_sppds')->where('id', $id)->first(['hotel_oleh', 'transport_lokal']) : null;
            $cache[$id] = ['hotel_admin' => in_array($row->hotel_oleh ?? 'sendiri', ['admin', 'tidak'], true), 'hotel_tidak' => ($row->hotel_oleh ?? 'sendiri') === 'tidak', 'transport_vendor' => ($row->transport_lokal ?? 'mandiri') === 'vendor'];
        }

        return $cache[$id];
    }

    /**
     * @param object $t baris view hitungan_biaya (satu tujuan)
     * @return array{hari:int,malam:int,komponen:array,tiket:array,total:float,termin:array}
     */
    public static function rincian($t, float $tiket = 0): array
    {
        $p = self::PERSEN;
        $pilihan = self::pilihan($t);
        if ($pilihan['hotel_admin']) {
            $p['hotel'] = 0; // hotel dipesan admin / tidak menginap: tidak termasuk uang muka
        }
        if ($pilihan['transport_vendor']) {
            $p['transport_lokal'] = 0; // transportasi lokal via vendor: tidak termasuk uang muka
        }
        $hari = (int) $t->jumlah_hari;
        // malam tambahan (extend yang bersambung dengan pengajuan sebelumnya) ikut biaya hotel; SPPD biasa tidak punya
        $malamTambahan = (int) ($t->malam_tambahan ?? 0);
        $malam = max($hari - 1, 0) + $malamTambahan;

        $komponen = [
            ['kode' => 'uang_makan', 'nama' => 'Uang makan', 'dasar' => (float) $t->fix_um, 'rate' => (float) $t->rate_um, 'porsi' => (float) $t->p_um, 'qty' => (float) $t->rate_hitung_hari, 'satuan' => 'hari'],
            ['kode' => 'uang_saku', 'nama' => 'Uang saku', 'dasar' => (float) $t->fix_us, 'rate' => (float) $t->rate_us, 'porsi' => (float) $t->p_us, 'qty' => (float) $t->rate_hitung_hari, 'satuan' => 'hari'],
            ['kode' => 'transport_lokal', 'nama' => 'Transportasi lokal', 'dasar' => (float) $t->fix_tl, 'rate' => (float) $t->rate_tr, 'porsi' => (float) $t->p_tl, 'qty' => (float) $t->rate_hitung_hari, 'satuan' => 'hari'],
            ['kode' => 'hotel', 'nama' => 'Hotel', 'dasar' => (float) $t->fix_hotel, 'rate' => (float) $t->rate_hotel, 'porsi' => (float) $t->p_hotel, 'qty' => (float) $malam, 'satuan' => 'malam'],
            ['kode' => 'bbm', 'nama' => 'BBM', 'dasar' => (float) $t->bbm, 'rate' => null, 'porsi' => 100.0, 'qty' => 1.0, 'satuan' => 'paket'],
        ];
        $total = 0.0;
        foreach ($komponen as &$k) {
            $k['persen'] = $p[$k['kode']];
            $k['jumlah'] = round($k['dasar'] * $k['persen'] / 100, 2);
            $total += $k['jumlah'];
        }
        unset($k);
        $komponen[3]['catatan'] = $malam . ' malam' . ($pilihan['hotel_tidak'] ? ' (tidak menginap)' : ($pilihan['hotel_admin'] ? ' (dipesan admin, tidak masuk uang muka)' : ''));
        if ($pilihan['transport_vendor']) {
            $komponen[2]['catatan'] = 'Vendor, tidak masuk uang muka';
        }
        $total = round($total, 2);
        // hotel dipesan admin (bukan "tidak menginap"): biayanya dibayar terpisah ke admin, tidak masuk total uang muka
        $hotelAdminTerpisah = $pilihan['hotel_admin'] && !$pilihan['hotel_tidak'];

        $tgl = fn (int $tambahHari) => date('Y-m-d', strtotime($t->waktu_berangkat . ' + ' . $tambahHari . ' days'));
        $terminAktif = self::terminAktif($t);
        // tanpa termin: seluruh uang muka dibayar sekali (satu pembayaran, tetap dicatat sebagai pembayaran ke-1)
        $hr = $terminAktif ? self::HARI_TERMIN : PHP_INT_MAX;
        if ($hari <= $hr) {
            $termin = [[
                'id' => 0, 'no' => 1, 'tgl_bayar' => date('Y-m-d', strtotime($t->waktu_berangkat)), 'jumlah' => $total, 'keterangan' => 'Hari 1' . ($hari > 1 ? '-' . $hari : ''),
                // rincian untuk dokumen keuangan (tabel standar biaya) per termin
                'mulai' => date('Y-m-d', strtotime($t->waktu_berangkat)), 'selesai' => date('Y-m-d', strtotime($t->waktu_kembali)), 'malam' => $malam,
                'mulai_hotel' => date('Y-m-d', strtotime($t->waktu_berangkat) - $malamTambahan * 86400),
                // hotel dipesan admin: biayanya (tidak masuk uang muka) dibayarkan ke rekening admin, dicatat per termin untuk voucher
                'hotel_admin' => $hotelAdminTerpisah ? round($komponen[3]['dasar'], 2) : 0,
                'komponen' => array_map(fn ($k) => ['kode' => $k['kode'], 'nama' => $k['nama'], 'rate' => $k['rate'], 'qty' => $k['qty'], 'satuan' => $k['satuan'], 'porsi' => $k['porsi'], 'persen' => $k['persen'], 'jumlah' => $k['jumlah']], $komponen),
            ]];
        } else {
            $termin = [];
            $jumlahTermin = (int) ceil($hari / $hr);
            $terbayar = 0.0;
            for ($k = 0; $k < $jumlahTermin; $k++) {
                $awal = $k * $hr + 1;
                $akhir = min(($k + 1) * $hr, $hari);
                // bobot hari: hari pertama = rate waktu berangkat, hari terakhir = rate waktu tiba, hari lain penuh
                $bobot = 0.0;
                for ($d = $awal; $d <= $akhir; $d++) {
                    $bobot += $d === 1 ? (float) $t->rate_wb : ($d === $hari ? (float) $t->rate_wt : 1.0);
                }
                // hotel per termin: malam sebelum tiap hari termin kecuali hari pertama perjalanan (tidak ada malam sebelumnya),
                // jadi termin 4 hari = 3 malam (termin pertama) dan jumlah malam seluruh termin = hari - 1; uang makan/transport tetap dihitung per hari
                $malamTermin = $akhir - $awal + 1 - ($awal === 1 ? 1 : 0) + ($k === 0 ? $malamTambahan : 0);
                if ($k === $jumlahTermin - 1) {
                    $jumlah = round($total - $terbayar, 2); // sisa, agar jumlah semua termin sama dengan total
                } else {
                    $jumlah = round(
                        $bobot * ((float) $t->rate_um * $t->p_um / 100 * $p['uang_makan'] / 100
                            + (float) $t->rate_us * $t->p_us / 100 * $p['uang_saku'] / 100
                            + (float) $t->rate_tr * $t->p_tl / 100 * $p['transport_lokal'] / 100)
                        + $malamTermin * (float) $t->rate_hotel * $t->p_hotel / 100 * $p['hotel'] / 100
                        + ($k === 0 ? (float) $t->bbm * $p['bbm'] / 100 : 0),
                        2
                    );
                }
                $terbayar += $jumlah;
                // rincian komponen termin ini: hari berbobot (uang makan/saku/transport), malam (hotel), BBM hanya di termin pertama
                $baris = [
                    ['kode' => 'uang_makan', 'nama' => 'Uang makan', 'rate' => (float) $t->rate_um, 'qty' => $bobot, 'satuan' => 'hari', 'porsi' => (float) $t->p_um, 'persen' => $p['uang_makan']],
                    ['kode' => 'uang_saku', 'nama' => 'Uang saku', 'rate' => (float) $t->rate_us, 'qty' => $bobot, 'satuan' => 'hari', 'porsi' => (float) $t->p_us, 'persen' => $p['uang_saku']],
                    ['kode' => 'transport_lokal', 'nama' => 'Transportasi lokal', 'rate' => (float) $t->rate_tr, 'qty' => $bobot, 'satuan' => 'hari', 'porsi' => (float) $t->p_tl, 'persen' => $p['transport_lokal']],
                    ['kode' => 'hotel', 'nama' => 'Hotel', 'rate' => (float) $t->rate_hotel, 'qty' => (float) $malamTermin, 'satuan' => 'malam', 'porsi' => (float) $t->p_hotel, 'persen' => $p['hotel']],
                ];
                if ($k === 0) {
                    $baris[] = ['kode' => 'bbm', 'nama' => 'BBM', 'rate' => null, 'qty' => 1.0, 'satuan' => 'paket', 'porsi' => 100.0, 'persen' => $p['bbm'], 'dasar' => (float) $t->bbm];
                }
                foreach ($baris as &$r) {
                    $dasar = $r['dasar'] ?? ($r['rate'] * $r['qty'] * $r['porsi'] / 100);
                    $r['jumlah'] = round($dasar * $r['persen'] / 100, 2);
                    unset($r['dasar']);
                }
                unset($r);
                $termin[] = [
                    'id' => $k, 'no' => $k + 1, 'tgl_bayar' => $tgl($k * $hr), 'jumlah' => $jumlah, 'keterangan' => 'Hari ' . $awal . ($akhir > $awal ? '-' . $akhir : ''),
                    'mulai' => $tgl($awal - 1), 'selesai' => $tgl($akhir - 1), 'malam' => $malamTermin, 'komponen' => $baris,
                    // malam pertama hotel termin ini: malam hari sebelum termin (termin 1: malam hari pertama, dimajukan bila ada malam penyambung)
                    'mulai_hotel' => $k === 0 ? date('Y-m-d', strtotime($t->waktu_berangkat) - $malamTambahan * 86400) : $tgl($awal - 2),
                    'hotel_admin' => $hotelAdminTerpisah ? round($malamTermin * (float) $t->rate_hotel * (float) $t->p_hotel / 100, 2) : 0,
                ];
            }
        }

        return [
            'hari' => $hari,
            'malam' => $malam,
            // kategori SPPD dan dasar rate (rate harian x bobot hari: hari berangkat/tiba dihitung sebagian)
            'kategori' => [
                'kategori' => $t->categori_sppd,
                'jenis' => $t->jenis_sppd,
                'moda' => $t->moda,
                'sumber_biaya' => $t->sumber_biaya,
                'renbis' => $t->renbis,
                'kegiatan_bisnis' => $t->k_bisnis,
                'rate_hari' => (float) $t->rate_hitung_hari,
                'rate_berangkat' => (float) $t->rate_wb,
                'rate_tiba' => (float) $t->rate_wt,
                'waktu_berangkat' => $t->waktu_berangkat,
                'waktu_kembali' => $t->waktu_kembali,
            ],
            'komponen' => $komponen,
            'tiket' => ['jumlah' => round($tiket, 2), 'ada' => $tiket > 0],
            'total' => $total,
            'hotel_admin' => round(array_sum(array_column($termin, 'hotel_admin')), 2),
            'termin_aktif' => $terminAktif,
            'termin' => $termin,
        ];
    }
}
