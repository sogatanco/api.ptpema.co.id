<?php

return [
    // SPPD yang dibuat pada/sesudah tanggal ini: divisi umum (tiket) dan divisi keuangan (uang muka) bekerja bersamaan begitu SPPD disetujui.
    // SPPD yang dibuat sebelumnya tetap memakai aturan lama (divisi keuangan menerima setelah divisi umum selesai atau bila tanpa tiket),
    // supaya data lama tidak ikut membanjiri daftar divisi keuangan.
    'proses_paralel_mulai' => env('SPPD_PROSES_PARALEL_MULAI', '2026-11-18'),

    // batas total hari extend per SPPD (jumlah semua extend yang masih aktif: menunggu atau disetujui; extend dibatalkan/ditolak tidak dihitung)
    'maks_hari_extend' => (int) env('SPPD_MAKS_HARI_EXTEND', 5),
];
