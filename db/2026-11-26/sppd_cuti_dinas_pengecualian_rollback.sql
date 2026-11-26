-- Rollback sppd_cuti_dinas_pengecualian.sql: kembalikan trigger 43 dan 45 ke definisi semula, hapus trigger void dan fungsi pengenal Direktur/Komisaris.
-- Cuti yang sudah dihapus karena SPPD void TIDAK dikembalikan.
-- WAJIB:  mysql --comments -u <user> -p < sppd_cuti_dinas_pengecualian_rollback.sql

DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`sppd_cuti_dinas_void`;
DROP FUNCTION IF EXISTS ptpemaco_sppdnew.karyawan_direksi_komisaris;

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
