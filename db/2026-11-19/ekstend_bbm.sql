-- Uang BBM (optional) pada pengajuan extend: ekstends.bbm (0 = tidak ada). Dipakai perhitungan uang muka extend (BBM 100%, sama dengan SPPD awal).
-- Aman dijalankan berulang. Rollback: ekstend_bbm_rollback.sql
-- WAJIB:  mysql --comments -u <user> -p < ekstend_bbm.sql

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'bbm');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `bbm` DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT ''uang BBM extend (optional)''', 'SELECT ''ekstends.bbm sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
