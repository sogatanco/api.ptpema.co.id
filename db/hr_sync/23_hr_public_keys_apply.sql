-- Public key tanda tangan elektronik karyawan dipusatkan di HR.
-- Latar belakang: `public_key` (RSA, dipakai login/e-sign: AuthController, CryptoService, SignatureService) hanya ada di apam.employees (22 baris terisi).
-- HR belum punya kolom/tabel untuk itu. Skrip ini:
--   1. membuat tabel BARU ptpemaco_hr_production.employee_public_keys (employe_id PK + FK ke users(employe_id) ON DELETE CASCADE, public_key, timestamps);
--      tidak mengubah tabel HR yang ada. Dibuat tanpa IF NOT EXISTS: bila dijalankan dua kali berhenti di sini (pengaman).
--   2. menyalin public_key dari apam.employees untuk karyawan yang ada di HR (INSERT IGNORE: isi HR yang sudah ada tidak ditimpa).
--   3. mengganti view apam.employees_hr agar public_key = isi HR, cadangan isi apam.employees.
-- Yang TIDAK pindah: private key tetap berkas terenkripsi di storage/app/keys/private_<employe_id>.pem server apam (bukan di database).
-- Prasyarat: 21_employees_hr_view.sql sudah dipasang. Kode Laravel branch synch (CryptoService) menulis key baru ke HR dan ke apam.employees (kompatibel mundur).
-- Jalankan oleh/atas persetujuan HR:  mysql --comments -u <user> -p < 23_hr_public_keys_apply.sql      Rollback: 24_hr_public_keys_rollback.sql

DROP PROCEDURE IF EXISTS ptpemaco_apam.tmp_pk_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_apam.tmp_pk_precheck()
BEGIN
  DECLARE v INT;
  SELECT COUNT(*) INTO v FROM information_schema.views WHERE table_schema = 'ptpemaco_apam' AND table_name = 'employees_hr';
  IF v <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '21_employees_hr_view.sql belum dipasang (view employees_hr tidak ada)';
  END IF;
  SELECT COUNT(*) INTO v FROM information_schema.tables WHERE table_schema = 'ptpemaco_hr_production' AND table_name = 'employee_public_keys';
  IF v <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabel employee_public_keys sudah ada; skrip ini pernah dijalankan';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_apam.tmp_pk_precheck();
DROP PROCEDURE ptpemaco_apam.tmp_pk_precheck;

-- 1. Tabel di HR
CREATE TABLE ptpemaco_hr_production.employee_public_keys (
  employe_id varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  public_key text COLLATE utf8mb4_unicode_ci NOT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (employe_id),
  CONSTRAINT employee_public_keys_employe_id_foreign FOREIGN KEY (employe_id) REFERENCES ptpemaco_hr_production.users (employe_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Salin key yang sudah ada
INSERT IGNORE INTO ptpemaco_hr_production.employee_public_keys (employe_id, public_key, created_at, updated_at)
SELECT h.employe_id, e.public_key, NOW(), NOW()
FROM ptpemaco_apam.employees e
JOIN ptpemaco_hr_production.users h ON h.employe_id COLLATE utf8mb4_unicode_ci = e.employe_id COLLATE utf8mb4_unicode_ci
WHERE e.public_key IS NOT NULL AND e.public_key <> '';

-- 3. View employees_hr: public_key dari HR (cadangan apam.employees)
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`ptpemaco`@`localhost` SQL SECURITY DEFINER VIEW `ptpemaco_apam`.`employees_hr` AS
select `u`.`employe_id` AS `employe_id`,
       coalesce(`k`.`public_key`, `e`.`public_key`) AS `public_key`,
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
left join `ptpemaco_hr_production`.`employee_public_keys` `k` on (`k`.`employe_id` collate utf8mb4_unicode_ci = `u`.`employe_id` collate utf8mb4_unicode_ci)
left join `ptpemaco_apam`.`employees` `e` on (`e`.`employe_id` collate utf8mb4_unicode_ci = `u`.`employe_id` collate utf8mb4_unicode_ci)
union all
select `e`.`employe_id`, `e`.`public_key`, `e`.`nik`, `e`.`user_id`, NULL, `e`.`as_pic`, `e`.`first_name`, `e`.`last_name`,
       `e`.`gender`, `e`.`religion`, `e`.`birthday`, `e`.`birthday_place`, `e`.`marital_status`, `e`.`img`, `e`.`employe_active`,
       `e`.`created_at`, `e`.`updated_at`
from `ptpemaco_apam`.`employees` `e`
where not exists (select 1 from `ptpemaco_hr_production`.`users` `h` where `h`.`employe_id` collate utf8mb4_unicode_ci = `e`.`employe_id` collate utf8mb4_unicode_ci);

-- Laporan: tersalin harus = key terisi milik karyawan yang ada di HR; tidak_ikut = key apam yang tidak punya karyawan HR (tetap di apam)
SELECT (SELECT COUNT(*) FROM ptpemaco_hr_production.employee_public_keys) AS tersalin,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees WHERE public_key IS NOT NULL AND public_key <> '') AS key_di_apam,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees e LEFT JOIN ptpemaco_hr_production.users h ON h.employe_id COLLATE utf8mb4_unicode_ci = e.employe_id COLLATE utf8mb4_unicode_ci
         WHERE e.public_key IS NOT NULL AND e.public_key <> '' AND h.id IS NULL) AS tidak_ikut,
       (SELECT COUNT(*) FROM ptpemaco_apam.employees_hr WHERE public_key IS NOT NULL AND public_key <> '') AS terbaca_lewat_view;
