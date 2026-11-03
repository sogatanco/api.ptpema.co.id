-- Data karyawan untuk modul project / daily / task / tiket dibaca dari HR.
-- Latar belakang: tabel apam `employees` menyimpan nama, jabatan (id apam), dan status yang sudah tidak sinkron dengan HR; hanya
-- `public_key` dan kaitan `user_id` yang milik apam. View `ptpemaco_apam.employees_hr` memiliki kolom yang sama dengan `employees`
-- tetapi nama, jabatan (id HR), status aktif, gender/agama/tanggal lahir/foto berasal dari HR (hr.users, employee_positions, employee_personal_data; tanpa struktur_lengkap_oke agar ringan);
-- public_key, nik, dan user_id tetap dari `employees` (user_id bila kosong dari kecocokan email akun apam). Karyawan HR yang belum punya baris di
-- `employees` tetap muncul; baris `employees` yang tidak ada di HR tetap muncul dengan data apam (position_id NULL).
-- Lima view apam yang hanya membaca nama (employe_id, first_name) dari `employees` dialihkan ke `employees_hr`:
--   task_latest_status, pic_task, list_detail_pic_task, send_message, list_task_final.
-- TIDAK menulis data. Tabel `employees` tetap (dirujuk FK 15 tabel dan menyimpan public_key).
-- Berdiri sendiri (tidak bergantung pada 13). Jalankan:  mysql --comments -u <user> -p < 21_employees_hr_view.sql
-- Rollback: 22_employees_hr_rollback.sql. Kode Laravel (branch synch) membaca `employees_hr`: pasang SQL ini SEBELUM deploy kode.

DROP PROCEDURE IF EXISTS ptpemaco_apam.tmp_emp_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_apam.tmp_emp_precheck()
BEGIN
  DECLARE v INT;
  SELECT COUNT(*) INTO v FROM information_schema.views WHERE table_schema = 'ptpemaco_apam' AND table_name IN ('list_detail_pic_task', 'list_task_final', 'pic_task', 'send_message', 'task_latest_status')
    AND (
      (table_name = 'list_detail_pic_task' AND MD5(REPLACE(view_definition, CHAR(13), '')) = '5e4505526e6bd78a0f9460c1abd0900b') OR
      (table_name = 'list_task_final' AND MD5(REPLACE(view_definition, CHAR(13), '')) = 'a093ed935a3140a424622a3cc5e0537c') OR
      (table_name = 'pic_task' AND MD5(REPLACE(view_definition, CHAR(13), '')) = '1a925dd2a23b2f4fa834828c33ce9088') OR
      (table_name = 'send_message' AND MD5(REPLACE(view_definition, CHAR(13), '')) = '75aa15a2b89a241a0f4a2f8acd99fa18') OR
      (table_name = 'task_latest_status' AND MD5(REPLACE(view_definition, CHAR(13), '')) = '5cfcd925c9ab5a6036668cca229e04e8'));
  IF v <> 5 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Definisi view di server berbeda dari yang dipakai skrip; regenerasi skrip';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_apam.tmp_emp_precheck();
DROP PROCEDURE ptpemaco_apam.tmp_emp_precheck;

-- 1. View data karyawan dari HR
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`employees_hr` AS
select `u`.`employe_id` AS `employe_id`,
       `e`.`public_key` AS `public_key`,
       `e`.`nik` AS `nik`,
       coalesce(`e`.`user_id`, (select max(`a`.`id`) from `ptpemaco_apam`.`users` `a` where lower(`a`.`email`) collate utf8mb4_unicode_ci = lower(`u`.`email`) collate utf8mb4_unicode_ci)) AS `user_id`,
       (select min(`ep`.`position_id`) from `ptpemaco_hr_production`.`employee_positions` `ep` where `ep`.`employe_id` collate utf8mb4_unicode_ci = `u`.`employe_id` collate utf8mb4_unicode_ci) AS `position_id`,
       coalesce((select max(`ep`.`is_pic`) from `ptpemaco_hr_production`.`employee_positions` `ep` where `ep`.`employe_id` collate utf8mb4_unicode_ci = `u`.`employe_id` collate utf8mb4_unicode_ci), `e`.`as_pic`, 0) AS `as_pic`,
       left(trim(concat_ws(' ', `u`.`first_name`, nullif(`u`.`last_name`, ''))), 100) AS `first_name`,
       '' AS `last_name`,
       coalesce(`epd`.`gender`, `e`.`gender`) AS `gender`,
       coalesce(`epd`.`religion`, `e`.`religion`) AS `religion`,
       coalesce(`epd`.`birthday`, `e`.`birthday`) AS `birthday`,
       coalesce(`epd`.`birthday_place`, `e`.`birthday_place`) AS `birthday_place`,
       coalesce(`epd`.`marital_status`, `e`.`marital_status`) AS `marital_status`,
       coalesce(`epd`.`photo`, `e`.`img`) AS `img`,
       `u`.`active` AS `employe_active`,
       `e`.`created_at` AS `created_at`,
       `e`.`updated_at` AS `updated_at`
