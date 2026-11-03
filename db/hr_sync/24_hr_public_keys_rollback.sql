-- Membatalkan 23_hr_public_keys_apply.sql: view employees_hr kembali membaca public_key dari apam.employees, lalu tabel HR employee_public_keys dihapus.
-- Aman: key tetap ada di apam.employees (kode baru menulis ke keduanya). Key yang dibuat SETELAH 23 sudah tertulis juga di apam.employees.
-- Jalankan:  mysql --comments -u <user> -p < 24_hr_public_keys_rollback.sql   (aman diulang)

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

DROP TABLE IF EXISTS ptpemaco_hr_production.employee_public_keys;

SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ptpemaco_hr_production' AND table_name = 'employee_public_keys') AS tabel_hr_tersisa,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees_hr WHERE public_key IS NOT NULL AND public_key <> '') AS key_terbaca;
