-- SPPD void: admin SPPD menandai SPPD yang tidak jadi berangkat (sudah atau belum di-approve) beserta alasannya. Baris SPPD tetap ada untuk jejak,
-- tetapi tidak lagi muncul di daftar review dan proses, tidak bisa diproses lebih lanjut, dan tombol void tidak tersedia setelah verifikasi realisasi selesai.
-- Jalankan sekali:  mysql -u <user> -p < sppd_void.sql   (tabel baru, tidak mengubah tabel yang ada)
-- Rollback: sppd_void_rollback.sql

CREATE TABLE IF NOT EXISTS `ptpemaco_sppdnew`.`sppd_void` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sppd_id` BIGINT UNSIGNED NOT NULL,
  `nomor_sppd` VARCHAR(255) NULL,
  `voided_by` VARCHAR(255) NOT NULL,
  `alasan` TEXT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sppd_id` (`sppd_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