from `ptpemaco_hr_production`.`users` `u`
left join `ptpemaco_hr_production`.`employee_personal_data` `epd` on (`epd`.`employe_id` collate utf8mb4_unicode_ci = `u`.`employe_id` collate utf8mb4_unicode_ci)
left join `ptpemaco_apam`.`employees` `e` on (`e`.`employe_id` collate utf8mb4_unicode_ci = `u`.`employe_id` collate utf8mb4_unicode_ci)
union all
select `e`.`employe_id`, `e`.`public_key`, `e`.`nik`, `e`.`user_id`, NULL, `e`.`as_pic`, `e`.`first_name`, `e`.`last_name`,
       `e`.`gender`, `e`.`religion`, `e`.`birthday`, `e`.`birthday_place`, `e`.`marital_status`, `e`.`img`, `e`.`employe_active`,
       `e`.`created_at`, `e`.`updated_at`
from `ptpemaco_apam`.`employees` `e`
where not exists (select 1 from `ptpemaco_hr_production`.`users` `h` where `h`.`employe_id` collate utf8mb4_unicode_ci = `e`.`employe_id` collate utf8mb4_unicode_ci);

-- 2. Alihkan 5 view

-- list_detail_pic_task
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`list_detail_pic_task` AS
select `c`.`first_name` AS `pic_task`,`ptpemaco_apam`.`a`.`task_id` AS `task_id`,`ptpemaco_apam`.`a`.`task_title` AS `task_title`,`ptpemaco_apam`.`a`.`project_id` AS `project_id`,`ptpemaco_apam`.`a`.`project_name` AS `project_name`,`ptpemaco_apam`.`a`.`divisi` AS `divisi`,`ptpemaco_apam`.`a`.`created_by` AS `created_by`,`ptpemaco_apam`.`a`.`progress` AS `progress`,`ptpemaco_apam`.`a`.`start_date` AS `start_date`,`ptpemaco_apam`.`a`.`end_date` AS `end_date` from ((`ptpemaco_apam`.`list_task_final` `a` join `ptpemaco_apam`.`project_task_pics` `b`) join `ptpemaco_apam`.`employees_hr` `c`) where ((`b`.`task_id` = `ptpemaco_apam`.`a`.`task_id`) and (`c`.`employe_id` = `b`.`employe_id`));

-- list_task_final
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`list_task_final` AS
select `a`.`task_id` AS `task_id`,`a`.`task_title` AS `task_title`,`a`.`task_parent` AS `task_parent`,`a`.`division` AS `division`,`a`.`project_id` AS `project_id`,`z`.`project_name` AS `project_name`,`y`.`organization_name` AS `divisi`,`x`.`first_name` AS `created_by`,if((`ptpemaco_apam`.`b`.`progress` is null),((select sum(`ptpemaco_apam`.`d`.`progress`) from (`ptpemaco_apam`.`progress1` `d` join `ptpemaco_apam`.`keturunan_task` `c`) where ((`a`.`task_id` = `ptpemaco_apam`.`c`.`task_parent`) and (`ptpemaco_apam`.`d`.`task_id` = `ptpemaco_apam`.`c`.`task_id`))) / (select count(`ptpemaco_apam`.`c`.`task_id`) from `ptpemaco_apam`.`keturunan_task` `c` where (`a`.`task_id` = `ptpemaco_apam`.`c`.`task_parent`))),`ptpemaco_apam`.`b`.`progress`) AS `progress`,(select `w`.`start_date` from `ptpemaco_apam`.`project_task_approval` `w` where (`w`.`task_id` = `a`.`task_id`) order by `w`.`approval_id` desc limit 1) AS `start_date`,(select `w`.`end_date` from `ptpemaco_apam`.`project_task_approval` `w` where (`w`.`task_id` = `a`.`task_id`) order by `w`.`approval_id` desc limit 1) AS `end_date` from ((((`ptpemaco_apam`.`project_tasks` `a` join `ptpemaco_apam`.`progress1` `b`) join `ptpemaco_apam`.`projects` `z`) join `ptpemaco_apam`.`organizations` `y`) join `ptpemaco_apam`.`employees_hr` `x`) where ((`a`.`task_id` = `ptpemaco_apam`.`b`.`task_id`) and (`a`.`project_id` = `z`.`project_id`) and (`z`.`division` = `y`.`organization_id`) and (`a`.`created_by` = `x`.`employe_id`));

-- pic_task
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`pic_task` AS
select `a`.`task_id` AS `task_id`,`b`.`first_name` AS `pic_name` from ((`ptpemaco_apam`.`project_tasks` `a` join `ptpemaco_apam`.`employees_hr` `b`) join `ptpemaco_apam`.`project_task_pics` `c`) where ((`c`.`task_id` = `a`.`task_id`) and (`c`.`employe_id` = `b`.`employe_id`));

