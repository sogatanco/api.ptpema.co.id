-- Menyesuaikan 5 trigger yang memakai id jabatan tetap (hardcode) ke id jabatan HR.
--
-- Latar belakang: setelah 13_views_live_apply.sql, struktur_lengkap_oke.position_id = positions.id HR.
-- Trigger di bawah ini mencari orang lewat position_id tetap dari apam; sebagian id itu tidak ada /
-- berbeda di HR, sehingga langkah approval / notifikasi hilang tanpa error (LIMIT 1 + ROW_COUNT()).
--
--   trigger                              id lama (apam)                      id baru (HR)
--   ptpemaco_sppdnew.add_step_verif_1    109 Sekretaris Komisaris            68
--   ptpemaco_sppdnew.after_update1       109 Sekretaris Komisaris            68
--   ptpemaco_sppdnew.update (realisasi)  79 Manajer Umum                     42
--                                        80 Supervisor Umum                  43
--   ptpemaco_admnew.add_verif_doc_log2   88 Sekretaris Perusahaan            51
--   ptpemaco_admnew.after_update2        88 Sekretaris Perusahaan            51
-- Id 1, 3, 6, 13 sama di apam dan HR, tidak diubah. Trigger pengajuan tidak perlu diubah.
-- Di dua trigger ADM, join nama jabatan "apam.positions p ON p.position_id = s.position_id" diganti
-- ke "ptpemaco_hr_production.positions p ON p.id = s.position_id" (id view sekarang id HR).
--
-- Cara kerja: skrip memeriksa MD5 definisi trigger saat ini (harus sama dengan yang dipakai saat skrip dibuat),
-- menyimpan definisi lama di ptpemaco_sppdnew.bak_trigger_defs (tabel dibuat tanpa IF NOT EXISTS: bila skrip
-- dijalankan dua kali berhenti di sini, sebagai pengaman), lalu DROP + CREATE tiap trigger dengan isi baru.
-- Bila berhenti di tengah, jalankan 18_triggers_rollback.sql.
-- WAJIB dijalankan dengan:  mysql --comments -u <user> -p < 17_triggers_apply.sql
-- (tanpa --comments klien membuang komentar di dalam isi trigger; fungsi tetap sama tetapi isi tidak persis).
-- Jalankan BERSAMAAN dengan 13_views_live_apply.sql (maintenance on), dengan akun yang boleh membuat trigger
-- dengan DEFINER aslinya. Rollback: 18_triggers_rollback.sql.


-- 1. Prasyarat: id jabatan HR sesuai, dan kelima trigger masih persis seperti saat skrip ini dibuat
DROP PROCEDURE IF EXISTS ptpemaco_sppdnew.tmp_trg_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_sppdnew.tmp_trg_precheck()
BEGIN
  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM ptpemaco_hr_production.positions
   WHERE (id = 68 AND TRIM(position_name) = 'Sekretaris Komisaris')
      OR (id = 51 AND TRIM(position_name) = 'Sekretaris Perusahaan')
      OR (id = 43 AND TRIM(position_name) = 'Supervisor Umum')
      OR (id = 42 AND TRIM(position_name) = 'Manajer Umum');
  IF v_ok <> 4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Id jabatan HR 68/51/43/42 tidak sesuai nama yang diharapkan';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM information_schema.triggers
   WHERE (trigger_schema = 'ptpemaco_admnew' AND trigger_name = 'add_verif_doc_log2' AND MD5(REPLACE(action_statement, CHAR(13), '')) = 'b4df3ea82358628505634a70cc2905d3') OR
      (trigger_schema = 'ptpemaco_admnew' AND trigger_name = 'after_update2' AND MD5(REPLACE(action_statement, CHAR(13), '')) = '21e8b5edb37c0bfc1beca31cb661fc53') OR
      (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'add_step_verif_1' AND MD5(REPLACE(action_statement, CHAR(13), '')) = '82b4825b407aea92138427c111ddb114') OR
      (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'after_update1' AND MD5(REPLACE(action_statement, CHAR(13), '')) = 'a97c9d544076b588abb2b733117e03b8') OR
      (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'update' AND MD5(REPLACE(action_statement, CHAR(13), '')) = 'bb316da99b40c8c54eff41deaa0a8701');
  IF v_ok <> 5 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Definisi trigger di server berbeda dari yang dipakai skrip; regenerasi skrip';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_sppdnew.tmp_trg_precheck();
DROP PROCEDURE ptpemaco_sppdnew.tmp_trg_precheck;

