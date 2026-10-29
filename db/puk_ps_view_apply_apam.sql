-- PUK: PS / PIC aktif ikut menjadi PUK (satu orang boleh PUK lebih dari satu divisi)
-- Target schema: ptpemaco_apam
-- Generated 2026-10-29. Menulis ulang 2 VIEW (tanpa mengubah data tabel). Lihat db/puk_ps_view_rollback_*.sql untuk membalikkan.

SET NAMES utf8mb4;

-- Aturan baru:
--   * PUK divisi = PIC/PS aktif hari ini (tabel ptpemaco_hr_production.pic, tanggal start_date..end_date) pada jabatan
--     Manajer divisi itu; kalau tidak ada, pemegang jabatan Manajer biasa; lalu urutan yang sama untuk Supervisor dan Staf.
--   * PIC didahulukan dari PS. Pegawai harus aktif (users.active = 1).
--   * struktur_lengkap_oke.position_name: teks dari tabel nota_dinas (jabatan tertulis manual, mis. 'Manajer X / PS. Manajer Agro')
--     tetap dipakai kalau ada. Kalau tidak ada nota_dinas aktif, otomatis ditambah ' / PS <jabatan>' (atau ' / PIC <jabatan>')
--     selama penugasan aktif, mis. 'Manajer X / PS Manajer Agro dan Jasa Lainnya'.
-- Dokumen yang sudah dibuat tidak berubah (langkah approval dibuat saat dokumen dibuat).

-- PERINGATAN: schema ptpemaco_apam adalah PRODUCTION. Jalankan dulu versi sysdev dan jalankan puk_ps_view_verify.sql.

CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`divisions` AS
select `a`.`organization_id` AS `organization_id`,`a`.`board_id` AS `board_id`,`a`.`organization_name` AS `organization_name`,
  (select coalesce(
    coalesce(
        (select `p`.`employe_id` from `ptpemaco_hr_production`.`pic` `p`
           join `ptpemaco_hr_production`.`positions` `ps` on `ps`.`id` = `p`.`position_id`
           join `ptpemaco_hr_production`.`users` `u` on `u`.`employe_id` = `p`.`employe_id` and `u`.`active` = 1
          where `ps`.`division_id` = `a`.`organization_id` and `ps`.`position_level` = 'Manajer'
            and curdate() between `p`.`start_date` and `p`.`end_date`
          order by (`p`.`role_assignment` = 'as_pic') desc, `p`.`id` desc limit 1),
        (select `s`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` `s`
          where `s`.`organization_id` = `a`.`organization_id` and `s`.`real_base` = 7 limit 1)),
    coalesce(
        (select `p`.`employe_id` from `ptpemaco_hr_production`.`pic` `p`
           join `ptpemaco_hr_production`.`positions` `ps` on `ps`.`id` = `p`.`position_id`
           join `ptpemaco_hr_production`.`users` `u` on `u`.`employe_id` = `p`.`employe_id` and `u`.`active` = 1
          where `ps`.`division_id` = `a`.`organization_id` and `ps`.`position_level` = 'Supervisor'
            and curdate() between `p`.`start_date` and `p`.`end_date`
          order by (`p`.`role_assignment` = 'as_pic') desc, `p`.`id` desc limit 1),
        (select `s`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` `s`
          where `s`.`organization_id` = `a`.`organization_id` and `s`.`real_base` = 8 limit 1)),
    coalesce(
        (select `p`.`employe_id` from `ptpemaco_hr_production`.`pic` `p`
           join `ptpemaco_hr_production`.`positions` `ps` on `ps`.`id` = `p`.`position_id`
           join `ptpemaco_hr_production`.`users` `u` on `u`.`employe_id` = `p`.`employe_id` and `u`.`active` = 1
          where `ps`.`division_id` = `a`.`organization_id` and `ps`.`position_level` = 'Staf'
            and curdate() between `p`.`start_date` and `p`.`end_date`
          order by (`p`.`role_assignment` = 'as_pic') desc, `p`.`id` desc limit 1),
        (select `s`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` `s`
          where `s`.`organization_id` = `a`.`organization_id` and `s`.`real_base` = 9 limit 1))
  )) AS `PUK`,
  if(((select `ptpemaco_apam`.`struktur_lengkap_oke`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` where ((`ptpemaco_apam`.`struktur_lengkap_oke`.`board_id` = `a`.`board_id`) and (`ptpemaco_apam`.`struktur_lengkap_oke`.`id_base` = 4)) limit 1) is not null),(select `ptpemaco_apam`.`struktur_lengkap_oke`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` where ((`ptpemaco_apam`.`struktur_lengkap_oke`.`board_id` = `a`.`board_id`) and (`ptpemaco_apam`.`struktur_lengkap_oke`.`id_base` = 4)) limit 1),(select `ptpemaco_apam`.`struktur_lengkap_oke`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` where (`ptpemaco_apam`.`struktur_lengkap_oke`.`position_id` = 6) limit 1)) AS `dir`,
  (select count(`ptpemaco_apam`.`project_stages`.`project_id`) from `ptpemaco_apam`.`project_stages` where ((`ptpemaco_apam`.`project_stages`.`division` = `a`.`organization_id`) and (year(`ptpemaco_apam`.`project_stages`.`end_date`) = year(curdate())))) AS `jumlah`
from `ptpemaco_apam`.`organizations` `a` where (`a`.`is_division` = 1);

CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`struktur_lengkap_oke` AS
select coalesce(`l1`.`employe_id`,`l2`.`employe_id`,`l3`.`employe_id`,`l4`.`employe_id`,`l5`.`employe_id`) AS `direct_atasan`,`l1`.`employe_id` AS `level_1`,`l2`.`employe_id` AS `level_2`,`l3`.`employe_id` AS `level_3`,`l4`.`employe_id` AS `level_4`,`l5`.`employe_id` AS `level_5`,`a`.`employe_id` AS `employe_id`,`a`.`user_id` AS `user_id`,`a`.`position_id` AS `position_id`,coalesce((select `nd`.`jabatan` from `ptpemaco_apam`.`nota_dinas` `nd` where ((`nd`.`id_employee` = `a`.`employe_id`) and (curdate() between `nd`.`from_` and `nd`.`to_`)) limit 1),concat(`c`.`position_name`,coalesce((select concat(' / ', if(`pc`.`role_assignment` = 'as_pic', 'PIC', 'PS'), ' ', `pp`.`position_name`) from `ptpemaco_hr_production`.`pic` `pc` join `ptpemaco_hr_production`.`positions` `pp` on `pp`.`id` = `pc`.`position_id` where `pc`.`employe_id` = `a`.`employe_id` and curdate() between `pc`.`start_date` and `pc`.`end_date` order by (`pc`.`role_assignment` = 'as_pic') desc, `pc`.`id` desc limit 1),''))) AS `position_name`,coalesce((select `nd`.`id_base` from `ptpemaco_apam`.`nota_dinas` `nd` where ((`nd`.`id_employee` = `a`.`employe_id`) and (curdate() between `nd`.`from_` and `nd`.`to_`)) limit 1),`c`.`id_base`) AS `real_base`,`c`.`id_base` AS `id_base`,`z`.`base_name` AS `base_name`,`z`.`rate_sppd` AS `rate_sppd`,`z`.`nomor_sppd` AS `nomor_sppd`,`c`.`organization_id` AS `organization_id`,`d`.`organization_name` AS `organization_name`,`d`.`board_id` AS `board_id`,`e`.`board_name` AS `board_name`,`a`.`as_pic` AS `as_pic`,`a`.`first_name` AS `first_name`,`a`.`gender` AS `gender`,`a`.`religion` AS `religion`,`a`.`birthday` AS `birthday`,`a`.`birthday_place` AS `birthday_place`,`a`.`marital_status` AS `marital_status`,`a`.`img` AS `img`,`a`.`employe_active` AS `employe_active`,`b`.`email` AS `email`,`b`.`roles` AS `roles`,concat('https://hr-api.ptpema.co.id/storage/photo/employee-photo/',(select `epd`.`photo` from `ptpemaco_hr_production`.`employee_personal_data` `epd` where (`epd`.`employe_id` = `a`.`employe_id`) limit 1)) AS `photo`,(select (case when regexp_like(`epd`.`phone_number`,'^08[0-9]{8,12}$') then concat('628',substr(`epd`.`phone_number`,3)) when regexp_like(`epd`.`phone_number`,'^628[0-9]{7,12}$') then `epd`.`phone_number` when regexp_like(`epd`.`phone_number`,'^\\+[1-9][0-9]{6,14}$') then `epd`.`phone_number` when regexp_like(`epd`.`phone_number`,'^[1-9][0-9]{7,14}$') then `epd`.`phone_number` else NULL end) from `ptpemaco_hr_production`.`employee_personal_data` `epd` where (`epd`.`employe_id` = `a`.`employe_id`) limit 1) AS `phone_number` from (((((((((((((((`ptpemaco_apam`.`employees` `a` join `ptpemaco_apam`.`users` `b` on((`a`.`user_id` = `b`.`id`))) join `ptpemaco_apam`.`positions` `c` on((`a`.`position_id` = `c`.`position_id`))) join `ptpemaco_apam`.`base_positions` `z` on((`c`.`id_base` = `z`.`id`))) join `ptpemaco_apam`.`organizations` `d` on((`c`.`organization_id` = `d`.`organization_id`))) join `ptpemaco_apam`.`board_organizations` `e` on((`d`.`board_id` = `e`.`board_id`))) join `ptpemaco_apam`.`struktur` `f` on((`f`.`position_id` = `a`.`position_id`))) left join `ptpemaco_apam`.`employees` `l1` on((`l1`.`position_id` = `f`.`direct_supervisor`))) left join `ptpemaco_apam`.`struktur` `s2` on((`s2`.`position_id` = `f`.`direct_supervisor`))) left join `ptpemaco_apam`.`employees` `l2` on((`l2`.`position_id` = `s2`.`direct_supervisor`))) left join `ptpemaco_apam`.`struktur` `s3` on((`s3`.`position_id` = `s2`.`direct_supervisor`))) left join `ptpemaco_apam`.`employees` `l3` on((`l3`.`position_id` = `s3`.`direct_supervisor`))) left join `ptpemaco_apam`.`struktur` `s4` on((`s4`.`position_id` = `s3`.`direct_supervisor`))) left join `ptpemaco_apam`.`employees` `l4` on((`l4`.`position_id` = `s4`.`direct_supervisor`))) left join `ptpemaco_apam`.`struktur` `s5` on((`s5`.`position_id` = `s4`.`direct_supervisor`))) left join `ptpemaco_apam`.`employees` `l5` on((`l5`.`position_id` = `s5`.`direct_supervisor`)));

-- Contoh data PS (JANGAN dijalankan otomatis; sesuaikan tanggal). Tanpa baris PS aktif, hasil view sama seperti sebelumnya.
-- INSERT INTO `ptpemaco_hr_production`.`pic` (employe_id, position_id, role_assignment, start_date, end_date, created_at, updated_at)
-- VALUES ('202506114K', 16, 'as_ps', '2026-11-01', '2026-11-30', NOW(), NOW());
