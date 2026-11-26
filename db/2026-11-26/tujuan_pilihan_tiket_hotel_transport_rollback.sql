-- Rollback tujuan_pilihan_tiket_hotel_transport.sql: hapus kolom hotel_oleh dan transport_lokal.
-- Tujuan dengan perlu_tiket = 2 (admin/mandiri) dikembalikan ke 0 (tidak lewat divisi umum, sama seperti sebelumnya).

UPDATE `ptpemaco_sppdnew`.`tujuan_sppds` SET `perlu_tiket` = 0 WHERE `perlu_tiket` = 2;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'tujuan_sppds' AND column_name = 'hotel_oleh');
SET @sql := IF(@ada = 1,
  'ALTER TABLE `ptpemaco_sppdnew`.`tujuan_sppds` DROP COLUMN `hotel_oleh`',
  'SELECT ''Kolom tujuan_sppds.hotel_oleh tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'tujuan_sppds' AND column_name = 'transport_lokal');
SET @sql := IF(@ada = 1,
  'ALTER TABLE `ptpemaco_sppdnew`.`tujuan_sppds` DROP COLUMN `transport_lokal`',
  'SELECT ''Kolom tujuan_sppds.transport_lokal tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
