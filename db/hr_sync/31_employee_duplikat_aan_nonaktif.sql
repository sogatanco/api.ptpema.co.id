-- Duplikat "Aan Alfiansyah": di apam ada dua baris employees.
--   202605143K  = data HR (users HR, email alfiansyah@pema.co.id, akun apam 170)  -> dipakai
--   202605052K  = sisa lama (tidak ada di HR, akun apam 169 dengan email salah alfiansyag@pema.co.id) -> dinonaktifkan
-- 202605052K tidak dipakai di daily, project, task, tiket (dicek 3 Nov 2026). Hanya menandai employe_active = 0; tidak ada baris yang dihapus.
-- Aman diulang. Rollback: 32_employee_duplikat_aan_rollback.sql
UPDATE ptpemaco_apam.employees e
SET e.employe_active = 0
WHERE e.employe_id = '202605052K'
  AND NOT EXISTS (SELECT 1 FROM ptpemaco_hr_production.users h WHERE h.employe_id COLLATE utf8mb4_unicode_ci = e.employe_id COLLATE utf8mb4_unicode_ci);

-- Laporan: 202605052K aktif = 0, 202605143K aktif = 1
SELECT employe_id, first_name, employe_active, user_id FROM ptpemaco_apam.employees WHERE employe_id IN ('202605052K', '202605143K');
