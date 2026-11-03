-- Memberi SEKKOM-01 (Saifan Saputra) jabatan Sekretaris Komisaris (id 68) di HR. Hanya menambah 1 baris employee_positions; data lain dan email tidak diubah.
-- MENGUBAH DATA HR (production): jalankan oleh/atas persetujuan HR, dengan akun yang boleh menulis ke ptpemaco_hr_production.
-- Kondisi sebelum: users SEKKOM-01 aktif, nama Saifan Saputra, tetapi tanpa baris employee_positions, sehingga tidak muncul di view HR dan tidak ditemukan trigger SPPD.
-- Aman diulang (tidak menambah baris kedua). Rollback: 20_hr_add_sekkom_rollback.sql
-- Catatan: jabatan 68 bertingkat Staf di HR, jadi id_base di view = 9 (bukan 2 seperti apam lama) dan tidak punya atasan (parent_id NULL).

DROP PROCEDURE IF EXISTS ptpemaco_hr_production.tmp_add_sekkom_check;
DELIMITER $$
CREATE PROCEDURE ptpemaco_hr_production.tmp_add_sekkom_check()
BEGIN
  IF (SELECT COUNT(*) FROM ptpemaco_hr_production.users WHERE employe_id = 'SEKKOM-01' AND active = 1) <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User SEKKOM-01 aktif tidak ditemukan di HR';
  END IF;
  IF (SELECT COUNT(*) FROM ptpemaco_hr_production.positions WHERE id = 68 AND TRIM(position_name) = 'Sekretaris Komisaris') <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jabatan id 68 bukan Sekretaris Komisaris';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_hr_production.tmp_add_sekkom_check();
DROP PROCEDURE ptpemaco_hr_production.tmp_add_sekkom_check;

START TRANSACTION;
INSERT INTO ptpemaco_hr_production.employee_positions (employe_id, position_id, is_pic, created_at, updated_at)
SELECT 'SEKKOM-01', 68, 0, NOW(), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ptpemaco_hr_production.employee_positions WHERE employe_id = 'SEKKOM-01' AND position_id = 68);
COMMIT;

-- Laporan: 1 baris; position_id 68, Sekretaris Komisaris
SELECT u.employe_id, u.first_name, u.email, u.active, ep.position_id, p.position_name
FROM ptpemaco_hr_production.users u
LEFT JOIN ptpemaco_hr_production.employee_positions ep ON ep.employe_id = u.employe_id
LEFT JOIN ptpemaco_hr_production.positions p ON p.id = ep.position_id
WHERE u.employe_id = 'SEKKOM-01';
