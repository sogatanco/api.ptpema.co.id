-- Rollback tujuan_perlu_tiket.sql: hapus kolom tujuan_sppds.perlu_tiket (isian "Perlu tiket" per tujuan hilang; semua SPPD kembali melewati UmumSppd).
-- Jalankan setelah aplikasi dikembalikan ke versi lama, karena versi baru menulis kolom ini.
-- Aman dijalankan berulang (dilewati bila kolom sudah tidak ada).
SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'tujuan_sppds' AND column_name = 'perlu_tiket');
SET @sql := IF(@ada = 1,
  'ALTER TABLE `ptpemaco_sppdnew`.`tujuan_sppds` DROP COLUMN `perlu_tiket`',
  'SELECT ''Kolom tujuan_sppds.perlu_tiket tidak ada, dilewati'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
