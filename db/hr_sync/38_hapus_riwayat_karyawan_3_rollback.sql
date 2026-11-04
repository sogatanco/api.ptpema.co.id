-- 38: rollback 37 - kembalikan baris yang dihapus dari tabel bak37_*.
INSERT IGNORE INTO ptpemaco_apam.project_notifications SELECT * FROM ptpemaco_apam.bak37_project_notifications;
INSERT IGNORE INTO ptpemaco_apam.project_task_approval SELECT * FROM ptpemaco_apam.bak37_project_task_approval;
INSERT IGNORE INTO ptpemaco_apam.project_task_favorite SELECT * FROM ptpemaco_apam.bak37_project_task_favorite;
-- Setelah yakin, backup boleh dibuang:
-- DROP TABLE ptpemaco_apam.bak37_project_notifications, ptpemaco_apam.bak37_project_task_approval, ptpemaco_apam.bak37_project_task_favorite;
