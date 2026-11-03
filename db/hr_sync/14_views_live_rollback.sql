-- 14_views_live_rollback.sql  -  kembalikan struktur_lengkap_oke, divisions, atasan_terkait dari *_backup (dibuat oleh 13_views_live_apply.sql)
-- View *_backup tidak dihapus; hapus manual bila sudah tidak perlu:
--   DROP VIEW `ptpemaco_apam`.`atasan_terkait_backup`, `ptpemaco_apam`.`divisions_backup`, `ptpemaco_apam`.`struktur_lengkap_oke_backup`;
SET NAMES utf8mb4;

SET @d = (SELECT `view_definition` FROM `information_schema`.`views` WHERE `table_schema` = 'ptpemaco_apam' AND `table_name` = 'struktur_lengkap_oke_backup');
SET @sql = IF(@d IS NOT NULL, CONCAT('CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`struktur_lengkap_oke` AS ', @d), 'SELECT "backup struktur_lengkap_oke_backup tidak ditemukan, dilewati" AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @d = (SELECT `view_definition` FROM `information_schema`.`views` WHERE `table_schema` = 'ptpemaco_apam' AND `table_name` = 'divisions_backup');
SET @sql = IF(@d IS NOT NULL, CONCAT('CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`divisions` AS ', REPLACE(@d,'`struktur_lengkap_oke_backup`','`struktur_lengkap_oke`')), 'SELECT "backup divisions_backup tidak ditemukan, dilewati" AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
SET @d = (SELECT `view_definition` FROM `information_schema`.`views` WHERE `table_schema` = 'ptpemaco_apam' AND `table_name` = 'atasan_terkait_backup');
SET @sql = IF(@d IS NOT NULL, CONCAT('CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`atasan_terkait` AS ', REPLACE(@d,'`struktur_lengkap_oke_backup`','`struktur_lengkap_oke`')), 'SELECT "backup atasan_terkait_backup tidak ditemukan, dilewati" AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;
