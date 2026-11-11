-- Menghapus tabel notifikasi lama ptpemaco_apam.notifications (notifikasi kini di ptpemaco_notif).
-- PRASYARAT: 05_backup_notifikasi_lama.sql sudah dijalankan (dan mysqldump-nya), NOTIF_BARU_AKTIF=true, backend terbaru sudah dirilis.
-- Jalankan: mysql ptpemaco_apam < db/notif/06_hapus_notifikasi_lama.sql
--
-- DAMPAK: view send_message (sumber bot WhatsApp lama lewat /dapi/send-message/first dan /dapi/notif/{id}/set-swa) bergantung pada
-- tabel ini, sehingga ikut dihapus. Pengiriman WhatsApp kini lewat antrean notifikasi:kirim-wa (1 pesan per menit).
-- Tabel notification_entity dan notification_entity_type dibiarkan (kecil, tidak dipakai kode lagi); project_notifications tidak disentuh.
-- Pemulihan: lihat bagian bawah file ini.

USE ptpemaco_apam;

DELIMITER $$
DROP PROCEDURE IF EXISTS _hapus_notifikasi_lama$$
CREATE PROCEDURE _hapus_notifikasi_lama()
BEGIN
  IF (SELECT COUNT(*) FROM notifications) <> (SELECT COUNT(*) FROM notifications_bak_20261111) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cadangan tidak sama dengan tabel asli; jalankan 05_backup_notifikasi_lama.sql lagi. Tidak ada yang dihapus.';
  END IF;
  DROP VIEW IF EXISTS send_message;
  DROP TABLE notifications;
END$$
DELIMITER ;

CALL _hapus_notifikasi_lama();
DROP PROCEDURE _hapus_notifikasi_lama;

-- PEMULIHAN (jika perlu):
--   RENAME TABLE notifications_bak_20261111 TO notifications;
--   lalu view send_message dari file mysqldump di langkah 0:  mysql ptpemaco_apam < /root/backup_notifikasi_lama_YYYYMMDD.sql
-- Setelah yakin aman (mis. 1 bulan), hapus tabel cadangan:  DROP TABLE notifications_bak_20261111;
