<?php

// Kondisi lalu lintas Banda Aceh dan sekitarnya untuk Dashboard TV (TomTom Traffic Flow, paket gratis cukup untuk interval cache di bawah).
// Isi TOMTOM_API_KEY di .env. Titik di bawah cukup dekat ke ruas jalannya; TomTom mengambil ruas jalan terdekat, jadi koordinat bisa disesuaikan.
return [
    'tomtom_key' => env('TOMTOM_API_KEY'),

    // lama simpan hasil (detik): membatasi jumlah permintaan ke TomTom walaupun banyak TV membuka dashboard
    'cache_seconds' => 300,

    // rasio kecepatan sekarang terhadap kecepatan lancar: di atas "lengang" = Lengang, di atas "ramai" = Ramai, selain itu Macet
    'batas' => ['lengang' => 0.75, 'ramai' => 0.5],

    // pusat: Kantor PT Pembangunan Aceh (Perseroda)
    'pusat' => ['nama' => 'Kantor PT Pembangunan Aceh', 'lat' => 5.5561209, 'lon' => 95.3234527],

    'titik' => [
        ['nama' => 'Kantor Pembangunan Aceh', 'lat' => 5.5561209, 'lon' => 95.3234527],
        ['nama' => 'Peunayong', 'lat' => 5.5575, 'lon' => 95.3275],
        ['nama' => 'Simpang Lima', 'lat' => 5.5489, 'lon' => 95.3210],
        ['nama' => 'Masjid Raya', 'lat' => 5.5537, 'lon' => 95.3175],
        ['nama' => 'Beurawe', 'lat' => 5.5605, 'lon' => 95.3315],
        ['nama' => 'Darussalam', 'lat' => 5.5690, 'lon' => 95.3680],
        ['nama' => 'Lambaro', 'lat' => 5.5830, 'lon' => 95.3970],
        ['nama' => 'Ulee Lheue', 'lat' => 5.5700, 'lon' => 95.2950],
    ],
];
