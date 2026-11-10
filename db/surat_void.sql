-- Surat void: admin menandai surat yang tidak terpakai lagi (belum ada paraf/tanda tangan). Baris surat tetap ada untuk jejak, tetapi tidak lagi
-- muncul di daftar review, tidak bisa diparaf, dan nomornya dianggap kosong sehingga bisa dipilih PICAdm untuk backdate.
-- Jalankan sekali:  mysql -u <user> -p < surat_void.sql   (tabel baru, tidak mengubah tabel yang ada)

CREATE TABLE IF NOT EXISTS `ptpemaco_admnew`.`surat_void` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `surat_id` BIGINT UNSIGNED NOT NULL,
  `nomor_surat` VARCHAR(100) NOT NULL,
  `voided_by` VARCHAR(255) NOT NULL,
  `alasan` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_surat_id` (`surat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
