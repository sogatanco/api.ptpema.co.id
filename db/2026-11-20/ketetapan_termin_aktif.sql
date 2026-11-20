-- Termin uang muka dapat diaktifkan / dinonaktifkan per ketetapan (aturan biaya SPPD) lewat Setting > Biaya & Ketetapan SPPD (SuperAdmin).
--   ketetapan_sppds.termin_aktif : 1 = uang muka bertermin (4 hari per termin), 0 = uang muka dibayar sekali tanpa termin
-- Bawaan 1 sehingga perilaku yang ada tidak berubah. Aman dijalankan berulang. Rollback: ketetapan_termin_aktif_rollback.sql
-- WAJIB:  mysql --comments -u <user> -p < ketetapan_termin_aktif.sql

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ketetapan_sppds' AND column_name = 'termin_aktif');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ketetapan_sppds` ADD COLUMN `termin_aktif` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''1 = uang muka bertermin, 0 = dibayar sekali tanpa termin''', 'SELECT ''ketetapan_sppds.termin_aktif sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
