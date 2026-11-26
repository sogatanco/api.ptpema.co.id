-- Form pengajuan SPPD: pilihan tiket (3 opsi), hotel, dan transportasi lokal per tujuan.
--   perlu_tiket     : 1 = dipesan divisi umum (alur UmumSppd), 2 = dipesan admin/mandiri (tidak lewat UmumSppd), 0 = tidak perlu tiket
--                     (kolom sudah ada; hanya menambah arti nilai 2)
--   hotel_oleh      : 'sendiri' = dipesan yang berangkat (hotel masuk uang muka), 'admin' = dipesan admin, 'tidak' = tidak menginap (keduanya TIDAK masuk uang muka)
--   transport_lokal : 'mandiri' = dibayar sendiri (masuk uang muka), 'vendor' = memakai vendor (TIDAK masuk uang muka)
-- Baris lama: hotel_oleh = 'sendiri', transport_lokal = 'mandiri' (perhitungan lama tidak berubah).
-- Aman dijalankan berulang. Rollback: tujuan_pilihan_tiket_hotel_transport_rollback.sql

SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'tujuan_sppds' AND column_name = 'hotel_oleh');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `ptpemaco_sppdnew`.`tujuan_sppds` ADD COLUMN `hotel_oleh` VARCHAR(10) NOT NULL DEFAULT ''sendiri''',
  'SELECT ''Kolom tujuan_sppds.hotel_oleh sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'tujuan_sppds' AND column_name = 'transport_lokal');
SET @sql := IF(@ada = 0,
  'ALTER TABLE `ptpemaco_sppdnew`.`tujuan_sppds` ADD COLUMN `transport_lokal` VARCHAR(10) NOT NULL DEFAULT ''mandiri''',
  'SELECT ''Kolom tujuan_sppds.transport_lokal sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
