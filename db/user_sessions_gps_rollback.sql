-- Rollback user_sessions_gps.sql
ALTER TABLE `ptpemaco_apam`.`user_sessions` DROP COLUMN `gps_lat`, DROP COLUMN `gps_lng`, DROP COLUMN `gps_akurasi`, DROP COLUMN `gps_at`;
