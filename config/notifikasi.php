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
        // hanya event yang butuh tindakan penerima yang dikirim ke WhatsApp
        'event' => [
            'adm.review', 'adm.backdate_diajukan', 'adm.surat_masuk', 'adm.cc',
            'sppd.review', 'mobil.permintaan', 'asset.permintaan_service',
            'project.review_task', 'project.bast_review', 'project.handover_konfirmasi',
            'pengajuan.review', 'tender.ba_submitted',
            'vendor.registered', 'vendor.review_submit', 'vendor.review_update',
            // kabar untuk pembuat dokumen setiap langkah approval (disetujui atau ditolak)
            'adm.disetujui', 'adm.ditolak', 'adm.backdate_disetujui', 'adm.backdate_ditolak',
            'sppd.disetujui', 'sppd.ditolak', 'sppd.laporan_disetujui', 'sppd.laporan_ditolak',
            'mobil.disetujui', 'mobil.ditolak', 'asset.service_disetujui', 'asset.service_ditolak',
            'project.task_disetujui', 'project.task_direvisi', 'tender.ba_approved', 'tender.ba_revised',
            // pengingat void SPPD yang tidak jadi berangkat, dan kabar SPPD di-void
            'sppd.ingatkan_void', 'sppd.void',
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
