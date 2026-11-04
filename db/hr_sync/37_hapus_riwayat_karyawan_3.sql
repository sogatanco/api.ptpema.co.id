-- 37: hapus 13 baris riwayat milik employe_id '3' (Edwar Salim, tidak ada di HR) di apam.
--   project_notifications (from_employe=3: 1, to_employe=3: 10), project_task_approval (status_by=3: 1),
--   project_task_favorite (employe_id=3: 1).
-- Aman dijalankan sebelum atau sesudah 35. Baris dicadangkan dulu ke tabel bak37_* (rollback: 38). Akun users tidak disentuh.
-- Jalankan: mysql -u <user> -p < 37_hapus_riwayat_karyawan_3.sql

CREATE TABLE IF NOT EXISTS ptpemaco_apam.bak37_project_notifications LIKE ptpemaco_apam.project_notifications;
CREATE TABLE IF NOT EXISTS ptpemaco_apam.bak37_project_task_approval LIKE ptpemaco_apam.project_task_approval;
CREATE TABLE IF NOT EXISTS ptpemaco_apam.bak37_project_task_favorite LIKE ptpemaco_apam.project_task_favorite;

INSERT IGNORE INTO ptpemaco_apam.bak37_project_notifications SELECT * FROM ptpemaco_apam.project_notifications WHERE from_employe = '3' OR to_employe = '3';
INSERT IGNORE INTO ptpemaco_apam.bak37_project_task_approval SELECT * FROM ptpemaco_apam.project_task_approval WHERE status_by = '3' OR employe_id = '3';
INSERT IGNORE INTO ptpemaco_apam.bak37_project_task_favorite SELECT * FROM ptpemaco_apam.project_task_favorite WHERE employe_id = '3';

DELETE FROM ptpemaco_apam.project_notifications WHERE from_employe = '3' OR to_employe = '3';
DELETE FROM ptpemaco_apam.project_task_approval WHERE status_by = '3' OR employe_id = '3';
DELETE FROM ptpemaco_apam.project_task_favorite WHERE employe_id = '3';

-- TES: semua 0 (dan backup berisi 11, 1, 1)
SELECT
  (SELECT COUNT(*) FROM ptpemaco_apam.project_notifications WHERE from_employe = '3' OR to_employe = '3') AS sisa_notif,
  (SELECT COUNT(*) FROM ptpemaco_apam.project_task_approval WHERE status_by = '3' OR employe_id = '3') AS sisa_approval,
  (SELECT COUNT(*) FROM ptpemaco_apam.project_task_favorite WHERE employe_id = '3') AS sisa_favorit,
  (SELECT COUNT(*) FROM ptpemaco_apam.bak37_project_notifications) AS bak_notif,
  (SELECT COUNT(*) FROM ptpemaco_apam.bak37_project_task_approval) AS bak_approval,
  (SELECT COUNT(*) FROM ptpemaco_apam.bak37_project_task_favorite) AS bak_favorit;
