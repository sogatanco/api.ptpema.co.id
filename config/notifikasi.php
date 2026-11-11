<?php

return [
    // false: notifikasi modul ADM, SPPD, Mobil Operasional dan Asset masih dihasilkan trigger lama di ptpemaco_apam.notifications.
    // Ubah menjadi true bersamaan dengan menjalankan db/notif/02_hapus_trigger_notifikasi.sql supaya tidak ganda.
    'aktif' => env('NOTIF_BARU_AKTIF', false),

    // true: notifikasi lama modul ADM, SPPD, Mobil Operasional dan Asset (dari trigger di ptpemaco_apam.notifications) disembunyikan dari bel.
    // Datanya tidak dihapus; ubah ke false untuk menampilkannya lagi. Hanya berlaku saat 'aktif' true.
    'sembunyikan_lama' => env('NOTIF_SEMBUNYIKAN_LAMA', true),

    'wa' => [
        'aktif' => env('NOTIF_WA_AKTIF', false),
        // event yang dikirim ke WhatsApp: "*" = semua event notifikasi (sama seperti push). Untuk membatasi, isi daftar event, mis. ["adm.review", "sppd.review"]
        'event' => ['*'],
    ],

    'webpush' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:it@pema.co.id'),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    // FCM (aplikasi Android/iOS): kirim aktif setelah ada akun Firebase dan aplikasi mobile
    'fcm' => [
        'aktif' => env('FCM_AKTIF', false),
        'project_id' => env('FCM_PROJECT_ID'),
        'service_account' => env('FCM_SERVICE_ACCOUNT'),
    ],

    'url_web' => env('NOTIF_URL_WEB', 'https://sys.pema.co.id'),
];
