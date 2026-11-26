-- Cuti "Dinas Luar Kota" otomatis dari SPPD (43/45/47): dua pengecualian.
--   1. SPPD (dan perpanjangannya) milik Direktur / Komisaris yang disetujui TIDAK dimasukkan ke cuti HR.
--      Dikenali dari jabatan di ptpemaco_apam.struktur_lengkap_oke: 'Direktur', 'Direktur ...', 'Komisaris', 'Komisaris Utama'
--      ('Sekretaris Komisaris' bukan komisaris, tetap masuk cuti). Fungsi: ptpemaco_sppdnew.karyawan_direksi_komisaris(employe_id).
--      Hanya berlaku untuk persetujuan berikutnya; cuti Direktur/Komisaris yang sudah terlanjur dibuat TIDAK dihapus (lihat laporan akhir).
--   2. SPPD yang di-VOID: cuti SPPD itu beserta cuti perpanjangannya dihapus dari HR (trigger sppd_cuti_dinas_void pada sppd_void),
--      dan SPPD void tidak akan dibuatkan cuti bila disetujui. Cuti dari SPPD yang SUDAH void sebelum skrip ini juga dibersihkan (sekali jalan).
-- Hanya baris yang tercatat di sppd_cuti_dinas / sppd_cuti_dinas_ekstend (dibuat otomatis) yang dihapus, dan hanya master_id = 3.
-- Pengajuan dinas luar kota yang diisi karyawan sendiri tidak disentuh.
-- Prasyarat: 43, 45, dan 47 sudah dijalankan. Hak tambahan untuk user definer trigger:
--   GRANT SELECT ON ptpemaco_apam.struktur_lengkap_oke TO 'ptpemaco'@'localhost';
-- WAJIB:  mysql --comments -u <user> -p < sppd_cuti_dinas_pengecualian.sql     Rollback: sppd_cuti_dinas_pengecualian_rollback.sql
--   (rollback mengembalikan trigger 43/45 semula; cuti yang sudah dihapus karena void tidak dikembalikan)

DROP PROCEDURE IF EXISTS ptpemaco_sppdnew.tmp_cuti_pengecualian_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_sppdnew.tmp_cuti_pengecualian_precheck()
BEGIN
  DECLARE v INT;
  SELECT COUNT(*) INTO v FROM information_schema.triggers WHERE trigger_schema = 'ptpemaco_esign' AND trigger_name IN ('sppd_cuti_dinas', 'sppd_cuti_dinas_ekstend');
  IF v <> 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jalankan 43 dan 45 dulu (trigger sppd_cuti_dinas/sppd_cuti_dinas_ekstend belum ada)'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.routines WHERE routine_schema = 'ptpemaco_sppdnew' AND routine_name = 'hapus_cuti_dinas_sppd';
  IF v <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jalankan 47 dulu (prosedur hapus_cuti_dinas_sppd belum ada)'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.tables WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'sppd_void';
  IF v <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabel sppd_void belum ada'; END IF;
END$$
DELIMITER ;
CALL ptpemaco_sppdnew.tmp_cuti_pengecualian_precheck();
DROP PROCEDURE ptpemaco_sppdnew.tmp_cuti_pengecualian_precheck;

