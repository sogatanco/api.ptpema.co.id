-- Rollback blokir_ruang_rapat.sql: hapus tabel blokir (seluruh blokir ruang hilang; booking rapat tidak terpengaruh).
DROP TABLE IF EXISTS `ptpemaco_apam`.`meeting_blokir`;
