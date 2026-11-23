-- Rollback hapus_log_sppd_dobel_menit.sql: mengembalikan baris log dari tabel cadangan.
USE ptpemaco_esign;

INSERT INTO logs
SELECT c.* FROM logs_sppd_dobel_menit_cadangan c
WHERE c.id NOT IN (SELECT id FROM logs);

-- setelah yakin tidak perlu rollback: DROP TABLE logs_sppd_dobel_menit_cadangan;
