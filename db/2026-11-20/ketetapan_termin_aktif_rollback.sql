-- Rollback ketetapan_termin_aktif.sql: hapus kolom; semua ketetapan kembali memakai termin (perilaku bawaan).
SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ketetapan_sppds' AND column_name = 'termin_aktif');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ketetapan_sppds` DROP COLUMN `termin_aktif`', 'SELECT ''kolom sudah tidak ada'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
