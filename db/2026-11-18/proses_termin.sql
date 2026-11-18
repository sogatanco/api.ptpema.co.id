-- Bukti transfer uang muka per termin (lembar proses KeuanganSppd).
-- SPPD sampai 4 hari = 1 termin; lebih dari 4 hari dibayar per 4 hari. KeuanganSppd mengupload bukti transfer untuk tiap termin.
-- Uang muka dianggap selesai diproses (baris proses 'uangmuka' dibuat aplikasi) setelah semua termin punya bukti.
-- Satu baris = satu termin satu tujuan yang sudah dibuktikan; (id_sppd, id_tujuan, termin) unik.
-- Jalankan sebelum memasang versi aplikasi (backend dan frontend) yang memakai tabel ini.
-- Aman dijalankan berulang. Rollback: proses_termin_rollback.sql

CREATE TABLE IF NOT EXISTS `ptpemaco_sppdnew`.`proses_termin` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sppd` BIGINT UNSIGNED NOT NULL,
  `id_tujuan` BIGINT UNSIGNED NOT NULL,
  `termin` TINYINT UNSIGNED NOT NULL, -- nomor termin, mulai 1
  `tgl_bayar` DATE NOT NULL,          -- tanggal jatuh tempo termin menurut perhitungan
  `jumlah` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `file` VARCHAR(255) NOT NULL,       -- bukti transfer (pdf/jpg/png) di public/sppd/proses/{id_sppd}/
  `diproses_oleh` VARCHAR(255) NULL DEFAULT NULL, -- employe_id KeuanganSppd
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `proses_termin_unik` (`id_sppd`, `id_tujuan`, `termin`),
  KEY `proses_termin_id_sppd_index` (`id_sppd`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
