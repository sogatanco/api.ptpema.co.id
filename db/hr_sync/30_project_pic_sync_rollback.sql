-- Membatalkan 29_project_pic_sync_from_hr.sql: riwayat PIC baru yang dibuat 29 dihapus, riwayat PIC lama dikembalikan (active/status semula)
-- dari ptpemaco_apam.project_pic_sync_bak. Aman diulang. Tabel backup dibiarkan. DIJALANKAN MANUAL.
-- Catatan: bila setelah 29 aplikasi sudah memakai riwayat baru (mis. review/handover PIC baru), pulihkan manual untuk project itu.

START TRANSACTION;
DELETE h FROM ptpemaco_apam.project_histories h
JOIN ptpemaco_apam.project_pic_sync_bak b ON b.new_history_id = h.history_id;

UPDATE ptpemaco_apam.project_histories x
JOIN ptpemaco_apam.project_pic_sync_bak b ON b.history_id = x.history_id
SET x.active = b.old_active, x.status = b.old_status;

UPDATE ptpemaco_apam.project_pic_sync_bak SET new_history_id = NULL;
COMMIT;

SELECT COUNT(*) AS dipulihkan FROM ptpemaco_apam.project_histories x
JOIN ptpemaco_apam.project_pic_sync_bak b ON b.history_id = x.history_id
WHERE x.active = b.old_active AND x.status = b.old_status;
