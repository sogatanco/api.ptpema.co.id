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

    /**
     * @param object $t baris view hitungan_biaya (satu tujuan)
     * @return array{hari:int,malam:int,komponen:array,tiket:array,total:float,termin:array}
     */
    public static function rincian($t, float $tiket = 0): array
    {
        $p = self::PERSEN;
        $hari = (int) $t->jumlah_hari;
        $malam = max($hari - 1, 0);

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
        $komponen[3]['catatan'] = $malam . ' malam';
        $total = round($total, 2);

        $tgl = fn (int $tambahHari) => date('Y-m-d', strtotime($t->waktu_berangkat . ' + ' . $tambahHari . ' days'));
        $hr = self::HARI_TERMIN;
        if ($hari <= $hr) {
            $termin = [['id' => 0, 'no' => 1, 'tgl_bayar' => date('Y-m-d', strtotime($t->waktu_berangkat)), 'jumlah' => $total, 'keterangan' => 'Hari 1' . ($hari > 1 ? '-' . $hari : '')]];
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
                $malamTermin = $akhir - $awal + 1 - ($akhir === $hari ? 1 : 0);
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
                $termin[] = ['id' => $k, 'no' => $k + 1, 'tgl_bayar' => $tgl($k * $hr), 'jumlah' => $jumlah, 'keterangan' => 'Hari ' . $awal . ($akhir > $awal ? '-' . $akhir : '')];
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
            'termin' => $termin,
        ];
    }
}
