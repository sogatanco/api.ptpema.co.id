-- Rollback tigapuluh_rentang.sql: menghapus tabel rentang tanggal Hotel 30%.
-- Klaim tetap utuh karena tigapuluhs.mulai / selesai tetap berisi tanggal paling awal dan paling akhir;
-- rentang yang terpisah (mis. 1-2 dan 5-6) kembali menjadi satu rentang menerus.
USE ptpemaco_sppdnew;

DROP TABLE IF EXISTS tigapuluh_rentang;
