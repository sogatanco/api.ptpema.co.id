-- Hapus ketergantungan apam pada tabel employees, positions, organizations, board_organizations: semua data karyawan, jabatan, divisi, dan board dibaca dari HR.
-- Yang tersisa dari apam hanya `users` (login, password, roles), kini dengan kolom penaut users.employe_id -> HR users.employe_id.
--
-- Langkah (satu berkas):
--   1. users: tambah kolom employe_id (unik) dan isi dari tautan lama employees.user_id (hanya karyawan yang ada di HR).
--   2. employees_hr didefinisikan ulang: nama, jabatan, status, data pribadi dari HR; public_key dari HR employee_public_keys; user_id dari users.employe_id. Tidak membaca apam.employees lagi.
--   3. Lepas 18 foreign key dari tabel aplikasi (daily, project, task, tiket, dll.) ke employees dan organizations. Kolom employe_id/division tetap berisi id yang sama (id HR).
--   4. Ganti nama 4 tabel menjadi zz_hapus_* (data utuh): employees, positions, organizations, board_organizations.
--   5. Buat VIEW dengan nama lama (employees, organizations, board_organizations) yang membaca HR, supaya view/trigger/kode lain yang masih menyebut nama itu tetap jalan
--      (mis. sppdnew.list_sppd, esign.list_verif, admnew.list_surat, apam.list_task_final). positions tidak diberi view: tidak ada objek aktif yang memakainya.
-- Dampak: view employees/organizations/board_organizations hanya-baca (penulisan lewat API integrasi/EmployeController gagal; sudah ditangani di kode branch hr-only).
--   Employee id yang tidak ada di HR (mis. '3' Edwar Salim, nonaktif: 13 baris di project_notifications, project_task_approval, project_task_favorite) tidak punya baris di employees_hr; namanya kosong di daftar itu.
--   Board id 12 'Komite' ada di apam tetapi tidak di HR; tidak dipakai organisasi mana pun.
-- Prasyarat: skrip 13, 21, 23 sudah dijalankan; kode branch hr-only (atau main yang sudah memuatnya) di-deploy bersamaan (maintenance on).
-- Aman dari dua kali jalan: ALTER TABLE users ADD COLUMN gagal bila kolom sudah ada. Rollback: 36_apam_tanpa_employees_rollback.sql
-- Jalankan:  mysql -u <user> -p < 35_apam_tanpa_employees_apply.sql   (backup dulu: dump ptpemaco_apam)

DROP PROCEDURE IF EXISTS ptpemaco_apam.tmp_35_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_apam.tmp_35_precheck()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ptpemaco_apam' AND table_type = 'BASE TABLE'
        AND table_name IN ('employees', 'positions', 'organizations', 'board_organizations')) <> 4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'employees/positions/organizations/board_organizations bukan 4 tabel biasa (skrip pernah dijalankan?)';
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ptpemaco_hr_production' AND table_name = 'employee_public_keys') <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabel HR employee_public_keys belum ada: jalankan 23 dulu';
  END IF;
  IF (SELECT COUNT(*) FROM ptpemaco_apam.employees e WHERE e.public_key IS NOT NULL AND e.public_key <> ''
        AND NOT EXISTS (SELECT 1 FROM ptpemaco_hr_production.employee_public_keys k WHERE k.employe_id COLLATE utf8mb4_unicode_ci = e.employe_id COLLATE utf8mb4_unicode_ci AND k.public_key = e.public_key)) > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ada public_key di apam.employees yang belum ada/berbeda di HR: jalankan 23 ulang atau samakan dulu';
  END IF;
  IF (SELECT COUNT(*) FROM ptpemaco_apam.employees e WHERE e.user_id IS NOT NULL
        AND EXISTS (SELECT 1 FROM ptpemaco_hr_production.users h WHERE h.employe_id COLLATE utf8mb4_unicode_ci = e.employe_id COLLATE utf8mb4_unicode_ci)) < 150 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tautan user_id karyawan HR kurang dari 150; periksa data';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_apam.tmp_35_precheck();
DROP PROCEDURE ptpemaco_apam.tmp_35_precheck;

-- 1. users.employe_id
ALTER TABLE ptpemaco_apam.users
  ADD COLUMN employe_id VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL AFTER email,
  ADD UNIQUE KEY users_employe_id_unique (employe_id);

UPDATE ptpemaco_apam.users u
JOIN ptpemaco_apam.employees e ON e.user_id = u.id
JOIN ptpemaco_hr_production.users h ON h.employe_id COLLATE utf8mb4_unicode_ci = e.employe_id COLLATE utf8mb4_unicode_ci
SET u.employe_id = e.employe_id;

-- 2. employees_hr tanpa apam.employees
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`employees_hr` AS
select `u`.`employe_id` AS `employe_id`,
 `k`.`public_key` AS `public_key`,
 cast(NULL as char(30)) AS `nik`,
 (select max(`a`.`id`) from `ptpemaco_apam`.`users` `a` where (`a`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci)) AS `user_id`,
 (select min(`ep`.`position_id`) from `ptpemaco_hr_production`.`employee_positions` `ep` where (`ep`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci)) AS `position_id`,
 coalesce((select max(`ep`.`is_pic`) from `ptpemaco_hr_production`.`employee_positions` `ep` where (`ep`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci)),0) AS `as_pic`,
 left(trim(concat_ws(' ',`u`.`first_name`,nullif(`u`.`last_name`,''))),100) AS `first_name`,
 '' AS `last_name`,
 `epd`.`gender` AS `gender`,
 `epd`.`religion` AS `religion`,
 `epd`.`birthday` AS `birthday`,
 `epd`.`birthday_place` AS `birthday_place`,
 `epd`.`marital_status` AS `marital_status`,
 `epd`.`photo` AS `img`,
 `u`.`active` AS `employe_active`,
 `u`.`created_at` AS `created_at`,
 `u`.`updated_at` AS `updated_at`
