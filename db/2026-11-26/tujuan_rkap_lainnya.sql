-- Form pengajuan SPPD: pilihan RKAP "Lainnya" dengan isian tulisan manual per tujuan.
--   rkap_lainnya : nama RKAP/proyek yang ditulis manual (rkap dikosongkan). Bila terisi, teks ini tampil sebagai RKAP di ST, detail, dan review.
-- Baris lama NULL (memakai pilihan RKAP seperti biasa). Kolom tambahan; view tidak diubah.
-- Aman dijalankan berulang. Rollback: tujuan_rkap_lainnya_rollback.sql

SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'tujuan_sppds' AND column_name = 'rkap_lainnya');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `ptpemaco_sppdnew`.`tujuan_sppds` ADD COLUMN `rkap_lainnya` VARCHAR(255) NULL DEFAULT NULL',
  'SELECT ''Kolom tujuan_sppds.rkap_lainnya sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