-- 2. Backup definisi lama (gagal bila sudah ada = skrip pernah dijalankan)
CREATE TABLE ptpemaco_sppdnew.bak_trigger_defs (
  trigger_schema VARCHAR(64) NOT NULL,
  trigger_name VARCHAR(64) NOT NULL,
  event_object_table VARCHAR(64) NOT NULL,
  action_timing VARCHAR(6) NOT NULL,
  event_manipulation VARCHAR(6) NOT NULL,
  definer VARCHAR(288) NOT NULL,
  sql_mode TEXT,
  action_statement LONGTEXT NOT NULL,
  backed_up_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (trigger_schema, trigger_name)
);
INSERT INTO ptpemaco_sppdnew.bak_trigger_defs
  (trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement)
SELECT trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement
FROM information_schema.triggers
WHERE (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name IN ('add_step_verif_1', 'after_update1', 'update'))
   OR (trigger_schema = 'ptpemaco_admnew' AND trigger_name IN ('add_verif_doc_log2', 'after_update2'));

-- 3. Pasang trigger baru

-- ptpemaco_admnew.add_verif_doc_log2
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_admnew`.`add_verif_doc_log2`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_admnew`.`add_verif_doc_log2` BEFORE INSERT ON `ptpemaco_admnew`.`surats` FOR EACH ROW BEGIN
    DECLARE v_id_base INT DEFAULT NULL;
    DECLARE v_puk VARCHAR(255) DEFAULT NULL;
    DECLARE v_dir VARCHAR(255) DEFAULT NULL;
    DECLARE v_pos88 VARCHAR(255) DEFAULT NULL;
    DECLARE v_step INT DEFAULT 0;

    -- Ambil id_base penandatangan
    SET v_id_base = (
        SELECT id_base
        FROM ptpemaco_apam.struktur_lengkap_oke
        WHERE employe_id = NEW.sign_by
        LIMIT 1
    );

    -- Ambil PUK divisi
    SET v_puk = (
        SELECT PUK
        FROM ptpemaco_apam.divisions
        WHERE organization_id = NEW.id_divisi
        LIMIT 1
    );

    -- Ambil direktur divisi
    SET v_dir = (
        SELECT dir
        FROM ptpemaco_apam.divisions
        WHERE organization_id = NEW.id_divisi
        LIMIT 1
    );

    -- Ambil karyawan dengan position_id = 51
    SET v_pos88 = (
        SELECT employe_id
        FROM ptpemaco_apam.struktur_lengkap_oke
        WHERE position_id = 51
        LIMIT 1
    );


    /* =========================================
       ID_BASE = 3
       ========================================= */
    IF v_id_base = 3 THEN

        IF v_puk IS NOT NULL THEN

            -- PUK
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = v_puk
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- Posisi 88
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, p.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_hr_production.positions p
                ON p.id = s.position_id
            WHERE s.employe_id = v_pos88
              AND s.position_id = 51
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- Direktur divisi
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = v_dir
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- Penandatangan
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = NEW.sign_by
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

        ELSE

            -- Posisi 88
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, p.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_hr_production.positions p
                ON p.id = s.position_id
            WHERE s.employe_id = v_pos88
              AND s.position_id = 51
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- Direktur divisi
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = v_dir
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- Penandatangan
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = NEW.sign_by
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

        END IF;


    /* =========================================
       ID_BASE = 4
       ========================================= */
    ELSEIF v_id_base = 4 THEN

        IF v_puk IS NOT NULL THEN

            -- PUK
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = v_puk
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- Posisi 88
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, p.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_hr_production.positions p
                ON p.id = s.position_id
            WHERE s.employe_id = v_pos88
              AND s.position_id = 51
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- Penandatangan
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = NEW.sign_by
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

        ELSE

            -- Posisi 88
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, p.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_hr_production.positions p
                ON p.id = s.position_id
            WHERE s.employe_id = v_pos88
              AND s.position_id = 51
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- Penandatangan
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = NEW.sign_by
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

        END IF;


    /* =========================================
       ID_BASE = 6
       ========================================= */
    ELSEIF v_id_base = 6 THEN

        -- PUK
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, s.position_name, NEW.no_document,
            v_step + 1, 'paraf', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.employe_id = v_puk
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();

        -- Posisi 88
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, p.position_name, NEW.no_document,
            v_step + 1, 'paraf', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        JOIN ptpemaco_hr_production.positions p
            ON p.id = s.position_id
        WHERE s.employe_id = v_pos88
          AND s.position_id = 51
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();

        -- Penandatangan
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, s.position_name, NEW.no_document,
            v_step + 1, 'sign', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.employe_id = NEW.sign_by
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();


    /* =========================================
       ID_BASE LAINNYA
       ========================================= */
    ELSE

        -- Posisi 88
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, p.position_name, NEW.no_document,
            v_step + 1, 'paraf', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        JOIN ptpemaco_hr_production.positions p
            ON p.id = s.position_id
        WHERE s.employe_id = v_pos88
          AND s.position_id = 51
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();

        -- Penandatangan
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, s.position_name, NEW.no_document,
            v_step + 1, 'sign', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.employe_id = NEW.sign_by
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();

    END IF;


    /* =========================================
       SIMPAN DOKUMEN
       ========================================= */
    INSERT INTO ptpemaco_esign.documents
        (doc_id, doc_file, created_by, id_current_position, jenis_doc, created_at)
    VALUES (
        NEW.no_document,
        '-',
        NEW.submitted_by,
        (
            SELECT position_name
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.submitted_by
            LIMIT 1
        ),
        2,
        NEW.created_at
    );

