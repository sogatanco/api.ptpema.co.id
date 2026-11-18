-- Rollback hitungan_biaya_uang_muka.sql: kembalikan view hitungan_biaya ke definisi asli yang dicadangkan.
-- Tabel cadangan ptpemaco_sppdnew.view_backup_20261118 dibiarkan (hapus manual bila sudah tidak diperlukan:
--   DROP TABLE `ptpemaco_sppdnew`.`view_backup_20261118`;).
-- Bila cadangan tidak ada, script berhenti dengan pesan dan view tidak diubah.

SET @ada = (SELECT COUNT(*) FROM `information_schema`.`TABLES` WHERE `TABLE_SCHEMA` = 'ptpemaco_sppdnew' AND `TABLE_NAME` = 'view_backup_20261118');
SET @sql_ambil = IF(@ada > 0,
  'SELECT `definisi`, `definer`, `security_type` INTO @def, @definer, @sec FROM `ptpemaco_sppdnew`.`view_backup_20261118` WHERE `nama` = ''hitungan_biaya''',
  'SET @def = NULL');
PREPARE s1 FROM @sql_ambil;
EXECUTE s1;
DEALLOCATE PREPARE s1;

SET @sql = IF(@def IS NOT NULL,
  CONCAT('CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`', SUBSTRING_INDEX(@definer, '@', 1), '`@`', SUBSTRING_INDEX(@definer, '@', -1),
         '` SQL SECURITY ', @sec, ' VIEW `ptpemaco_sppdnew`.`hitungan_biaya` AS ', @def),
  'SELECT ''Cadangan view_backup_20261118 tidak ditemukan, view TIDAK diubah'' AS pesan');
PREPARE s2 FROM @sql;
EXECUTE s2;
DEALLOCATE PREPARE s2;

SELECT IF(@def IS NOT NULL, 'hitungan_biaya dikembalikan ke definisi asli', 'DIBATALKAN: cadangan tidak ada') AS hasil;
