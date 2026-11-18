-- Rollback logs_hapus_duplikat.sql: kembalikan baris log yang dihapus dari cadangan, lalu hapus tabel cadangan.
-- Setelah rollback log persetujuan kembali tercatat ganda seperti semula.
INSERT IGNORE INTO `ptpemaco_esign`.`logs`
SELECT * FROM `ptpemaco_esign`.`logs_duplikat_20261118`;

DROP TABLE IF EXISTS `ptpemaco_esign`.`logs_duplikat_20261118`;
