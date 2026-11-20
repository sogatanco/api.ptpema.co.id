-- Uang muka per TERMIN boleh tidak dibayarkan di muka (mis. SPPD mendadak): KeuanganSppd menandai termin itu "dibayar di aktual (realisasi)".
-- Satu termin bisa dibayar sementara termin lain tidak. Termin yang ditandai dicatat di proses_termin tanpa bukti transfer
-- (file kosong) dan dihitung sebagai "sudah ditangani", sehingga uang muka selesai bila seluruh termin berbukti atau ditandai.
--   proses_termin.ditunda_aktual : 1 = termin ini dibayar saat realisasi (aktual)
--   proses_termin.catatan        : alasan (opsional)
-- Prasyarat: db/2026-11-18/proses_termin.sql. Aman dijalankan berulang. Rollback: uangmuka_dibayar_aktual_rollback.sql
-- WAJIB:  mysql --comments -u <user> -p < uangmuka_dibayar_aktual.sql

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_termin' AND column_name = 'ditunda_aktual');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`proses_termin` ADD COLUMN `ditunda_aktual` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = termin dibayar saat realisasi (aktual), tanpa bukti transfer''', 'SELECT ''proses_termin.ditunda_aktual sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_termin' AND column_name = 'catatan');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`proses_termin` ADD COLUMN `catatan` VARCHAR(255) NULL DEFAULT NULL COMMENT ''alasan termin dibayar di aktual''', 'SELECT ''proses_termin.catatan sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
