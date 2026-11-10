<?php

return [
    // false: notifikasi modul ADM, SPPD, Mobil Operasional dan Asset masih dihasilkan trigger lama di ptpemaco_apam.notifications.
    // Ubah menjadi true bersamaan dengan menjalankan db/notif/02_hapus_trigger_notifikasi.sql supaya tidak ganda.
    'aktif' => env('NOTIF_BARU_AKTIF', false),

    'wa' => [
        'aktif' => env('NOTIF_WA_AKTIF', false),
        // hanya event yang butuh tindakan penerima yang dikirim ke WhatsApp
        'event' => [
            'adm.review', 'adm.surat_masuk', 'adm.cc',
            'sppd.review', 'mobil.permintaan', 'asset.permintaan_service',
        ],
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