END$$
DELIMITER ;

-- ptpemaco_admnew.after_update2
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_admnew`.`after_update2`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_admnew`.`after_update2` BEFORE UPDATE ON `ptpemaco_admnew`.`surats` FOR EACH ROW BEGIN
    DECLARE v_id_base INT DEFAULT NULL;
    DECLARE v_puk VARCHAR(255) DEFAULT NULL;
    DECLARE v_dir VARCHAR(255) DEFAULT NULL;
    DECLARE v_pos88 VARCHAR(255) DEFAULT NULL;
    DECLARE v_step INT DEFAULT 0;

    -- Hapus step lama untuk dokumen ini
    DELETE FROM ptpemaco_esign.verif_steps
    WHERE id_doc = NEW.no_document;

    -- Ambil id_base penandatangan
    SET v_id_base = (
        SELECT id_base
        FROM ptpemaco_apam.struktur_lengkap_oke
        WHERE employe_id = NEW.sign_by
        LIMIT 1
    );

    -- Ambil PUK divisi
    SET v_puk = (
        SELECT PUK
        FROM ptpemaco_apam.divisions
        WHERE organization_id = NEW.id_divisi
        LIMIT 1
    );

    -- Ambil direktur divisi
    SET v_dir = (
        SELECT dir
        FROM ptpemaco_apam.divisions
        WHERE organization_id = NEW.id_divisi
        LIMIT 1
    );

    -- Ambil karyawan posisi 88
    SET v_pos88 = (
        SELECT employe_id
        FROM ptpemaco_apam.struktur_lengkap_oke
        WHERE position_id = 51
        LIMIT 1
    );


    /* ==========================================
       ID_BASE = 3
       ========================================== */
    IF v_id_base = 3 THEN

        IF v_puk IS NOT NULL THEN

            -- STEP PUK
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = v_puk
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- STEP POSISI 88
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, p.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_hr_production.positions p
                ON p.id = 51
            WHERE s.employe_id = v_pos88
              AND s.position_id = 51
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- STEP DIREKTUR DIVISI
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = v_dir
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- STEP PENANDATANGAN
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = NEW.sign_by
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

        ELSE

            -- STEP POSISI 88
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, p.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_hr_production.positions p
                ON p.id = 51
            WHERE s.employe_id = v_pos88
              AND s.position_id = 51
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- STEP DIREKTUR DIVISI
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = v_dir
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- STEP PENANDATANGAN
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = NEW.sign_by
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

        END IF;


    /* ==========================================
       ID_BASE = 4
       ========================================== */
    ELSEIF v_id_base = 4 THEN

        IF v_puk IS NOT NULL THEN

            -- STEP PUK
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = v_puk
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- STEP POSISI 88
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, p.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_hr_production.positions p
                ON p.id = 51
            WHERE s.employe_id = v_pos88
              AND s.position_id = 51
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- STEP PENANDATANGAN
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = NEW.sign_by
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

        ELSE

            -- STEP POSISI 88
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, p.position_name, NEW.no_document,
                v_step + 1, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_hr_production.positions p
                ON p.id = 51
            WHERE s.employe_id = v_pos88
              AND s.position_id = 51
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

            -- STEP PENANDATANGAN
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id, s.position_name, NEW.no_document,
                v_step + 1, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.employe_id = NEW.sign_by
            LIMIT 1;

            SET v_step = v_step + ROW_COUNT();

        END IF;


    /* ==========================================
       ID_BASE = 6
       ========================================== */
    ELSEIF v_id_base = 6 THEN

        -- STEP PUK
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, s.position_name, NEW.no_document,
            v_step + 1, 'paraf', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.employe_id = v_puk
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();

        -- STEP POSISI 88
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, p.position_name, NEW.no_document,
            v_step + 1, 'paraf', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        JOIN ptpemaco_hr_production.positions p
            ON p.id = 51
        WHERE s.employe_id = v_pos88
          AND s.position_id = 51
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();

        -- STEP PENANDATANGAN
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, s.position_name, NEW.no_document,
            v_step + 1, 'sign', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.employe_id = NEW.sign_by
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();


    /* ==========================================
       ID_BASE LAINNYA
       ========================================== */
    ELSE

        -- STEP POSISI 88
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, p.position_name, NEW.no_document,
            v_step + 1, 'paraf', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        JOIN ptpemaco_hr_production.positions p
            ON p.id = 51
        WHERE s.employe_id = v_pos88
          AND s.position_id = 51
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();

        -- STEP PENANDATANGAN
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id, s.position_name, NEW.no_document,
            v_step + 1, 'sign', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.employe_id = NEW.sign_by
        LIMIT 1;

        SET v_step = v_step + ROW_COUNT();

    END IF;