from `ptpemaco_hr_production`.`users` `u`
left join `ptpemaco_hr_production`.`employee_personal_data` `epd` on (`epd`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci)
left join `ptpemaco_hr_production`.`employee_public_keys` `k` on (`k`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci);

-- 3. lepas foreign key ke employees / organizations
ALTER TABLE ptpemaco_apam.activity_bases DROP FOREIGN KEY activity_bases_employe_id_foreign;
ALTER TABLE ptpemaco_apam.daily DROP FOREIGN KEY daily_employe_id_foreign;
ALTER TABLE ptpemaco_apam.daily_comments DROP FOREIGN KEY daily_comments_employe_id_foreign;
ALTER TABLE ptpemaco_apam.daily_logs DROP FOREIGN KEY daily_logs_employe_id_foreign;
ALTER TABLE ptpemaco_apam.project_histories DROP FOREIGN KEY project_histories_employe_id_2;
ALTER TABLE ptpemaco_apam.project_histories DROP FOREIGN KEY project_histories_employe_id_foreign;
ALTER TABLE ptpemaco_apam.project_notifications DROP FOREIGN KEY notification_employe_id_foreign_1;
ALTER TABLE ptpemaco_apam.project_notifications DROP FOREIGN KEY notification_employe_id_foreign_2;
ALTER TABLE ptpemaco_apam.project_stages DROP FOREIGN KEY stage_division_id_foreign;
ALTER TABLE ptpemaco_apam.project_task_approval DROP FOREIGN KEY task_employe_id_2_foreign;
ALTER TABLE ptpemaco_apam.project_task_approval DROP FOREIGN KEY task_employe_id_foreign;
ALTER TABLE ptpemaco_apam.project_task_favorite DROP FOREIGN KEY task_favorite_employe_id_foreign;
ALTER TABLE ptpemaco_apam.project_task_pics DROP FOREIGN KEY task_pic_employe_id;
ALTER TABLE ptpemaco_apam.project_tasks DROP FOREIGN KEY project_task_employe_id_foreign;
ALTER TABLE ptpemaco_apam.projects DROP FOREIGN KEY projects_created_by_foreign;
ALTER TABLE ptpemaco_apam.projects DROP FOREIGN KEY projects_division_foreign;
ALTER TABLE ptpemaco_apam.tickets DROP FOREIGN KEY ticket_pic_foreign;
ALTER TABLE ptpemaco_apam.tickets DROP FOREIGN KEY ticket_requester_foreign;

-- 4. ganti nama tabel (atomik)
RENAME TABLE ptpemaco_apam.employees TO ptpemaco_apam.zz_hapus_employees,
             ptpemaco_apam.positions TO ptpemaco_apam.zz_hapus_positions,
             ptpemaco_apam.organizations TO ptpemaco_apam.zz_hapus_organizations,
             ptpemaco_apam.board_organizations TO ptpemaco_apam.zz_hapus_board_organizations;

-- 5. view bernama lama yang membaca HR
CREATE VIEW `ptpemaco_apam`.`employees` AS
select `employe_id`,`public_key`,`nik`,`user_id`,`position_id`,`as_pic`,`first_name`,`last_name`,`gender`,`religion`,`birthday`,`birthday_place`,`marital_status`,`img`,`employe_active`,`created_at`,`updated_at`
from `ptpemaco_apam`.`employees_hr`;

CREATE VIEW `ptpemaco_apam`.`organizations` AS
select `d`.`id` AS `organization_id`,`d`.`board_id` AS `board_id`,`d`.`division_name` AS `organization_name`,`d`.`division_code` AS `organization_code`,
       if(`d`.`id` > 10, 1, 0) AS `is_division`,`d`.`created_at` AS `created_at`,`d`.`updated_at` AS `updated_at`
from `ptpemaco_hr_production`.`divisions` `d`;

CREATE VIEW `ptpemaco_apam`.`board_organizations` AS
select `b`.`id` AS `board_id`,`b`.`company_id` AS `company_id`,`b`.`board_code` AS `board_code`,`b`.`board_name` AS `board_name`,`b`.`created_at` AS `created_at`,`b`.`updated_at` AS `updated_at`
from `ptpemaco_hr_production`.`board_organizations` `b`;

-- Laporan
SELECT (SELECT COUNT(*) FROM ptpemaco_apam.users WHERE employe_id IS NOT NULL) AS users_tertaut,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees) AS employees_view,
       (SELECT COUNT(*) FROM ptpemaco_hr_production.users) AS users_hr,
       (SELECT COUNT(*) FROM ptpemaco_apam.organizations) AS organizations_view,
       (SELECT COUNT(*) FROM ptpemaco_apam.board_organizations) AS board_view,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees_hr WHERE user_id IS NULL AND employe_active = 1) AS aktif_tanpa_akun;
-- Harus: employees_view = users_hr; organizations_view = 25; board_view = 10. aktif_tanpa_akun = karyawan aktif HR yang belum punya akun apam (periksa; mis. akun uji 99).
