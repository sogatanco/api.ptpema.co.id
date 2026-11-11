-- Backup tabel notifikasi lama sebelum dihapus (db/notif/06_hapus_notifikasi_lama.sql).
-- Jalankan di server: mysql ptpemaco_apam < db/notif/05_backup_notifikasi_lama.sql
-- LANGKAH 0 (sangat disarankan, file backup di luar database; ikut menyimpan definisi view send_message):
--   mysqldump --single-transaction ptpemaco_apam notifications notification_entity notification_entity_type send_message \
--     > /root/backup_notifikasi_lama_$(date +%Y%m%d).sql
-- Skrip ini menyalin isi tabel ke tabel cadangan di database yang sama, lalu menampilkan jumlah barisnya untuk dicocokkan.

USE ptpemaco_apam;

DROP TABLE IF EXISTS notifications_bak_20261111;
CREATE TABLE notifications_bak_20261111 LIKE notifications;
INSERT INTO notifications_bak_20261111 SELECT * FROM notifications;

SELECT (SELECT COUNT(*) FROM notifications) AS asli, (SELECT COUNT(*) FROM notifications_bak_20261111) AS cadangan;
