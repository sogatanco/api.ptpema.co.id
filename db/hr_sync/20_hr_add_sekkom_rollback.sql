-- Membatalkan 19_hr_add_sekkom.sql: hapus baris jabatan SEKKOM-01 -> 68 di HR. MENGUBAH DATA HR. Aman diulang; jabatan lain SEKKOM-01 tidak disentuh.
START TRANSACTION;
DELETE FROM ptpemaco_hr_production.employee_positions
WHERE employe_id = 'SEKKOM-01' AND position_id = 68 AND is_pic = 0;
COMMIT;
SELECT u.employe_id, (SELECT COUNT(*) FROM ptpemaco_hr_production.employee_positions ep WHERE ep.employe_id = u.employe_id) AS jumlah_jabatan
FROM ptpemaco_hr_production.users u WHERE u.employe_id = 'SEKKOM-01';
