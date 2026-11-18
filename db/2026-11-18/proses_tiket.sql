-- Proses tiket oleh UmumSppd (menu SPPD > PERLU DIPROSES).
-- UmumSppd memilih sumber tiket (tidak_ada | pelaksana | admin | umum). Bila "umum", tiket pergi/pulang
-- per tujuan SPPD diunggah satu per satu (bisa lebih dari satu), lengkap dengan keterangan dan total harga.
-- Terpisah dari realisasi (rill_tiket tidak berubah). Kolom baru boleh kosong; data lama tidak berubah.
-- Jalankan sebelum memasang versi aplikasi (backend) yang menulis tabel ini.
-- Aman dijalankan berulang: bagian yang sudah ada dilewati (tidak error "Duplicate column name").
-- Rollback: proses_tiket_rollback.sql

-- 1) kolom proses.sumber_tiket (dibuat hanya bila belum ada)
SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses' AND column_name = 'sumber_tiket');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `ptpemaco_sppdnew`.`proses` ADD COLUMN `sumber_tiket` VARCHAR(20) NULL DEFAULT NULL',
  'SELECT ''Kolom proses.sumber_tiket sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) tabel proses_tiket (dibuat hanya bila belum ada)
CREATE TABLE IF NOT EXISTS `ptpemaco_sppdnew`.`proses_tiket` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sppd` BIGINT UNSIGNED NOT NULL,
  `id_tujuan` BIGINT UNSIGNED NOT NULL,
  `arah` VARCHAR(10) NOT NULL, -- pergi | pulang
  `keterangan` VARCHAR(255) NULL DEFAULT NULL,
  `file` VARCHAR(255) NOT NULL,
  `harga` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `uploaded_by` VARCHAR(255) NULL DEFAULT NULL, -- employe_id (teks), mis. 202305031P
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `proses_tiket_id_sppd_index` (`id_sppd`),
  KEY `proses_tiket_id_tujuan_index` (`id_tujuan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) perbaikan untuk versi lama skrip ini yang membuat uploaded_by bertipe angka (employe_id berbentuk teks); tidak berefek bila sudah VARCHAR
ALTER TABLE `ptpemaco_sppdnew`.`proses_tiket` MODIFY `uploaded_by` VARCHAR(255) NULL DEFAULT NULL;
