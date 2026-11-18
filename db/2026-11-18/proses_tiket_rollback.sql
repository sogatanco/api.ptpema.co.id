-- Rollback proses_tiket.sql (menghapus semua data tiket yang sudah diunggah lewat fitur ini; file PDF di public/sppd/proses/ tidak ikut terhapus)
DROP TABLE `ptpemaco_sppdnew`.`proses_tiket`;
ALTER TABLE `ptpemaco_sppdnew`.`proses` DROP COLUMN `sumber_tiket`;
