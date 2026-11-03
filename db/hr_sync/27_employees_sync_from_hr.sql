-- Menyelaraskan data lama tabel apam `employees` dengan HR (aturan: bila berbeda, HR yang dipakai).
-- Cakupan: nama (employees.first_name = nama lengkap HR, last_name dikosongkan) dan status aktif
-- (employees.employe_active = users.active HR) untuk karyawan yang berbeda saat skrip dijalankan. Karyawan yang tidak ada di HR tidak disentuh.
-- Tidak menyentuh user_id, public_key, position_id, gender dan data pribadi lain (employees_hr sudah menyajikan data HR untuk itu).
-- Nilai lama disimpan di tabel baru ptpemaco_apam.employees_sync_bak (dibuat tanpa IF NOT EXISTS: dijalankan dua kali akan berhenti di sini).
-- DIJALANKAN MANUAL setelah konfirmasi. Rollback: 28_employees_sync_rollback.sql
-- Jalankan:  mysql -u <user> -p < 27_employees_sync_from_hr.sql

CREATE TABLE ptpemaco_apam.employees_sync_bak (
  employe_id varchar(20) NOT NULL,
  first_name varchar(100) NOT NULL,
  last_name varchar(100) DEFAULT NULL,
  employe_active tinyint(1) DEFAULT NULL,
  backed_up_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (employe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ptpemaco_apam.employees_sync_bak (employe_id, first_name, last_name, employe_active)
SELECT e.employe_id, e.first_name, e.last_name, e.employe_active
FROM ptpemaco_apam.employees e
JOIN ptpemaco_hr_production.users h ON h.employe_id COLLATE utf8mb4_unicode_ci = e.employe_id COLLATE utf8mb4_unicode_ci
WHERE LEFT(TRIM(CONCAT_WS(' ', h.first_name, NULLIF(h.last_name, ''))), 100) <> TRIM(CONCAT_WS(' ', e.first_name, NULLIF(e.last_name, '')))
   OR IFNULL(e.employe_active, 0) <> h.active;

START TRANSACTION;
UPDATE ptpemaco_apam.employees e
JOIN ptpemaco_apam.employees_sync_bak b ON b.employe_id = e.employe_id
JOIN ptpemaco_hr_production.users h ON h.employe_id COLLATE utf8mb4_unicode_ci = e.employe_id COLLATE utf8mb4_unicode_ci
SET e.first_name = LEFT(TRIM(CONCAT_WS(' ', h.first_name, NULLIF(h.last_name, ''))), 100),
    e.last_name = NULL,
    e.employe_active = h.active;
COMMIT;

-- Laporan: diubah = jumlah baris backup (saat disusun: 43); sisa_beda harus 0
SELECT (SELECT COUNT(*) FROM ptpemaco_apam.employees_sync_bak) AS diubah,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees e JOIN ptpemaco_hr_production.users h ON h.employe_id COLLATE utf8mb4_unicode_ci = e.employe_id COLLATE utf8mb4_unicode_ci
         WHERE LEFT(TRIM(CONCAT_WS(' ', h.first_name, NULLIF(h.last_name, ''))), 100) <> TRIM(CONCAT_WS(' ', e.first_name, NULLIF(e.last_name, '')))
            OR IFNULL(e.employe_active, 0) <> h.active) AS sisa_beda;
