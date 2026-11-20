-- Blokir ruang rapat oleh Sekretaris Direksi: ruangan tidak bisa dibooking pada rentang waktu tertentu.
-- Disimpan di tabel terpisah (bukan di meetings) sehingga TIDAK ikut tampil di /jadwal-rapat maupun TV dashboard (keduanya memakai daftar meetings).
-- Aman dijalankan berulang. Rollback: blokir_ruang_rapat_rollback.sql
-- WAJIB:  mysql --comments -u <user> -p < blokir_ruang_rapat.sql

CREATE TABLE IF NOT EXISTS `ptpemaco_apam`.`meeting_blokir` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `room` VARCHAR(50) NOT NULL COMMENT 'Growth | Harmoni | Kopiah | Internasional',
  `start_time` DATETIME NOT NULL,
  `end_time` DATETIME NOT NULL,
  `alasan` VARCHAR(255) NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NULL DEFAULT NULL COMMENT 'employe_id Sekretaris Direksi',
  `canceled_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `meeting_blokir_room_waktu` (`room`, `start_time`, `end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
