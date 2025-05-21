<?php

use Illuminate\Support\Carbon;

function formatTanggalRangeIndo($startDate, $endDate): string
{
    $start = Carbon::parse($startDate);
    $end = Carbon::parse($endDate);

    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
        5 => 'Mei', 6 => 'Jun', 7 => 'Jul', 8 => 'Agu',
        9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];

    $tanggalMulai = $start->day;
    $bulanMulai = $bulan[$start->month];
    $tahunMulai = $start->year;

    $tanggalAkhir = $end->day;
    $bulanAkhir = $bulan[$end->month];
    $tahunAkhir = $end->year;

    if ($start->toDateString() === $end->toDateString()) {
        // Kasus: 18 Mei 2025
        return "$tanggalMulai $bulanMulai $tahunMulai";
    }

    if ($tahunMulai === $tahunAkhir && $start->month === $end->month) {
        // Kasus: 18 - 20 Mei 2025
        return "$tanggalMulai - $tanggalAkhir $bulanMulai $tahunMulai";
    }

    if ($tahunMulai === $tahunAkhir && $start->month !== $end->month) {
        // Kasus: 18 Mei - 12 Jun 2025
        return "$tanggalMulai $bulanMulai - $tanggalAkhir $bulanAkhir $tahunMulai";
    }

    // Kasus: 18 Mei 2025 - 10 Jan 2026
    return "$tanggalMulai $bulanMulai $tahunMulai - $tanggalAkhir $bulanAkhir $tahunAkhir";
}
