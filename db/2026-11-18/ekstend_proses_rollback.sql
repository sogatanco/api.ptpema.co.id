-- Rollback ekstend_proses.sql. Jalankan setelah aplikasi dikembalikan ke versi lama.
-- PERHATIAN: data tiket/termin extend (id_ekstend <> 0) ikut hilang bila kolomnya dibuang; hapus dulu barisnya agar kunci unik lama dapat dipasang kembali.
-- Aman dijalankan berulang.

SET @ada_tabel := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_termin');
SET @ada_kol := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_termin' AND column_name = 'id_ekstend');
SET @sql := IF(@ada_kol = 1, 'DELETE FROM `ptpemaco_sppdnew`.`proses_termin` WHERE `id_ekstend` <> 0', 'SELECT ''proses_termin.id_ekstend tidak ada'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(@ada_kol = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`proses_termin` DROP INDEX `proses_termin_unik`, ADD UNIQUE KEY `proses_termin_unik` (`id_sppd`, `id_tujuan`, `termin`)', 'SELECT ''kunci lama sudah terpasang'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada_kol2 := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_tiket' AND column_name = 'id_ekstend');
SET @sql := IF(@ada_kol2 = 1, 'DELETE FROM `ptpemaco_sppdnew`.`proses_tiket` WHERE `id_ekstend` <> 0', 'SELECT ''proses_tiket.id_ekstend tidak ada'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_tiket' AND column_name = 'id_ekstend');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`proses_tiket` DROP COLUMN `id_ekstend`', 'SELECT ''proses_tiket.id_ekstend tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_termin' AND column_name = 'id_ekstend');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`proses_termin` DROP COLUMN `id_ekstend`', 'SELECT ''proses_termin.id_ekstend tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'uangmuka');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `uangmuka`', 'SELECT ''ekstends.uangmuka tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'by_umum');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `by_umum`', 'SELECT ''ekstends.by_umum tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'sumber_tiket');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `sumber_tiket`', 'SELECT ''ekstends.sumber_tiket tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'disetujui_at');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `disetujui_at`', 'SELECT ''ekstends.disetujui_at tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'butuh_proses');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `butuh_proses`', 'SELECT ''ekstends.butuh_proses tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'perlu_tiket');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `perlu_tiket`', 'SELECT ''ekstends.perlu_tiket tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'moda');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `moda`', 'SELECT ''ekstends.moda tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'detail_tujuan');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `detail_tujuan`', 'SELECT ''ekstends.detail_tujuan tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'id_kategori');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `id_kategori`', 'SELECT ''ekstends.id_kategori tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'tipe');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `tipe`', 'SELECT ''ekstends.tipe tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

