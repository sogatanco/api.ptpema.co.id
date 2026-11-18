-- Form pengajuan SPPD: setiap tujuan punya isian "Perlu tiket".
--   perlu_tiket = 1 : SPPD diproses UmumSppd dulu (pemesanan tiket), baru masuk KeuanganSppd.
--   perlu_tiket = 0 : tanpa tiket, SPPD (setelah disetujui) langsung masuk KeuanganSppd.
-- SPPD dengan beberapa tujuan masuk UmumSppd bila minimal satu tujuannya perlu tiket.
-- Baris lama otomatis bernilai 1 (alur lama tidak berubah). Kolom bersifat tambahan; view tidak diubah.
-- Jalankan sebelum memasang versi aplikasi (backend dan frontend) yang memakai kolom ini.
-- Aman dijalankan berulang. Rollback: tujuan_perlu_tiket_rollback.sql

SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'tujuan_sppds' AND column_name = 'perlu_tiket');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `ptpemaco_sppdnew`.`tujuan_sppds` ADD COLUMN `perlu_tiket` TINYINT(1) NOT NULL DEFAULT 1 AFTER `p_tiket`',
  'SELECT ''Kolom tujuan_sppds.perlu_tiket sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
