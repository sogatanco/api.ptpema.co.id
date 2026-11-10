-- Permintaan surat bertanggal mundur (backdate). Admin (AdminAdm) mengajukan surat dengan mode backdate; surat BELUM mengambil nomor dan belum
-- masuk alur paraf. PICAdm memilih nomor dari saran sistem (nomor kosong pada tanggal itu, atau sisipan seperti 107-A); saat disetujui surat
-- dibuat dengan nomor itu dan nomor hari ini tidak terpakai.
-- Jalankan sekali:  mysql -u <user> -p < surat_backdate.sql   (tabel baru, tidak mengubah tabel yang ada)

CREATE TABLE IF NOT EXISTS `ptpemaco_admnew`.`surat_backdate` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `requested_by` VARCHAR(255) NOT NULL,
  `tanggal` DATE NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `file_lampiran` VARCHAR(255) NULL,
  `status` ENUM('menunggu','selesai','ditolak') NOT NULL DEFAULT 'menunggu',
  `nomor_surat` VARCHAR(100) NULL,
  `surat_id` BIGINT UNSIGNED NULL,
  `processed_by` VARCHAR(255) NULL,
  `alasan_tolak` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_requested_by` (`requested_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