-- 1. fungsi pengenal Direktur / Komisaris
DROP FUNCTION IF EXISTS ptpemaco_sppdnew.karyawan_direksi_komisaris;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` FUNCTION `ptpemaco_sppdnew`.`karyawan_direksi_komisaris`(p_emp VARCHAR(255)) RETURNS TINYINT(1)
    READS SQL DATA
BEGIN
  RETURN EXISTS (
    SELECT 1 FROM ptpemaco_apam.struktur_lengkap_oke s
     WHERE s.employe_id = p_emp COLLATE utf8mb4_unicode_ci
       AND (TRIM(s.position_name) = 'Direktur' OR TRIM(s.position_name) LIKE 'Direktur %' OR TRIM(s.position_name) LIKE 'Komisaris%')
  );
END$$
DELIMITER ;

-- 2. trigger persetujuan SPPD dan perpanjangan: lewati Direktur/Komisaris dan SPPD void
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`sppd_cuti_dinas`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_esign`.`sppd_cuti_dinas` AFTER UPDATE ON `ptpemaco_esign`.`verif_steps` FOR EACH ROW
BEGIN
  DECLARE v_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_nomor VARCHAR(255) DEFAULT NULL;
  DECLARE v_narasi VARCHAR(1000) DEFAULT NULL;
  DECLARE v_emp VARCHAR(255) DEFAULT NULL;
  DECLARE v_sub VARCHAR(255) DEFAULT NULL;
  DECLARE v_start DATE DEFAULT NULL;
  DECLARE v_end DATE DEFAULT NULL;
  DECLARE v_days INT DEFAULT 0;
  DECLARE v_req BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_dup BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_creator VARCHAR(255) DEFAULT NULL;
  DECLARE v_approver VARCHAR(255) DEFAULT NULL;
  DECLARE v_reason VARCHAR(255) DEFAULT NULL;
  DECLARE v_msg TEXT DEFAULT NULL;
  -- galat apa pun dicatat; tanda tangan SPPD tidak boleh gagal karenanya
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT;
    IF v_id IS NOT NULL THEN
      REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas (id_sppd, nomor_dokumen, request_time_off_id, hasil)
      VALUES (v_id, NEW.id_doc, v_req, LEFT(CONCAT('galat: ', IFNULL(v_msg, '')), 500));
    END IF;
  END;

  IF NEW.type = 'sign' AND NEW.status = 'approve' AND (OLD.status IS NULL OR OLD.status <> 'approve') THEN

    SELECT s.id, s.nomor_sppd, s.narasi_st, s.employe_id, s.submitted_by
      INTO v_id, v_nomor, v_narasi, v_emp, v_sub
    FROM ptpemaco_sppdnew.sppds s
    WHERE s.nomor_dokumen = NEW.id_doc COLLATE utf8mb4_unicode_ci
    LIMIT 1;

    IF v_id IS NOT NULL THEN

      SELECT MIN(DATE(t.waktu_berangkat)), MAX(DATE(t.waktu_kembali)) INTO v_start, v_end
      FROM ptpemaco_sppdnew.tujuan_sppds t WHERE t.id_sppd = v_id;

      SELECT c.request_time_off_id INTO v_req FROM ptpemaco_sppdnew.sppd_cuti_dinas c WHERE c.id_sppd = v_id;

      IF v_emp IS NULL OR v_emp = '-' THEN
        REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas (id_sppd, nomor_dokumen, request_time_off_id, hasil)
        VALUES (v_id, NEW.id_doc, v_req, 'dilewati: SPPD tanpa karyawan tertentu');
      ELSEIF ptpemaco_sppdnew.karyawan_direksi_komisaris(v_emp) = 1 THEN
        REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas (id_sppd, nomor_dokumen, request_time_off_id, hasil)
        VALUES (v_id, NEW.id_doc, v_req, 'dilewati: direktur/komisaris tidak dimasukkan ke cuti');
      ELSEIF EXISTS (SELECT 1 FROM ptpemaco_sppdnew.sppd_void sv WHERE sv.sppd_id = v_id) THEN
        REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas (id_sppd, nomor_dokumen, request_time_off_id, hasil)
        VALUES (v_id, NEW.id_doc, v_req, 'dilewati: SPPD void');
      ELSEIF v_start IS NULL OR v_end IS NULL OR v_end < v_start THEN
        REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas (id_sppd, nomor_dokumen, request_time_off_id, hasil)
        VALUES (v_id, NEW.id_doc, v_req, 'dilewati: tanggal tujuan tidak ada atau tidak valid');
      ELSEIF NOT EXISTS (SELECT 1 FROM ptpemaco_hr_production.users u WHERE u.employe_id = v_emp COLLATE utf8mb4_unicode_ci) THEN
        REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas (id_sppd, nomor_dokumen, request_time_off_id, hasil)
        VALUES (v_id, NEW.id_doc, v_req, 'dilewati: karyawan tidak ada di users HR');
      ELSE
        IF v_end > v_start + INTERVAL 365 DAY THEN
          SET v_end = v_start + INTERVAL 365 DAY;
        END IF;

        -- hari kerja Senin-Jumat dikurangi hari libur HR, minimal 1
        SELECT COUNT(*) INTO v_days FROM (
          WITH RECURSIVE d (dt) AS (
            SELECT v_start UNION ALL SELECT dt + INTERVAL 1 DAY FROM d WHERE dt < v_end
          )
          SELECT dt FROM d
        ) x
        WHERE WEEKDAY(x.dt) < 5
          AND NOT EXISTS (SELECT 1 FROM ptpemaco_hr_production.holidays h WHERE h.holiday_date = x.dt AND h.is_deleted = 0);
        IF v_days < 1 THEN SET v_days = 1; END IF;

        SET v_reason = LEFT(CONCAT('SPPD ', IFNULL(v_nomor, ''), ' - ', IFNULL(v_narasi, '')), 255);
        SET v_creator = IFNULL((SELECT u.employe_id FROM ptpemaco_hr_production.users u WHERE u.employe_id = v_sub COLLATE utf8mb4_unicode_ci LIMIT 1), v_emp);
        SET v_approver = (SELECT u.employe_id FROM ptpemaco_hr_production.users u WHERE u.employe_id = NEW.id_employe COLLATE utf8mb4_unicode_ci LIMIT 1);

        IF v_req IS NOT NULL AND EXISTS (SELECT 1 FROM ptpemaco_hr_production.request_time_offs r WHERE r.id = v_req) THEN
          -- SPPD disetujui ulang (setelah diedit): perbarui pengajuan yang sama
          UPDATE ptpemaco_hr_production.request_time_offs
             SET employe_id = v_emp, date_start = v_start, date_end = v_end, total_day_taken = v_days, reason = v_reason,
                 status = 'approved', approved_by = v_approver, cancelled_by = NULL, updated_at = NOW()
           WHERE id = v_req;
          REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas (id_sppd, nomor_dokumen, request_time_off_id, hasil)
          VALUES (v_id, NEW.id_doc, v_req, CONCAT('diperbarui: ', v_start, ' s/d ', v_end, ', ', v_days, ' hari'));
        ELSE
          SELECT r.id INTO v_dup FROM ptpemaco_hr_production.request_time_offs r
           WHERE r.master_id = 3 AND r.employe_id = v_emp COLLATE utf8mb4_unicode_ci AND r.status IN ('pending', 'approved')
             AND r.date_start <= v_end AND r.date_end >= v_start
           ORDER BY r.id LIMIT 1;
          IF v_dup IS NOT NULL THEN
            REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas (id_sppd, nomor_dokumen, request_time_off_id, hasil)
            VALUES (v_id, NEW.id_doc, NULL, CONCAT('dilewati: sudah ada pengajuan dinas luar kota #', v_dup, ' pada tanggal yang sama'));
          ELSE
            INSERT INTO ptpemaco_hr_production.request_time_offs
              (master_id, sub_master_id, employe_id, date_start, date_end, excluded_dates, type, reason, total_day_taken, delegate_to, attachment,
               status, created_by, approved_by, cancelled_by, is_manual_form, created_at, updated_at, used_from_carry_over, used_from_quota)
            VALUES
              (3, NULL, v_emp, v_start, v_end, NULL, 'Full Day', v_reason, v_days, NULL, NULL,
               'approved', v_creator, v_approver, NULL, 0, NOW(), NOW(), 0, 0);
            SET v_req = LAST_INSERT_ID();
            REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas (id_sppd, nomor_dokumen, request_time_off_id, hasil)
            VALUES (v_id, NEW.id_doc, v_req, CONCAT('dibuat: ', v_start, ' s/d ', v_end, ', ', v_days, ' hari'));
          END IF;
        END IF;
      END IF;

    END IF;
  END IF;
