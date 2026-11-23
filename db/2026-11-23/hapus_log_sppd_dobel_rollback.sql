-- Rollback hapus_log_sppd_dobel.sql: mengembalikan baris log yang dihapus dari tabel cadangan.
USE ptpemaco_esign;

INSERT INTO logs
SELECT c.* FROM logs_sppd_dobel_cadangan c
WHERE c.id NOT IN (SELECT id FROM logs);

-- tabel cadangan boleh dihapus setelah yakin tidak perlu rollback lagi:
-- DROP TABLE logs_sppd_dobel_cadangan;
