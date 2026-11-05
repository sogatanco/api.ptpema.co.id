-- 43: SPPD disetujui (langkah tanda tangan 'sign' disetujui) -> otomatis dibuatkan cuti "Dinas Luar Kota" (time_off_masters id 3) di HR
-- untuk karyawan SPPD tersebut, langsung berstatus 'approved' tanpa alur approval (tidak ada baris approval_requests).
-- Hanya SPPD baru ke depan; data lama tidak disentuh.
--   Tanggal    : MIN(waktu_berangkat) s/d MAX(waktu_kembali) dari ptpemaco_sppdnew.tujuan_sppds.
--   Hari       : hari kerja (Senin-Jumat) dikurangi hari libur HR (holidays.is_deleted = 0), minimal 1; sama dengan cara HR menghitung pada 96% data yang ada.
--   Alasan     : 'SPPD <nomor> - <narasi>'. created_by = pengaju SPPD, approved_by = penanda tangan (NULL bila tidak ada di users HR).
--   Dilewati   : employe_id '-', karyawan tidak ada di users HR, SPPD tanpa tujuan, atau karyawan sudah punya pengajuan dinas luar kota (pending/approved)
--                yang beririsan tanggalnya. Hasil tiap SPPD dicatat di ptpemaco_sppdnew.sppd_cuti_dinas (kolom hasil).
--   Disetujui ulang setelah SPPD diedit: pengajuan HR yang sama diperbarui (tidak dobel), dikenali lewat tabel sppd_cuti_dinas.
--   Galat apa pun dicatat di sppd_cuti_dinas dan TIDAK membatalkan tanda tangan SPPD.
-- Prasyarat: user definer trigger (ptpemaco@localhost) perlu hak INSERT, UPDATE pada ptpemaco_hr_production.request_time_offs.
--   Bila belum:  GRANT INSERT, UPDATE ON ptpemaco_hr_production.request_time_offs TO 'ptpemaco'@'localhost';
-- WAJIB:  mysql --comments -u <user> -p < 43_sppd_cuti_dinas_otomatis.sql     Rollback: 44_sppd_cuti_dinas_otomatis_rollback.sql

DROP PROCEDURE IF EXISTS ptpemaco_sppdnew.tmp_43_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_sppdnew.tmp_43_precheck()
BEGIN
  DECLARE v INT;
  SELECT COUNT(*) INTO v FROM ptpemaco_hr_production.time_off_masters WHERE id = 3 AND type = 'Dinas Luar Kota' AND active = 1;
  IF v <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'time_off_masters id 3 bukan Dinas Luar Kota yang aktif'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.tables WHERE table_schema = 'ptpemaco_hr_production' AND table_name IN ('request_time_offs', 'holidays', 'users');
  IF v <> 3 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabel HR request_time_offs/holidays/users tidak lengkap'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.triggers WHERE trigger_schema = 'ptpemaco_esign' AND trigger_name = 'sppd_cuti_dinas';
  IF v <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Trigger sppd_cuti_dinas sudah ada (sudah dijalankan?)'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.tables WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'sppd_cuti_dinas';
  IF v <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabel sppd_cuti_dinas sudah ada'; END IF;
END$$
DELIMITER ;
CALL ptpemaco_sppdnew.tmp_43_precheck();
DROP PROCEDURE ptpemaco_sppdnew.tmp_43_precheck;

CREATE TABLE ptpemaco_sppdnew.sppd_cuti_dinas (
  id_sppd BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  nomor_dokumen VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  request_time_off_id BIGINT UNSIGNED NULL,
  hasil VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- Laporan: 1 baris, trigger_ada = 1, tabel_catatan = 1
SELECT (SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = 'ptpemaco_esign' AND trigger_name = 'sppd_cuti_dinas') AS trigger_ada,
       (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'sppd_cuti_dinas') AS tabel_catatan;
