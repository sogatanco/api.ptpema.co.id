-- 50: kembalikan nama jabatan positions.id = 77 dari bak49_positions (dibuat oleh 49_rename_spv_minerba.sql).
-- Jalankan: mysql -u <user> -p < 50_rename_spv_minerba_rollback.sql

UPDATE ptpemaco_hr_production.positions p
JOIN ptpemaco_hr_production.bak49_positions b ON b.id = p.id
SET p.position_name = b.position_name
WHERE p.id = 77;

-- TES: nama = 'Supervisor Teknikal Operasi (Minerba)'. Lalu boleh: DROP TABLE ptpemaco_hr_production.bak49_positions;
SELECT position_name AS nama FROM ptpemaco_hr_production.positions WHERE id = 77;
