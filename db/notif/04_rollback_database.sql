-- Menghapus database notifikasi baru beserta isinya. Jalankan hanya setelah trigger lama dikembalikan
-- (db/notif/03_rollback_trigger.sql) dan NOTIF_BARU_AKTIF=false.
DROP DATABASE IF EXISTS ptpemaco_notif;
