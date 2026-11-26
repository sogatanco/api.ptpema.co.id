-- Rollback tujuan_rkap_lainnya.sql: hapus kolom tujuan_sppds.rkap_lainnya (tulisan RKAP manual hilang; tujuan terkait tampil tanpa RKAP).

SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'tujuan_sppds' AND column_name = 'rkap_lainnya');
SET @sql := IF(@ada = 1,
  'ALTER TABLE `ptpemaco_sppdnew`.`tujuan_sppds` DROP COLUMN `rkap_lainnya`',
  'SELECT ''Kolom tujuan_sppds.rkap_lainnya tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
