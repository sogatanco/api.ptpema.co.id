-- Surat tanda tangan basah: surat yang ditandatangani di atas kertas, tanpa paraf/tanda tangan di sistem. Baris di tabel ini menandai surat
-- sebagai ttd basah (tidak masuk daftar review, tanpa notifikasi "perlu approve") dan menyimpan file surat ber-ttd yang diunggah admin.
-- Jalankan sekali:  mysql -u <user> -p < surat_ttd_basah.sql   (tabel baru, tidak mengubah tabel yang ada)

CREATE TABLE IF NOT EXISTS `ptpemaco_admnew`.`surat_ttd_basah` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `surat_id` BIGINT UNSIGNED NOT NULL,
  `file_ttd` VARCHAR(255) NULL,
  `mime` VARCHAR(64) NULL,
  `uploaded_by` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_surat_id` (`surat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
