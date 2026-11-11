-- Rollback sppd_void.sql: menghapus tabel penanda SPPD void (SPPD yang pernah di-void kembali tampil seperti biasa).
-- Cadangkan dulu bila perlu:  CREATE TABLE ptpemaco_sppdnew.sppd_void_bak AS SELECT * FROM ptpemaco_sppdnew.sppd_void;
DROP TABLE IF EXISTS `ptpemaco_sppdnew`.`sppd_void`;
