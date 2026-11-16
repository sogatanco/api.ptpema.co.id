<?php

return [
    /*
    | Golongan jabatan (id_base) yang TIDAK memakai fitur pesan.
    | 10 = Tenaga Pendukung (supir, satpam, petugas kebersihan di Divisi Umum). Pisahkan dengan koma bila lebih dari satu.
    | Ubah lewat CHAT_ID_BASE_DIKECUALIKAN di .env, lalu php artisan config:clear.
    */
    'id_base_dikecualikan' => array_values(array_filter(array_map('intval', explode(',', (string) env('CHAT_ID_BASE_DIKECUALIKAN', '10'))))),
];
