-- Rollback proses_termin.sql: hapus tabel bukti transfer per termin (data bukti ikut hilang; file di public/sppd/proses/ tidak ikut terhapus).
-- Jalankan setelah aplikasi dikembalikan ke versi lama. Aman dijalankan berulang.
DROP TABLE IF EXISTS `ptpemaco_sppdnew`.`proses_termin`;
