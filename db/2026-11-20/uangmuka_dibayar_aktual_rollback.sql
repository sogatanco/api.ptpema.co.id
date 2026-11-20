-- Rollback uangmuka_dibayar_aktual.sql: hapus kolom penanda di proses_termin (dan sisa kolom versi awal di proses / ekstends bila ada).
-- PERHATIAN: baris termin yang ditandai "dibayar di aktual" (file kosong) tetap ada dan akan tampak seperti termin berbukti kosong.
-- Hapus manual baris proses_termin dengan file kosong bila ingin membukanya lagi.
SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_termin' AND column_name = 'ditunda_aktual');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`proses_termin` DROP COLUMN `ditunda_aktual`, DROP COLUMN `catatan`', 'SELECT ''kolom proses_termin sudah tidak ada'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses' AND column_name = 'ditunda_aktual');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`proses` DROP COLUMN `ditunda_aktual`, DROP COLUMN `catatan`', 'SELECT ''kolom proses versi awal tidak ada'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'uangmuka_aktual');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `uangmuka_aktual`, DROP COLUMN `catatan_aktual`', 'SELECT ''kolom ekstends versi awal tidak ada'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
