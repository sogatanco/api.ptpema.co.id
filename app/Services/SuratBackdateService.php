<?php

namespace App\Services;

use App\Models\Adm\Surat;
use App\Models\Adm\SuratVoid;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Penomoran surat bertanggal mundur (backdate).
 *
 * Nomor surat berurutan sepanjang tahun (reset tiap Januari). Surat bertanggal D menempati rentang nomor di antara surat terakhir sebelum D dan
 * surat pertama sesudah D. Aturannya sama dengan yang selama ini dikerjakan manual:
 *   1. bila ada nomor kosong di rentang itu, pakai nomor kosong terkecil (mis. 107);
 *   2. bila tidak ada, sisipkan dengan huruf di belakang nomor terakhir pada atau sebelum D (mis. 107-A, lalu 107-B).
 * Surat bertanggal mundur diajukan admin, lalu PICAdm memilih salah satu saran nomor di bawah; nomor hari ini (last_number) tidak disentuh.
 */
class SuratBackdateService
{
    /**
     * Surat pada deret penomoran berjalan: [['n' => nomor, 'huruf' => 'A'|'', 'tgl' => 'Y-m-d'], ...].
     * Penomoran direset ke 01 pada awal tahun, tetapi tidak selalu tepat 1 Januari (mis. 825/I/2026 masih ada sebelum 01/I/2026), maka deret
     * dimulai dari surat bernomor 01 terakhir pada tahun ini; bila belum ada, semua surat dengan nomor bertahun ini.
     */
    private static function rows(int $year): array
    {
        $semua = Surat::where('nomor_surat', 'like', '%/' . $year)->orderBy('id')->get(['id', 'nomor_surat', 'created_at']);
        $parsed = [];
        $awal = null;
        // surat void tidak dihitung: nomornya kosong kembali dan bisa dipilih untuk backdate
        $void = array_flip(SuratVoid::ids());
        foreach ($semua as $s) {
            if (isset($void[(int) $s->id])) {
                continue;
            }
            if (!preg_match('/^(\d+)(?:-([A-Z]+))?\//', (string) $s->nomor_surat, $m)) {
                continue;
            }
            $r = ['n' => (int) $m[1], 'huruf' => $m[2] ?? '', 'tgl' => substr((string) $s->created_at, 0, 10)];
            if ($r['n'] === 1 && $r['huruf'] === '') {
                $awal = count($parsed);
            }
            $parsed[] = $r;
        }

        return $awal === null ? $parsed : array_slice($parsed, $awal);
    }

    private static function nextHuruf(string $h): string
    {
        if ($h === '') {
            return 'A';
        }
        $i = strlen($h) - 1;
        while ($i >= 0) {
            if ($h[$i] !== 'Z') {
                $h[$i] = chr(ord($h[$i]) + 1);

                return $h;
            }
            $h[$i] = 'A';
            $i--;
        }

        return 'A' . $h;
    }

    public static function validasiTanggal(string $tanggal): Carbon
    {
        try {
            $d = Carbon::createFromFormat('Y-m-d', $tanggal)->startOfDay();
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Format tanggal harus YYYY-MM-DD.');
        }
        if (!$d->lt(Carbon::today())) {
            throw new InvalidArgumentException('Tanggal backdate harus sebelum hari ini.');
        }
        if ($d->year !== Carbon::today()->year) {
            throw new InvalidArgumentException('Backdate hanya untuk tahun berjalan (penomoran surat direset tiap tahun).');
        }
        $deret = self::rows($d->year);
        if ($deret && $d->toDateString() < min(array_column($deret, 'tgl'))) {
            throw new InvalidArgumentException('Tanggal itu sebelum surat pertama pada deret penomoran tahun ini (' . min(array_column($deret, 'tgl')) . ').');
        }

        return $d;
    }

    /**
     * Saran nomor untuk tanggal $d: semua nomor kosong pada rentang tanggal itu (terkecil dulu), lalu satu opsi sisipan huruf di belakang
     * nomor terakhir pada atau sebelum tanggal itu (mis. 107-A, lalu 107-B).
     *
     * @return list<array{nomor: string, mode: string, keterangan: string}> nomor tanpa "/KODE/BULAN/TAHUN"
     */
    public static function saran(Carbon $d): array
    {
        $rows = self::rows($d->year);
        $tgl = $d->toDateString();
        $out = [];

        $sebelum = array_column(array_filter($rows, fn ($r) => $r['tgl'] < $tgl), 'n');
        $sesudah = array_column(array_filter($rows, fn ($r) => $r['tgl'] > $tgl), 'n');
        $lo = $sebelum ? max($sebelum) : 0;
        $dipakai = array_flip(array_column(array_filter($rows, fn ($r) => $r['huruf'] === ''), 'n'));
        // tanpa surat sesudah D, nomor kosong hanya dicari sampai nomor terbesar yang ada
        $batas = $sesudah ? min($sesudah) : ($rows ? max(array_column($rows, 'n')) + 1 : $lo + 1);
        for ($n = $lo + 1; $n < $batas; $n++) {
            if (!isset($dipakai[$n])) {
                $out[] = ['nomor' => sprintf('%02d', $n), 'mode' => 'kosong', 'keterangan' => 'Nomor kosong pada rentang tanggal tersebut'];
            }
        }

        $jangkar = array_column(array_filter($rows, fn ($r) => $r['tgl'] <= $tgl), 'n');
        if ($jangkar) {
            $a = max($jangkar);
            $hurufAda = array_column(array_filter($rows, fn ($r) => $r['n'] === $a && $r['huruf'] !== ''), 'huruf');
            $huruf = 'A';
            if ($hurufAda) {
                usort($hurufAda, fn ($x, $y) => strlen($x) <=> strlen($y) ?: strcmp($x, $y));
                $huruf = self::nextHuruf(end($hurufAda));
            }
            $out[] = ['nomor' => sprintf('%02d', $a) . '-' . $huruf, 'mode' => 'sisip', 'keterangan' => "Disisipkan setelah nomor $a"];
        }

        if (!$out) {
            throw new InvalidArgumentException('Tidak ada saran nomor untuk tanggal itu.');
        }

        return $out;
    }
}
