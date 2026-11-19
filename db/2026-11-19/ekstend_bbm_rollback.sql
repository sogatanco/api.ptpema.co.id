-- Rollback ekstend_bbm.sql: hapus kolom ekstends.bbm (nilai BBM extend yang sudah diisi hilang).
SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'bbm');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `bbm`', 'SELECT ''kolom sudah tidak ada'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
