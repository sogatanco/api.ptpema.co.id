-- Membatalkan 27_employees_sync_from_hr.sql: mengembalikan nama dan status aktif lama dari ptpemaco_apam.employees_sync_bak.
-- Tabel backup dibiarkan (hapus manual setelah stabil). Aman diulang. DIJALANKAN MANUAL.

START TRANSACTION;
UPDATE ptpemaco_apam.employees e
JOIN ptpemaco_apam.employees_sync_bak b ON b.employe_id = e.employe_id
SET e.first_name = b.first_name, e.last_name = b.last_name, e.employe_active = b.employe_active;
COMMIT;

SELECT COUNT(*) AS dikembalikan FROM ptpemaco_apam.employees e
JOIN ptpemaco_apam.employees_sync_bak b ON b.employe_id = e.employe_id
WHERE e.first_name = b.first_name AND e.last_name <=> b.last_name AND e.employe_active <=> b.employe_active;