END$$
DELIMITER ;
SET SESSION sql_mode = DEFAULT;

SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`sppd_cuti_dinas_ekstend`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_esign`.`sppd_cuti_dinas_ekstend` AFTER UPDATE ON `ptpemaco_esign`.`verif_steps` FOR EACH ROW
BEGIN
  DECLARE v_done INT DEFAULT 0;
  DECLARE v_id BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_nomor VARCHAR(255) DEFAULT NULL;
  DECLARE v_emp VARCHAR(255) DEFAULT NULL;
  DECLARE v_tujuan BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_start DATE DEFAULT NULL;
  DECLARE v_end DATE DEFAULT NULL;
  DECLARE v_alasan LONGTEXT DEFAULT NULL;
  DECLARE v_sub VARCHAR(255) DEFAULT NULL;
  DECLARE v_days INT DEFAULT 0;
  DECLARE v_req BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_dup BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_creator VARCHAR(255) DEFAULT NULL;
  DECLARE v_approver VARCHAR(255) DEFAULT NULL;
  DECLARE v_reason VARCHAR(255) DEFAULT NULL;
  DECLARE v_msg TEXT DEFAULT NULL;
  DECLARE cur CURSOR FOR
    SELECT e.id_tujuan, e.start, e.`end`, e.alasan, e.submitted_by
    FROM ptpemaco_sppdnew.ekstends e
    JOIN ptpemaco_sppdnew.tujuan_sppds t ON t.id = e.id_tujuan
    WHERE t.id_sppd = v_id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;
  -- galat apa pun dicatat; persetujuan perpanjangan tidak boleh gagal karenanya
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT;
    IF v_tujuan IS NOT NULL THEN
      REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas_ekstend (id_tujuan, nomor_dokumen, request_time_off_id, hasil)
      VALUES (v_tujuan, NEW.id_doc, v_req, LEFT(CONCAT('galat: ', IFNULL(v_msg, '')), 500));
    END IF;
  END;

  IF NEW.type = 'extend' AND NEW.status = 'approve' AND (OLD.status IS NULL OR OLD.status <> 'approve') THEN

    SELECT s.id, s.nomor_sppd, s.employe_id INTO v_id, v_nomor, v_emp
    FROM ptpemaco_sppdnew.sppds s
    WHERE s.nomor_dokumen = NEW.id_doc COLLATE utf8mb4_unicode_ci
    LIMIT 1;

    IF v_id IS NOT NULL THEN
      SET v_approver = (SELECT u.employe_id FROM ptpemaco_hr_production.users u WHERE u.employe_id = NEW.id_employe COLLATE utf8mb4_unicode_ci LIMIT 1);

      OPEN cur;
      ekstend_loop: LOOP
        FETCH cur INTO v_tujuan, v_start, v_end, v_alasan, v_sub;
        IF v_done = 1 THEN LEAVE ekstend_loop; END IF;
        SET v_req = NULL;
        SET v_dup = NULL;
        SELECT c.request_time_off_id INTO v_req FROM ptpemaco_sppdnew.sppd_cuti_dinas_ekstend c WHERE c.id_tujuan = v_tujuan;
        -- SELECT ... INTO tanpa baris menandai NOT FOUND; jangan hentikan loop karenanya
        SET v_done = 0;

        IF v_emp IS NULL OR v_emp = '-' THEN
          REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas_ekstend (id_tujuan, nomor_dokumen, request_time_off_id, hasil)
          VALUES (v_tujuan, NEW.id_doc, v_req, 'dilewati: SPPD tanpa karyawan tertentu');
        ELSEIF ptpemaco_sppdnew.karyawan_direksi_komisaris(v_emp) = 1 THEN
          REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas_ekstend (id_tujuan, nomor_dokumen, request_time_off_id, hasil)
          VALUES (v_tujuan, NEW.id_doc, v_req, 'dilewati: direktur/komisaris tidak dimasukkan ke cuti');
        ELSEIF EXISTS (SELECT 1 FROM ptpemaco_sppdnew.sppd_void sv WHERE sv.sppd_id = v_id) THEN
          REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas_ekstend (id_tujuan, nomor_dokumen, request_time_off_id, hasil)
          VALUES (v_tujuan, NEW.id_doc, v_req, 'dilewati: SPPD void');
        ELSEIF v_start IS NULL OR v_end IS NULL OR v_end < v_start THEN
          REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas_ekstend (id_tujuan, nomor_dokumen, request_time_off_id, hasil)
          VALUES (v_tujuan, NEW.id_doc, v_req, 'dilewati: tanggal perpanjangan tidak valid');
        ELSEIF NOT EXISTS (SELECT 1 FROM ptpemaco_hr_production.users u WHERE u.employe_id = v_emp COLLATE utf8mb4_unicode_ci) THEN
          REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas_ekstend (id_tujuan, nomor_dokumen, request_time_off_id, hasil)
          VALUES (v_tujuan, NEW.id_doc, v_req, 'dilewati: karyawan tidak ada di users HR');
        ELSE
          IF v_end > v_start + INTERVAL 365 DAY THEN
            SET v_end = v_start + INTERVAL 365 DAY;
          END IF;

          -- hari kerja Senin-Jumat dikurangi hari libur HR, minimal 1
          SELECT COUNT(*) INTO v_days FROM (
            WITH RECURSIVE d (dt) AS (
              SELECT v_start UNION ALL SELECT dt + INTERVAL 1 DAY FROM d WHERE dt < v_end
            )
            SELECT dt FROM d
          ) x
          WHERE WEEKDAY(x.dt) < 5
            AND NOT EXISTS (SELECT 1 FROM ptpemaco_hr_production.holidays h WHERE h.holiday_date = x.dt AND h.is_deleted = 0);
          IF v_days < 1 THEN SET v_days = 1; END IF;

          SET v_reason = LEFT(CONCAT('Perpanjangan SPPD ', IFNULL(v_nomor, ''), ' - ', IFNULL(v_alasan, '')), 255);
          SET v_creator = IFNULL((SELECT u.employe_id FROM ptpemaco_hr_production.users u WHERE u.employe_id = v_sub COLLATE utf8mb4_unicode_ci LIMIT 1), v_emp);

          IF v_req IS NOT NULL AND EXISTS (SELECT 1 FROM ptpemaco_hr_production.request_time_offs r WHERE r.id = v_req) THEN
            -- perpanjangan disetujui ulang: perbarui pengajuan yang sama
            UPDATE ptpemaco_hr_production.request_time_offs
               SET employe_id = v_emp, date_start = v_start, date_end = v_end, total_day_taken = v_days, reason = v_reason,
                   status = 'approved', approved_by = v_approver, cancelled_by = NULL, updated_at = NOW()
             WHERE id = v_req;
            REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas_ekstend (id_tujuan, nomor_dokumen, request_time_off_id, hasil)
            VALUES (v_tujuan, NEW.id_doc, v_req, CONCAT('diperbarui: ', v_start, ' s/d ', v_end, ', ', v_days, ' hari'));
          ELSE
            SELECT r.id INTO v_dup FROM ptpemaco_hr_production.request_time_offs r
             WHERE r.master_id = 3 AND r.employe_id = v_emp COLLATE utf8mb4_unicode_ci AND r.status IN ('pending', 'approved')
               AND r.date_start <= v_end AND r.date_end >= v_start
             ORDER BY r.id LIMIT 1;
            SET v_done = 0;
            IF v_dup IS NOT NULL THEN
              REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas_ekstend (id_tujuan, nomor_dokumen, request_time_off_id, hasil)
              VALUES (v_tujuan, NEW.id_doc, NULL, CONCAT('dilewati: sudah ada pengajuan dinas luar kota #', v_dup, ' pada tanggal yang sama'));
            ELSE
              INSERT INTO ptpemaco_hr_production.request_time_offs
                (master_id, sub_master_id, employe_id, date_start, date_end, excluded_dates, type, reason, total_day_taken, delegate_to, attachment,
                 status, created_by, approved_by, cancelled_by, is_manual_form, created_at, updated_at, used_from_carry_over, used_from_quota)
              VALUES
                (3, NULL, v_emp, v_start, v_end, NULL, 'Full Day', v_reason, v_days, NULL, NULL,
                 'approved', v_creator, v_approver, NULL, 0, NOW(), NOW(), 0, 0);
              SET v_req = LAST_INSERT_ID();
              REPLACE INTO ptpemaco_sppdnew.sppd_cuti_dinas_ekstend (id_tujuan, nomor_dokumen, request_time_off_id, hasil)
              VALUES (v_tujuan, NEW.id_doc, v_req, CONCAT('dibuat: ', v_start, ' s/d ', v_end, ', ', v_days, ' hari'));
            END IF;
          END IF;
        END IF;
      END LOOP;
      CLOSE cur;
    END IF;
  END IF;
END$$
DELIMITER ;
SET SESSION sql_mode = DEFAULT;

-- 3. SPPD di-void -> hapus cuti-nya (dan cuti perpanjangannya) bila ada
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`sppd_cuti_dinas_void`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`sppd_cuti_dinas_void` AFTER INSERT ON `ptpemaco_sppdnew`.`sppd_void` FOR EACH ROW
BEGIN
  CALL ptpemaco_sppdnew.hapus_cuti_dinas_sppd(NEW.sppd_id, IFNULL((SELECT s.nomor_dokumen FROM ptpemaco_sppdnew.sppds s WHERE s.id = NEW.sppd_id), ''));
END$$
DELIMITER ;

-- 4. pembersihan sekali jalan: cuti dari SPPD yang sudah void sebelum skrip ini (DELETE multi-tabel beralias butuh database aktif)
USE ptpemaco_sppdnew;
DELETE r FROM ptpemaco_hr_production.request_time_offs r
  JOIN ptpemaco_sppdnew.sppd_cuti_dinas c ON c.request_time_off_id = r.id
  JOIN ptpemaco_sppdnew.sppd_void v ON v.sppd_id = c.id_sppd
 WHERE r.master_id = 3;
DELETE c FROM ptpemaco_sppdnew.sppd_cuti_dinas c JOIN ptpemaco_sppdnew.sppd_void v ON v.sppd_id = c.id_sppd;
DELETE r FROM ptpemaco_hr_production.request_time_offs r
  JOIN ptpemaco_sppdnew.sppd_cuti_dinas_ekstend c ON c.request_time_off_id = r.id
  JOIN ptpemaco_sppdnew.sppds s ON s.nomor_dokumen = c.nomor_dokumen COLLATE utf8mb4_unicode_ci
  JOIN ptpemaco_sppdnew.sppd_void v ON v.sppd_id = s.id
 WHERE r.master_id = 3;
DELETE c FROM ptpemaco_sppdnew.sppd_cuti_dinas_ekstend c
  JOIN ptpemaco_sppdnew.sppds s ON s.nomor_dokumen = c.nomor_dokumen COLLATE utf8mb4_unicode_ci
  JOIN ptpemaco_sppdnew.sppd_void v ON v.sppd_id = s.id;

-- Laporan: trigger_ada = 3, fungsi_ada = 1, void_masih_ada_cuti = 0,
-- cuti_direksi_terlanjur = jumlah cuti Direktur/Komisaris yang sudah dibuat sebelumnya (tidak dihapus; periksa bila perlu)
SELECT (SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_name IN ('sppd_cuti_dinas', 'sppd_cuti_dinas_ekstend', 'sppd_cuti_dinas_void')) AS trigger_ada,
       (SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = 'ptpemaco_sppdnew' AND routine_name = 'karyawan_direksi_komisaris') AS fungsi_ada,
       (SELECT COUNT(*) FROM ptpemaco_sppdnew.sppd_cuti_dinas c JOIN ptpemaco_sppdnew.sppd_void v ON v.sppd_id = c.id_sppd) AS void_masih_ada_cuti,
       (SELECT COUNT(*) FROM ptpemaco_sppdnew.sppd_cuti_dinas c JOIN ptpemaco_sppdnew.sppds s ON s.id = c.id_sppd
         WHERE c.request_time_off_id IS NOT NULL AND ptpemaco_sppdnew.karyawan_direksi_komisaris(s.employe_id) = 1) AS cuti_direksi_terlanjur;
