-- 49: samakan nama jabatan 'Supervisor Teknikal Operasi (Minerba)' (positions.id = 77, tanpa kata 'dan')
--   dengan dua jabatan saudaranya: 'Supervisor Teknikal dan Operasi (Migas dan EBTKE)' dan '... (Agro dan Jasa Lainnya)'.
-- Hanya mengubah nama jabatan di HR (ptpemaco_hr_production.positions); id, atasan, dan pemegang jabatan tidak berubah.
-- Baris lama dicadangkan dulu ke ptpemaco_hr_production.bak49_positions (rollback: 50).
-- Hanya berjalan bila nama masih persis seperti semula, jadi aman dijalankan dua kali.
-- Jalankan: mysql -u <user> -p < 49_rename_spv_minerba.sql

CREATE TABLE IF NOT EXISTS ptpemaco_hr_production.bak49_positions LIKE ptpemaco_hr_production.positions;

INSERT IGNORE INTO ptpemaco_hr_production.bak49_positions
SELECT * FROM ptpemaco_hr_production.positions WHERE id = 77 AND position_name = 'Supervisor Teknikal Operasi (Minerba)';

UPDATE ptpemaco_hr_production.positions
SET position_name = 'Supervisor Teknikal dan Operasi (Minerba)'
WHERE id = 77 AND position_name = 'Supervisor Teknikal Operasi (Minerba)';

-- TES: nama_baru = 'Supervisor Teknikal dan Operasi (Minerba)', bak = 1 (setelah dijalankan pertama kali)
SELECT
  (SELECT position_name FROM ptpemaco_hr_production.positions WHERE id = 77) AS nama_baru,
  (SELECT COUNT(*) FROM ptpemaco_hr_production.bak49_positions WHERE id = 77) AS bak;