END$$
DELIMITER ;

-- ptpemaco_sppdnew.add_step_verif_1
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`add_step_verif_1`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`add_step_verif_1` BEFORE INSERT ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW BEGIN
    -- 1. Deklarasikan variabel untuk step agar dinamis
    DECLARE v_step INT DEFAULT 1;

    IF NEW.employe_id != '-' THEN

        INSERT INTO ptpemaco_apam.notifications
            (actor, recipient, entity_type_id, entity_id)
        VALUES
            (NEW.submitted_by, NEW.employe_id, 30, '#2');

        /* =========================================================
           ID BASE 1 / 2
           ========================================================= */
        IF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) IN (1, 2) THEN

            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            VALUES (
                NEW.submitted_by,
                (SELECT employe_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 13 LIMIT 1),
                32,
                '#3'
            );

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_apam.positions p ON p.position_id = 13
            WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.id_base = 4 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.id_base = 4 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1 OFFSET 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.id_base = 4 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1 OFFSET 2;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.id_base = 3 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


        /* =========================================================
           ID BASE 3
           ========================================================= */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) = 3 THEN

            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            VALUES (
                NEW.submitted_by,
                (SELECT employe_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 13 LIMIT 1),
                32,
                '#3'
            );

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_apam.positions p ON p.position_id = 13
            WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 68 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 1 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


        /* =========================================================
           ID BASE 4
           ========================================================= */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) = 4 THEN

            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            VALUES (
                NEW.submitted_by,
                (SELECT employe_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 13 LIMIT 1),
                32,
                '#3'
            );

            IF (SELECT employe_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 6 LIMIT 1) != NEW.employe_id THEN

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                JOIN ptpemaco_apam.positions p ON p.position_id = 13
                WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 6 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            ELSE

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                JOIN ptpemaco_apam.positions p ON p.position_id = 13
                WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            END IF;


        /* =========================================================
           ID BASE 6 / 7
           ========================================================= */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) IN (6, 7) THEN

            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            VALUES (
                NEW.submitted_by,
                (SELECT employe_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 13 LIMIT 1),
                32,
                '#3'
            );

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_apam.positions p ON p.position_id = 13
            WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            IF (SELECT dir_terkait FROM ptpemaco_apam.atasan_terkait WHERE employe_id = NEW.employe_id LIMIT 1) = (SELECT employe_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 6 LIMIT 1)
            OR (SELECT dir_terkait FROM ptpemaco_apam.atasan_terkait WHERE employe_id = NEW.employe_id LIMIT 1) IS NULL THEN

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 6 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            ELSE

                /* DYNAMIC STEP - DIR TERKAIT */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT a.dir_terkait, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
                FROM ptpemaco_apam.atasan_terkait a
                JOIN ptpemaco_apam.struktur_lengkap_oke s ON s.employe_id = a.dir_terkait
                WHERE a.employe_id = NEW.employe_id AND a.dir_terkait IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 6 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            END IF;


        /* =========================================================
           ID BASE 8 / 9
           ========================================================= */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) IN (8, 9) THEN

            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT NEW.submitted_by, d.PUK, 32, '#3'
            FROM ptpemaco_apam.divisions d
            WHERE d.organization_id = (SELECT organization_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.employe_id LIMIT 1)
              AND d.PUK IS NOT NULL
            LIMIT 1;

            /* DYNAMIC STEP - PUK */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT d.PUK, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
            FROM ptpemaco_apam.divisions d
            JOIN ptpemaco_apam.struktur_lengkap_oke s ON s.employe_id = d.PUK
            WHERE d.organization_id = (SELECT organization_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.employe_id LIMIT 1)
              AND d.PUK IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_apam.positions p ON p.position_id = 13
            WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            IF (SELECT dir_terkait FROM ptpemaco_apam.atasan_terkait WHERE employe_id = NEW.employe_id LIMIT 1) IS NOT NULL THEN

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT a.dir_terkait, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
                FROM ptpemaco_apam.atasan_terkait a
                JOIN ptpemaco_apam.struktur_lengkap_oke s ON s.employe_id = a.dir_terkait
                WHERE a.employe_id = NEW.employe_id AND a.dir_terkait IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            ELSE

                /* DYNAMIC STEP */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            END IF;


        /* =========================================================
           ID BASE 10
           ========================================================= */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) = 10 THEN

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_apam.positions p ON p.position_id = 13
            WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT NEW.submitted_by, s.employe_id, 32, '#3'
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 13 AND s.employe_id IS NOT NULL
            LIMIT 1;

            /* DYNAMIC STEP - PUK */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT d.PUK, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
            FROM ptpemaco_apam.divisions d
            JOIN ptpemaco_apam.struktur_lengkap_oke s ON s.employe_id = d.PUK
            WHERE d.organization_id = (SELECT organization_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.employe_id LIMIT 1)
              AND d.PUK IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


        /* =========================================================
           ID BASE 5
           ========================================================= */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) = 5 THEN

            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT NEW.submitted_by, s.employe_id, 32, '#3'
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 13 AND s.employe_id IS NOT NULL
            LIMIT 1;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            JOIN ptpemaco_apam.positions p ON p.position_id = 13
            WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 68 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 1 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

        END IF;


    /* =============================================================
       NEW.employe_id = '-'
       ============================================================= */
    ELSE

        INSERT INTO ptpemaco_apam.notifications
            (actor, recipient, entity_type_id, entity_id)
        SELECT NEW.submitted_by, s.employe_id, 32, '#3'
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.position_id = 13 AND s.employe_id IS NOT NULL
        LIMIT 1;

        /* DYNAMIC STEP */
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        JOIN ptpemaco_apam.positions p ON p.position_id = 13
        WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
        LIMIT 1;
        IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

        IF (SELECT dir_terkait FROM ptpemaco_apam.atasan_terkait WHERE employe_id = NEW.submitted_by LIMIT 1) IS NULL THEN

            /* DYNAMIC STEP */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 6 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

        ELSE

            /* DYNAMIC STEP - DIR TERKAIT */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT a.dir_terkait, s.position_name, NEW.nomor_dokumen, v_step, 'paraf', NULL, NULL
            FROM ptpemaco_apam.atasan_terkait a
            JOIN ptpemaco_apam.struktur_lengkap_oke s ON s.employe_id = a.dir_terkait
            WHERE a.employe_id = NEW.submitted_by AND a.dir_terkait IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

        END IF;

        /* DYNAMIC STEP */
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT s.employe_id, s.position_name, NEW.nomor_dokumen, v_step, 'sign', NULL, NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.position_id = 3 AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
        LIMIT 1;
        IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

    END IF;

