-- Lokasi dari browser (Geolocation API) untuk Setting > Lokasi User. Dikirim browser hanya bila pengguna SUDAH mengizinkan lokasi;
-- tanpa izin, peta memakai perkiraan lokasi dari IP. Kolom baru boleh kosong; data lama tidak berubah.
-- Jalankan sebelum memasang versi aplikasi (backend) yang menulis kolom ini.
-- Rollback: user_sessions_gps_rollback.sql
ALTER TABLE `ptpemaco_apam`.`user_sessions`
  ADD COLUMN `gps_lat` DECIMAL(9,6) NULL DEFAULT NULL AFTER `aplikasi`,
  ADD COLUMN `gps_lng` DECIMAL(9,6) NULL DEFAULT NULL AFTER `gps_lat`,
  ADD COLUMN `gps_akurasi` INT UNSIGNED NULL DEFAULT NULL AFTER `gps_lng`,
  ADD COLUMN `gps_at` DATETIME NULL DEFAULT NULL AFTER `gps_akurasi`;
