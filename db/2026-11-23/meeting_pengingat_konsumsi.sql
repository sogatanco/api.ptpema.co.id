-- Tujuan : penanda bahwa pengingat konsumsi 1 jam sebelum rapat sudah dikirim ke PIC Konsumsi,
--          supaya perintah terjadwal meeting:ingatkan-konsumsi tidak mengirim dua kali untuk rapat yang sama.
-- Database: ptpemaco_apam (database utama, tabel meetings).
-- Urutan : jalankan sekali. Bila kolom sudah ada, MySQL menolak dengan "Duplicate column name"; itu aman.
-- Rollback: meeting_pengingat_konsumsi_rollback.sql

USE ptpemaco_apam;

ALTER TABLE meetings
    ADD COLUMN pengingat_konsumsi_at DATETIME NULL DEFAULT NULL AFTER canceled_at;
