-- READ-ONLY. Uji hasil aturan baru TANPA mengubah view. Ubah @tgl untuk mensimulasikan tanggal lain.
-- Bandingkan PUK lama (view divisions yang sekarang) dengan PUK menurut aturan baru.

SET NAMES utf8mb4;
SET @tgl = curdate();   -- contoh simulasi: SET @tgl = '2026-04-15';

SELECT n.organization_id, n.organization_name, o.PUK AS puk_lama, n.PUK AS puk_baru, IF(o.PUK <=> n.PUK, '', 'BERUBAH') AS status
FROM (
select `a`.`organization_id` AS `organization_id`,`a`.`board_id` AS `board_id`,`a`.`organization_name` AS `organization_name`,
  (select coalesce(
    coalesce(
        (select `p`.`employe_id` from `ptpemaco_hr_production`.`pic` `p`
           join `ptpemaco_hr_production`.`positions` `ps` on `ps`.`id` = `p`.`position_id`
           join `ptpemaco_hr_production`.`users` `u` on `u`.`employe_id` = `p`.`employe_id` and `u`.`active` = 1
          where `ps`.`division_id` = `a`.`organization_id` and `ps`.`position_level` = 'Manajer'
            and @tgl between `p`.`start_date` and `p`.`end_date`
          order by (`p`.`role_assignment` = 'as_pic') desc, `p`.`id` desc limit 1),
        (select `s`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` `s`
          where `s`.`organization_id` = `a`.`organization_id` and `s`.`real_base` = 7 limit 1)),
    coalesce(
        (select `p`.`employe_id` from `ptpemaco_hr_production`.`pic` `p`
           join `ptpemaco_hr_production`.`positions` `ps` on `ps`.`id` = `p`.`position_id`
           join `ptpemaco_hr_production`.`users` `u` on `u`.`employe_id` = `p`.`employe_id` and `u`.`active` = 1
          where `ps`.`division_id` = `a`.`organization_id` and `ps`.`position_level` = 'Supervisor'
            and @tgl between `p`.`start_date` and `p`.`end_date`
          order by (`p`.`role_assignment` = 'as_pic') desc, `p`.`id` desc limit 1),
        (select `s`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` `s`
          where `s`.`organization_id` = `a`.`organization_id` and `s`.`real_base` = 8 limit 1)),
    coalesce(
        (select `p`.`employe_id` from `ptpemaco_hr_production`.`pic` `p`
           join `ptpemaco_hr_production`.`positions` `ps` on `ps`.`id` = `p`.`position_id`
           join `ptpemaco_hr_production`.`users` `u` on `u`.`employe_id` = `p`.`employe_id` and `u`.`active` = 1
          where `ps`.`division_id` = `a`.`organization_id` and `ps`.`position_level` = 'Staf'
            and @tgl between `p`.`start_date` and `p`.`end_date`
          order by (`p`.`role_assignment` = 'as_pic') desc, `p`.`id` desc limit 1),
        (select `s`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` `s`
          where `s`.`organization_id` = `a`.`organization_id` and `s`.`real_base` = 9 limit 1))
  )) AS `PUK`,
  if(((select `ptpemaco_apam`.`struktur_lengkap_oke`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` where ((`ptpemaco_apam`.`struktur_lengkap_oke`.`board_id` = `a`.`board_id`) and (`ptpemaco_apam`.`struktur_lengkap_oke`.`id_base` = 4)) limit 1) is not null),(select `ptpemaco_apam`.`struktur_lengkap_oke`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` where ((`ptpemaco_apam`.`struktur_lengkap_oke`.`board_id` = `a`.`board_id`) and (`ptpemaco_apam`.`struktur_lengkap_oke`.`id_base` = 4)) limit 1),(select `ptpemaco_apam`.`struktur_lengkap_oke`.`employe_id` from `ptpemaco_apam`.`struktur_lengkap_oke` where (`ptpemaco_apam`.`struktur_lengkap_oke`.`position_id` = 6) limit 1)) AS `dir`,
  (select count(`ptpemaco_apam`.`project_stages`.`project_id`) from `ptpemaco_apam`.`project_stages` where ((`ptpemaco_apam`.`project_stages`.`division` = `a`.`organization_id`) and (year(`ptpemaco_apam`.`project_stages`.`end_date`) = year(curdate())))) AS `jumlah`
from `ptpemaco_apam`.`organizations` `a` where (`a`.`is_division` = 1)
) n
JOIN `ptpemaco_apam`.`divisions` o ON o.organization_id = n.organization_id
ORDER BY n.organization_id;