END$$
DELIMITER ;

-- ptpemaco_sppdnew.after_update1
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`after_update1`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`after_update1` AFTER UPDATE ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW BEGIN
    -- 1. Deklarasikan variabel untuk step dinamis
    DECLARE v_step INT DEFAULT 1;

    INSERT INTO ptpemaco_esign.logs
        (employe_id, id_document, activity, created_at)
    VALUES
        (
            NEW.submitted_by,
            NEW.nomor_dokumen,
            'Melakukan update Pengajuan SPPD',
            NEW.updated_at
        );

    DELETE FROM ptpemaco_esign.verif_steps
    WHERE id_doc = NEW.nomor_dokumen;


    /* =========================================================
       EMPLOYEE ADA
       ========================================================= */
    IF NEW.employe_id != '-' THEN

        INSERT INTO ptpemaco_apam.notifications
            (actor, recipient, entity_type_id, entity_id)
        VALUES
            (
                NEW.submitted_by,
                NEW.employe_id,
                30,
                '#2'
            );


        /* =====================================================
           ID BASE 1 / 2
           ===================================================== */
        IF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) IN (1, 2) THEN

            /* NOTIFICATION POSITION 13 */
            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT
                NEW.submitted_by,
                s.employe_id,
                32,
                '#3'
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
            LIMIT 1;


            /* STEP 1 - POSITION 13 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                p.position_name,
                NEW.nomor_dokumen,
                v_step,
                'verifikasi_biaya',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            INNER JOIN ptpemaco_apam.positions p
                ON p.position_id = 13
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
              AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* STEP 2 - ID BASE 4 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'paraf',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.id_base = 4
              AND s.employe_id IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* STEP 3 - ID BASE 4 RECORD KE-2 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'paraf',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.id_base = 4
              AND s.employe_id IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1 OFFSET 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* STEP 4 - ID BASE 4 RECORD KE-3 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'paraf',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.id_base = 4
              AND s.employe_id IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1 OFFSET 2;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* STEP 5 - ID BASE 3 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'sign',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.id_base = 3
              AND s.employe_id IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


        /* =====================================================
           ID BASE 3
           ===================================================== */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) = 3 THEN

            /* NOTIFICATION POSITION 13 */
            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT
                NEW.submitted_by,
                s.employe_id,
                32,
                '#3'
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
            LIMIT 1;


            /* STEP 1 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                p.position_name,
                NEW.nomor_dokumen,
                v_step,
                'verifikasi_biaya',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            INNER JOIN ptpemaco_apam.positions p
                ON p.position_id = 13
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
              AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* STEP 2 - POSITION 109 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'paraf',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 68
              AND s.employe_id IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* STEP 3 - POSITION 1 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'sign',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 1
              AND s.employe_id IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


        /* =====================================================
           ID BASE 4
           ===================================================== */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) = 4 THEN

            /* NOTIFICATION POSITION 13 */
            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT
                NEW.submitted_by,
                s.employe_id,
                32,
                '#3'
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
            LIMIT 1;


            IF (
                SELECT employe_id
                FROM ptpemaco_apam.struktur_lengkap_oke
                WHERE position_id = 6
                LIMIT 1
            ) != NEW.employe_id THEN

                /* STEP 1 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    p.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'verifikasi_biaya',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                INNER JOIN ptpemaco_apam.positions p
                    ON p.position_id = 13
                WHERE s.position_id = 13
                  AND s.employe_id IS NOT NULL
                  AND p.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


                /* STEP 2 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'paraf',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 6
                  AND s.employe_id IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


                /* STEP 3 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'sign',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3
                  AND s.employe_id IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            ELSE

                /* STEP 1 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    p.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'verifikasi_biaya',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                INNER JOIN ptpemaco_apam.positions p
                    ON p.position_id = 13
                WHERE s.position_id = 13
                  AND s.employe_id IS NOT NULL
                  AND p.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


                /* STEP 2 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'sign',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3
                  AND s.employe_id IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            END IF;


        /* =====================================================
           ID BASE 6 / 7
           ===================================================== */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) IN (6, 7) THEN

            /* NOTIFICATION POSITION 13 */
            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT
                NEW.submitted_by,
                s.employe_id,
                32,
                '#3'
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
            LIMIT 1;


            /* STEP 1 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                p.position_name,
                NEW.nomor_dokumen,
                v_step,
                'verifikasi_biaya',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            INNER JOIN ptpemaco_apam.positions p
                ON p.position_id = 13
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
              AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            IF (
                SELECT dir_terkait
                FROM ptpemaco_apam.atasan_terkait
                WHERE employe_id = NEW.employe_id
                LIMIT 1
            ) = (
                SELECT employe_id
                FROM ptpemaco_apam.struktur_lengkap_oke
                WHERE position_id = 6
                LIMIT 1
            )
            OR (
                SELECT dir_terkait
                FROM ptpemaco_apam.atasan_terkait
                WHERE employe_id = NEW.employe_id
                LIMIT 1
            ) IS NULL THEN

                /* STEP 2 - POSITION 6 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'paraf',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 6
                  AND s.employe_id IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


                /* STEP 3 - POSITION 3 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'sign',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3
                  AND s.employe_id IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            ELSE

                /* STEP 2 - DIR TERKAIT */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    a.dir_terkait,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'paraf',
                    NULL,
                    NULL
                FROM ptpemaco_apam.atasan_terkait a
                INNER JOIN ptpemaco_apam.struktur_lengkap_oke s
                    ON s.employe_id = a.dir_terkait
                WHERE a.employe_id = NEW.employe_id
                  AND a.dir_terkait IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


                /* STEP 3 - POSITION 6 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'paraf',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 6
                  AND s.employe_id IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


                /* STEP 4 - POSITION 3 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'sign',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3
                  AND s.employe_id IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            END IF;


        /* =====================================================
           ID BASE 8 / 9
           ===================================================== */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) IN (8, 9) THEN

            /* NOTIFICATION PUK */
            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT
                NEW.submitted_by,
                d.PUK,
                32,
                '#3'
            FROM ptpemaco_apam.divisions d
            WHERE d.organization_id = (
                SELECT organization_id
                FROM ptpemaco_apam.struktur_lengkap_oke
                WHERE employe_id = NEW.employe_id
                LIMIT 1
            )
              AND d.PUK IS NOT NULL
            LIMIT 1;


            /* STEP 1 - PUK */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                d.PUK,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'paraf',
                NULL,
                NULL
            FROM ptpemaco_apam.divisions d
            INNER JOIN ptpemaco_apam.struktur_lengkap_oke s
                ON s.employe_id = d.PUK
            WHERE d.organization_id = (
                SELECT organization_id
                FROM ptpemaco_apam.struktur_lengkap_oke
                WHERE employe_id = NEW.employe_id
                LIMIT 1
            )
              AND d.PUK IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* STEP 2 - POSITION 13 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                p.position_name,
                NEW.nomor_dokumen,
                v_step,
                'verifikasi_biaya',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            INNER JOIN ptpemaco_apam.positions p
                ON p.position_id = 13
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
              AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            IF (
                SELECT dir_terkait
                FROM ptpemaco_apam.atasan_terkait
                WHERE employe_id = NEW.employe_id
                LIMIT 1
            ) IS NOT NULL THEN

                /* STEP 3 - DIR TERKAIT */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    a.dir_terkait,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'sign',
                    NULL,
                    NULL
                FROM ptpemaco_apam.atasan_terkait a
                INNER JOIN ptpemaco_apam.struktur_lengkap_oke s
                    ON s.employe_id = a.dir_terkait
                WHERE a.employe_id = NEW.employe_id
                  AND a.dir_terkait IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            ELSE

                /* STEP 3 - POSITION 3 */
                INSERT INTO ptpemaco_esign.verif_steps
                    (id_employe, id_current_position, id_doc, step, type, status, ket)
                SELECT
                    s.employe_id,
                    s.position_name,
                    NEW.nomor_dokumen,
                    v_step,
                    'sign',
                    NULL,
                    NULL
                FROM ptpemaco_apam.struktur_lengkap_oke s
                WHERE s.position_id = 3
                  AND s.employe_id IS NOT NULL
                  AND s.position_name IS NOT NULL
                LIMIT 1;
                IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

            END IF;


        /* =====================================================
           ID BASE 10
           ===================================================== */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) = 10 THEN

            /* STEP 1 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                p.position_name,
                NEW.nomor_dokumen,
                v_step,
                'verifikasi_biaya',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            INNER JOIN ptpemaco_apam.positions p
                ON p.position_id = 13
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
              AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* NOTIFICATION */
            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT
                NEW.submitted_by,
                s.employe_id,
                32,
                '#3'
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
            LIMIT 1;


            /* STEP 2 - PUK */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                d.PUK,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'sign',
                NULL,
                NULL
            FROM ptpemaco_apam.divisions d
            INNER JOIN ptpemaco_apam.struktur_lengkap_oke s
                ON s.employe_id = d.PUK
            WHERE d.organization_id = (
                SELECT organization_id
                FROM ptpemaco_apam.struktur_lengkap_oke
                WHERE employe_id = NEW.employe_id
                LIMIT 1
            )
              AND d.PUK IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


        /* =====================================================
           ID BASE 5
           ===================================================== */
        ELSEIF (
            SELECT id_base
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE employe_id = NEW.employe_id
            LIMIT 1
        ) = 5 THEN

            /* NOTIFICATION */
            INSERT INTO ptpemaco_apam.notifications
                (actor, recipient, entity_type_id, entity_id)
            SELECT
                NEW.submitted_by,
                s.employe_id,
                32,
                '#3'
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
            LIMIT 1;


            /* STEP 1 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                p.position_name,
                NEW.nomor_dokumen,
                v_step,
                'verifikasi_biaya',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            INNER JOIN ptpemaco_apam.positions p
                ON p.position_id = 13
            WHERE s.position_id = 13
              AND s.employe_id IS NOT NULL
              AND p.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* STEP 2 - POSITION 109 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'paraf',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 68
              AND s.employe_id IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


            /* STEP 3 - POSITION 1 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'sign',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 1
              AND s.employe_id IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

        END IF;


    /* =========================================================
       EMPLOYEE '-'
       ========================================================= */
    ELSE

        /* NOTIFICATION POSITION 13 */
        INSERT INTO ptpemaco_apam.notifications
            (actor, recipient, entity_type_id, entity_id)
        SELECT
            NEW.submitted_by,
            s.employe_id,
            32,
            '#3'
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.position_id = 13
          AND s.employe_id IS NOT NULL
        LIMIT 1;


        /* STEP 1 */
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id,
            p.position_name,
            NEW.nomor_dokumen,
            v_step,
            'verifikasi_biaya',
            NULL,
            NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        INNER JOIN ptpemaco_apam.positions p
            ON p.position_id = 13
        WHERE s.position_id = 13
          AND s.employe_id IS NOT NULL
          AND p.position_name IS NOT NULL
        LIMIT 1;
        IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;


        IF (
            SELECT dir_terkait
            FROM ptpemaco_apam.atasan_terkait
            WHERE employe_id = NEW.submitted_by
            LIMIT 1
        ) IS NULL THEN

            /* STEP 2 - POSITION 6 */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                s.employe_id,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'paraf',
                NULL,
                NULL
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = 6
              AND s.employe_id IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

        ELSE

            /* STEP 2 - DIR TERKAIT */
            INSERT INTO ptpemaco_esign.verif_steps
                (id_employe, id_current_position, id_doc, step, type, status, ket)
            SELECT
                a.dir_terkait,
                s.position_name,
                NEW.nomor_dokumen,
                v_step,
                'paraf',
                NULL,
                NULL
            FROM ptpemaco_apam.atasan_terkait a
            INNER JOIN ptpemaco_apam.struktur_lengkap_oke s
                ON s.employe_id = a.dir_terkait
            WHERE a.employe_id = NEW.submitted_by
              AND a.dir_terkait IS NOT NULL
              AND s.position_name IS NOT NULL
            LIMIT 1;
            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

        END IF;


        /* STEP 3 - POSITION 3 */
        INSERT INTO ptpemaco_esign.verif_steps
            (id_employe, id_current_position, id_doc, step, type, status, ket)
        SELECT
            s.employe_id,
            s.position_name,
            NEW.nomor_dokumen,
            v_step,
            'sign',
            NULL,
            NULL
        FROM ptpemaco_apam.struktur_lengkap_oke s
        WHERE s.position_id = 3
          AND s.employe_id IS NOT NULL
          AND s.position_name IS NOT NULL
        LIMIT 1;
        IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;

    END IF;

