-- Database notifikasi baru, terpisah dari ptpemaco_apam.notifications (yang tetap dipakai daily dan project).
-- Dipakai untuk notifikasi dalam aplikasi (bel), Web Push, FCM (aplikasi mobile) dan WhatsApp.
-- Jalankan sebagai user yang boleh CREATE DATABASE, lalu beri hak ke user aplikasi (ganti 'ptpemaco'@'%' bila berbeda):
--   GRANT SELECT, INSERT, UPDATE, DELETE ON ptpemaco_notif.* TO 'ptpemaco'@'%';
-- Rollback: db/notif/04_rollback_database.sql

CREATE DATABASE IF NOT EXISTS ptpemaco_notif CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ptpemaco_notif;

-- Satu baris per notifikasi per penerima. Judul dan isi sudah berbentuk kalimat lengkap saat dibuat,
-- jadi tidak perlu join ke tabel lain untuk menampilkannya di bel, push, maupun WhatsApp.
CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    modul VARCHAR(20) NOT NULL COMMENT 'adm, sppd, mobil, asset',
    event VARCHAR(40) NOT NULL COMMENT 'mis. adm.review, sppd.disetujui',
    recipient VARCHAR(30) NOT NULL COMMENT 'employe_id penerima',
    actor VARCHAR(30) NULL COMMENT 'employe_id pelaku',
    actor_name VARCHAR(150) NULL,
    judul VARCHAR(150) NOT NULL,
    isi TEXT NOT NULL,
    url VARCHAR(255) NULL COMMENT 'path halaman di sys, mis. adm',
    link VARCHAR(500) NULL COMMENT 'tautan lengkap yang dikirim lewat WhatsApp dan push, mis. https://sys.pema.co.id/adm',
    ref VARCHAR(120) NULL COMMENT 'nomor dokumen atau id rujukan',
    data JSON NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_recipient_belum_dibaca (recipient, read_at, id),
    KEY idx_modul_event (modul, event),
    KEY idx_ref (ref)
) ENGINE=InnoDB;

-- Status pengiriman per kanal, supaya kegagalan bisa dilacak dan dikirim ulang
CREATE TABLE IF NOT EXISTS notification_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    channel ENUM('webpush','fcm','wa') NOT NULL,
    status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
    target VARCHAR(255) NULL COMMENT 'endpoint push, token FCM atau nomor WA (disamarkan)',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    error VARCHAR(500) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notification (notification_id),
    KEY idx_status (status, channel),
    CONSTRAINT fk_deliveries_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Langganan Web Push (satu per browser per pegawai)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employe_id VARCHAR(30) NOT NULL,
    endpoint_hash CHAR(64) NOT NULL COMMENT 'sha256 endpoint, untuk unique key',
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    user_agent VARCHAR(255) NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_endpoint (endpoint_hash),
    KEY idx_employe (employe_id)
) ENGINE=InnoDB;

-- Token perangkat untuk FCM (aplikasi Android/iOS, disiapkan untuk nanti)
CREATE TABLE IF NOT EXISTS device_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employe_id VARCHAR(30) NOT NULL,
    platform ENUM('android','ios','web') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token TEXT NOT NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_token (token_hash),
    KEY idx_employe (employe_id)
) ENGINE=InnoDB;
