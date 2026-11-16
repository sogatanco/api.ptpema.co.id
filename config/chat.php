<?php

return [
    /*
    | Golongan jabatan (id_base) yang TIDAK dimasukkan ke grup chat sebuah divisi: [organization_id => [id_base, ...]].
    | Divisi Umum (20): Tenaga Pendukung (10 = supir, satpam, petugas kebersihan) tetap bisa chat pribadi dan tampil di daftar user,
    | tetapi tidak masuk grup Divisi Umum. Ubah CHAT_GRUP_UMUM_ID_BASE di .env (pisahkan dengan koma), lalu php artisan config:clear.
    */
    'grup_tanpa_golongan' => [
        20 => array_values(array_filter(array_map('intval', explode(',', (string) env('CHAT_GRUP_UMUM_ID_BASE', '10'))))),
    ],

    // grup hanya dibuat bila anggotanya minimal sekian orang
    'grup_minimal_anggota' => 2,
];