END$$
DELIMITER ;

-- ptpemaco_sppdnew.update
SET SESSION sql_mode = '';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`update`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`update` AFTER UPDATE ON `ptpemaco_sppdnew`.`realisasi` FOR EACH ROW BEGIN

	DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='check_document';
    DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='verif_realisasi';
    DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='verif_document';

	CREATE TEMPORARY TABLE temp_table AS SELECT `step` FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) ORDER BY step DESC LIMIT 1;
    
    
    INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=43 limit 1), 32, '#3');
    
    
    INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT employe_id from sppds where id=NEW.id_sppd LIMIT 1), 31, '#3');
    
    INSERT INTO ptpemaco_esign.verif_steps(id_employe, id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=42 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=42 limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+1), 'check_document', NULL, NULL);
    
    INSERT INTO ptpemaco_esign.verif_steps(id_employe, id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=42 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=42 limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+2), 'verif_document', NULL, NULL);
    
	
    
    
    
    INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+3), 'verif_realisasi', NULL, NULL);
    
    
    
    
    
    
    DROP TEMPORARY TABLE temp_table;
    
    INSERT INTO ptpemaco_esign.logs(employe_id,id_current_position, id_document, activity, created_at) VALUES(NEW.submitted_by,(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=NEW.submitted_by limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), 'Update Laporan Realisasi', NEW.created_at);
END$$
DELIMITER ;
SET SESSION sql_mode = DEFAULT;

-- 4. Laporan: 5 baris, kolom id_lama_tersisa harus 0
SELECT trigger_schema, trigger_name,
       action_statement REGEXP 'position_id[[:space:]]*=[[:space:]]*(109|88|80|79)([^0-9]|$)' AS id_lama_tersisa
FROM information_schema.triggers
WHERE (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name IN ('add_step_verif_1', 'after_update1', 'update'))
   OR (trigger_schema = 'ptpemaco_admnew' AND trigger_name IN ('add_verif_doc_log2', 'after_update2'))
ORDER BY 1, 2;