-- send_message
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`send_message` AS
select `a`.`id` AS `id`,`a`.`actor` AS `actor`,`a`.`s_wa` AS `s_wa`,(select `ptpemaco_apam`.`employees_hr`.`first_name` from `ptpemaco_apam`.`employees_hr` where (`ptpemaco_apam`.`employees_hr`.`employe_id` = `a`.`actor`)) AS `actor_name`,`a`.`recipient` AS `recipient`,(select `ptpemaco_apam`.`employees_hr`.`first_name` from `ptpemaco_apam`.`employees_hr` where (`ptpemaco_apam`.`employees_hr`.`employe_id` = `a`.`recipient`)) AS `reciepint_name`,`b`.`type` AS `type`,`c`.`entity` AS `entity`,`a`.`created_at` AS `created_at`,`b`.`message` AS `message`,coalesce((select (case when (`ptpemaco_hr_production`.`employee_personal_data`.`gender` = 'Laki-laki') then 'Bapak' when (`ptpemaco_hr_production`.`employee_personal_data`.`gender` = 'Perempuan') then 'Ibu' else 'Bapak/Ibu' end) from `ptpemaco_hr_production`.`employee_personal_data` where (`ptpemaco_hr_production`.`employee_personal_data`.`employe_id` = `a`.`recipient`) limit 1),'Bapak/Ibu') AS `panggilan`,(select (case when regexp_like(`ptpemaco_hr_production`.`employee_personal_data`.`phone_number`,'^08[0-9]{8,12}$') then concat('628',substr(`ptpemaco_hr_production`.`employee_personal_data`.`phone_number`,3)) when regexp_like(`ptpemaco_hr_production`.`employee_personal_data`.`phone_number`,'^628[0-9]{8,12}$') then `ptpemaco_hr_production`.`employee_personal_data`.`phone_number` when regexp_like(`ptpemaco_hr_production`.`employee_personal_data`.`phone_number`,'^[1-9][0-9]{6,15}$') then `ptpemaco_hr_production`.`employee_personal_data`.`phone_number` when regexp_like(`ptpemaco_hr_production`.`employee_personal_data`.`phone_number`,'^\\+[0-9]{6,15}$') then `ptpemaco_hr_production`.`employee_personal_data`.`phone_number` else NULL end) from `ptpemaco_hr_production`.`employee_personal_data` where (`ptpemaco_hr_production`.`employee_personal_data`.`employe_id` = `a`.`recipient`) limit 1) AS `pn`,concat('https://sys.ptpema.co.id/',`b`.`url`,if((`b`.`query_key` = ''),concat('/',`a`.`entity_id`),concat('?',`b`.`query_key`,'=',`a`.`entity_id`))) AS `url` from ((`ptpemaco_apam`.`notifications` `a` join `ptpemaco_apam`.`notification_entity_type` `b` on((`a`.`entity_type_id` = `b`.`id`))) join `ptpemaco_apam`.`notification_entity` `c` on((`b`.`entity_id` = `c`.`id`))) where ((`a`.`status` = 0) and (`a`.`s_wa` is null) and (cast(`a`.`created_at` as date) = curdate()));

-- task_latest_status
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`task_latest_status` AS
select `a`.`task_id` AS `task_id`,`b`.`task_parent` AS `task_parent`,`b`.`project_id` AS `project_id`,`b`.`division` AS `division`,`b`.`task_title` AS `task_title`,`b`.`task_desc` AS `task_desc`,`c`.`employe_id` AS `employe_id`,`c`.`first_name` AS `first_name`,`a`.`approval_id` AS `approval_id`,`a`.`status` AS `status`,`b`.`task_progress` AS `task_progress`,`a`.`start_date` AS `start_date`,`a`.`end_date` AS `end_date`,`d`.`first_name` AS `created_by`,`a`.`created_at` AS `send_date`,`b`.`updated_at` AS `updated_at`,`b`.`created_at` AS `created_at`,`b`.`sub` AS `sub`,`ptpemaco_apam`.`e`.`direct_atasan` AS `direct_atasan` from ((((`ptpemaco_apam`.`project_task_approval` `a` join `ptpemaco_apam`.`project_tasks` `b`) join `ptpemaco_apam`.`employees_hr` `c`) join `ptpemaco_apam`.`employees_hr` `d`) join `ptpemaco_apam`.`struktur_lengkap_oke` `e`) where (`a`.`approval_id` in (select max(`ptpemaco_apam`.`project_task_approval`.`approval_id`) from `ptpemaco_apam`.`project_task_approval` group by `ptpemaco_apam`.`project_task_approval`.`task_id`) and (`a`.`task_id` = `b`.`task_id`) and (`a`.`employe_id` = `c`.`employe_id`) and (`b`.`created_by` = `d`.`employe_id`) and (`a`.`employe_id` = `ptpemaco_apam`.`e`.`employe_id`));

-- Laporan: employees_hr harus >= jumlah karyawan HR; selisih nama = perubahan nama mengikuti HR
SELECT (SELECT COUNT(*) FROM ptpemaco_apam.employees_hr) AS employees_hr,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees) AS employees_apam,
       (SELECT COUNT(*) FROM ptpemaco_hr_production.users) AS users_hr,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees_hr WHERE user_id IS NULL AND employe_active = 1) AS aktif_tanpa_user_id;
