-- Membatalkan 35: tabel employees, positions, organizations, board_organizations dikembalikan; view bernama lama dihapus; FK dipasang lagi; employees_hr versi sebelumnya dipulihkan.
-- Syarat: kode lama (yang masih membaca/menulis tabel employees) dipakai lagi bila perlu. Kolom users.employe_id dibiarkan (tidak mengganggu); hapus manual bila mau:
--   ALTER TABLE ptpemaco_apam.users DROP INDEX users_employe_id_unique, DROP COLUMN employe_id;
-- Penambahan FK gagal bila ada baris anak yang mereferensikan employe_id/division yang tidak ada di tabel lama (mis. data baru dari id HR yang belum ada di employees lama).
-- Jalankan:  mysql -u <user> -p < 36_apam_tanpa_employees_rollback.sql

DROP VIEW ptpemaco_apam.employees;
DROP VIEW ptpemaco_apam.organizations;
DROP VIEW ptpemaco_apam.board_organizations;

RENAME TABLE ptpemaco_apam.zz_hapus_employees TO ptpemaco_apam.employees,
             ptpemaco_apam.zz_hapus_positions TO ptpemaco_apam.positions,
             ptpemaco_apam.zz_hapus_organizations TO ptpemaco_apam.organizations,
             ptpemaco_apam.zz_hapus_board_organizations TO ptpemaco_apam.board_organizations;

ALTER TABLE ptpemaco_apam.activity_bases ADD CONSTRAINT activity_bases_employe_id_foreign FOREIGN KEY (employe_id) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.daily ADD CONSTRAINT daily_employe_id_foreign FOREIGN KEY (employe_id) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.daily_comments ADD CONSTRAINT daily_comments_employe_id_foreign FOREIGN KEY (employe_id) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.daily_logs ADD CONSTRAINT daily_logs_employe_id_foreign FOREIGN KEY (employe_id) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_histories ADD CONSTRAINT project_histories_employe_id_2 FOREIGN KEY (review_by) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_histories ADD CONSTRAINT project_histories_employe_id_foreign FOREIGN KEY (employe_id) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_notifications ADD CONSTRAINT notification_employe_id_foreign_1 FOREIGN KEY (from_employe) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_notifications ADD CONSTRAINT notification_employe_id_foreign_2 FOREIGN KEY (to_employe) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_stages ADD CONSTRAINT stage_division_id_foreign FOREIGN KEY (division) REFERENCES ptpemaco_apam.organizations (organization_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_task_approval ADD CONSTRAINT task_employe_id_2_foreign FOREIGN KEY (status_by) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_task_approval ADD CONSTRAINT task_employe_id_foreign FOREIGN KEY (employe_id) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_task_favorite ADD CONSTRAINT task_favorite_employe_id_foreign FOREIGN KEY (employe_id) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_task_pics ADD CONSTRAINT task_pic_employe_id FOREIGN KEY (employe_id) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.project_tasks ADD CONSTRAINT project_task_employe_id_foreign FOREIGN KEY (created_by) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.projects ADD CONSTRAINT projects_created_by_foreign FOREIGN KEY (created_by) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.projects ADD CONSTRAINT projects_division_foreign FOREIGN KEY (division) REFERENCES ptpemaco_apam.organizations (organization_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.tickets ADD CONSTRAINT ticket_pic_foreign FOREIGN KEY (pic) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;
ALTER TABLE ptpemaco_apam.tickets ADD CONSTRAINT ticket_requester_foreign FOREIGN KEY (requester) REFERENCES ptpemaco_apam.employees (employe_id) ON DELETE CASCADE ON UPDATE NO ACTION;

CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`employees_hr` AS
select `u`.`employe_id` AS `employe_id`,coalesce(`k`.`public_key`,`e`.`public_key`) AS `public_key`,`e`.`nik` AS `nik`,coalesce(`e`.`user_id`,(select max(`a`.`id`) from `ptpemaco_apam`.`users` `a` where ((lower(`a`.`email`) collate utf8mb4_unicode_ci) = (lower(`u`.`email`) collate utf8mb4_unicode_ci)))) AS `user_id`,(select min(`ep`.`position_id`) from `ptpemaco_hr_production`.`employee_positions` `ep` where ((`ep`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci))) AS `position_id`,coalesce((select max(`ep`.`is_pic`) from `ptpemaco_hr_production`.`employee_positions` `ep` where ((`ep`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci))),`e`.`as_pic`,0) AS `as_pic`,left(trim(concat_ws(' ',`u`.`first_name`,nullif(`u`.`last_name`,''))),100) AS `first_name`,'' AS `last_name`,coalesce(`epd`.`gender`,`e`.`gender`) AS `gender`,coalesce(`epd`.`religion`,`e`.`religion`) AS `religion`,coalesce(`epd`.`birthday`,`e`.`birthday`) AS `birthday`,coalesce(`epd`.`birthday_place`,`e`.`birthday_place`) AS `birthday_place`,coalesce(`epd`.`marital_status`,`e`.`marital_status`) AS `marital_status`,coalesce(`epd`.`photo`,`e`.`img`) AS `img`,`u`.`active` AS `employe_active`,`e`.`created_at` AS `created_at`,`e`.`updated_at` AS `updated_at` from (((`ptpemaco_hr_production`.`users` `u` left join `ptpemaco_hr_production`.`employee_personal_data` `epd` on(((`epd`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci)))) left join `ptpemaco_hr_production`.`employee_public_keys` `k` on(((`k`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci)))) left join `ptpemaco_apam`.`employees` `e` on(((`e`.`employe_id` collate utf8mb4_unicode_ci) = (`u`.`employe_id` collate utf8mb4_unicode_ci)))) union all select `e`.`employe_id` AS `employe_id`,`e`.`public_key` AS `public_key`,`e`.`nik` AS `nik`,`e`.`user_id` AS `user_id`,NULL AS `NULL`,`e`.`as_pic` AS `as_pic`,`e`.`first_name` AS `first_name`,`e`.`last_name` AS `last_name`,`e`.`gender` AS `gender`,`e`.`religion` AS `religion`,`e`.`birthday` AS `birthday`,`e`.`birthday_place` AS `birthday_place`,`e`.`marital_status` AS `marital_status`,`e`.`img` AS `img`,`e`.`employe_active` AS `employe_active`,`e`.`created_at` AS `created_at`,`e`.`updated_at` AS `updated_at` from `ptpemaco_apam`.`employees` `e` where exists(select 1 from `ptpemaco_hr_production`.`users` `h` where ((`h`.`employe_id` collate utf8mb4_unicode_ci) = (`e`.`employe_id` collate utf8mb4_unicode_ci))) is false;

SELECT (SELECT COUNT(*) FROM ptpemaco_apam.employees) AS employees_tabel,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees_hr) AS employees_hr,
       (SELECT COUNT(*) FROM information_schema.referential_constraints WHERE constraint_schema = 'ptpemaco_apam' AND referenced_table_name IN ('employees', 'organizations')) AS fk_terpasang;
