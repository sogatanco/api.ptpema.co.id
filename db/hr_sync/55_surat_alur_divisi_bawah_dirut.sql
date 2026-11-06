-- Approval surat untuk divisi yang langsung di bawah Direktur Utama (tanpa direktur): Sekretariat Perusahaan, Satuan Pengawas Internal,
-- Manajemen Resiko (di tabel divisions kolom dir = Direktur Utama).
-- Alur bila yang menandatangani Direktur Utama:  PUK / Sekretaris Perusahaan (paraf)  ->  Direktur Umum dan Keuangan (paraf)  ->  Direktur Utama (sign).
-- Sebelumnya paraf "direktur divisi" jatuh ke Direktur Utama sendiri; setelah 51 paraf itu dibuang sehingga Direktur Umum dan Keuangan tidak ikut.
-- Perubahan di dua trigger tabel ptpemaco_admnew.surats (buat DAN update surat): bila direktur divisi berjabatan Direktur Utama (id_base 3),
-- paraf direktur diberikan ke pemegang posisi 6 Direktur Umum dan Keuangan. Divisi dengan direktur lain tidak berubah. Surat yang sudah ada tidak berubah.
-- PRASYARAT: 51_surat_approval_tanpa_ganda.sql sudah dijalankan (skrip memeriksa MD5 definisi trigger hasil 51 dan berhenti bila berbeda).
-- Definisi sebelum 55 disimpan di ptpemaco_admnew.bak_trigger_defs55 (tanpa IF NOT EXISTS: dijalankan dua kali berhenti di sini).
-- WAJIB:  mysql --comments -u <user> -p < 55_surat_alur_divisi_bawah_dirut.sql     Rollback: 56_surat_alur_divisi_bawah_dirut_rollback.sql
-- Jalankan saat tidak ada surat yang sedang dibuat/diedit.

DROP PROCEDURE IF EXISTS ptpemaco_admnew.tmp_trg55_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_admnew.tmp_trg55_precheck()
BEGIN
  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM information_schema.triggers
   WHERE (trigger_schema = 'ptpemaco_admnew' AND trigger_name = 'add_verif_doc_log2' AND MD5(REPLACE(action_statement, CHAR(13), '')) = 'c05d22382fae239889a9dfeafeb0e878') OR
      (trigger_schema = 'ptpemaco_admnew' AND trigger_name = 'after_update2' AND MD5(REPLACE(action_statement, CHAR(13), '')) = '6ee6b7bd468c4b1afd790992f2ccc8dd');
  IF v_ok <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Definisi trigger surats bukan hasil 51 (jalankan 51 dulu) atau berbeda dari yang dipakai skrip';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 6 AND TRIM(position_name) = 'Direktur Umum dan Keuangan';
  IF v_ok < 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Posisi 6 Direktur Umum dan Keuangan tidak ada pemegangnya';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_admnew.tmp_trg55_precheck();
DROP PROCEDURE ptpemaco_admnew.tmp_trg55_precheck;

CREATE TABLE ptpemaco_admnew.bak_trigger_defs55 (
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
INSERT INTO ptpemaco_admnew.bak_trigger_defs55
  (trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement)
SELECT trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement
FROM information_schema.triggers
WHERE trigger_schema = 'ptpemaco_admnew' AND trigger_name IN ('add_verif_doc_log2', 'after_update2');

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

    -- Divisi langsung di bawah Direktur Utama (tidak punya direktur): paraf direktur dipegang Direktur Umum dan Keuangan (posisi 6),
    -- sehingga alurnya PUK / Sekretaris Perusahaan, Direktur Umum dan Keuangan, lalu Direktur Utama menandatangani
    IF (SELECT id_base FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = v_dir LIMIT 1) = 3 THEN
        SET v_dir = (
            SELECT employe_id
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE position_id = 6
            LIMIT 1
        );
    END IF;

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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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

    -- Catat perubahan surat di log e-sign
    INSERT INTO ptpemaco_esign.logs
        (employe_id, id_current_position, id_document, activity, created_at)
    VALUES
        (
            NEW.submitted_by,
            (SELECT position_name FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.submitted_by LIMIT 1),
            NEW.no_document,
            'Melakukan Perubahan Dokumen',
            NOW()
        );

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

    -- Divisi langsung di bawah Direktur Utama (tidak punya direktur): paraf direktur dipegang Direktur Umum dan Keuangan (posisi 6),
    -- sehingga alurnya PUK / Sekretaris Perusahaan, Direktur Umum dan Keuangan, lalu Direktur Utama menandatangani
    IF (SELECT id_base FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = v_dir LIMIT 1) = 3 THEN
        SET v_dir = (
            SELECT employe_id
            FROM ptpemaco_apam.struktur_lengkap_oke
            WHERE position_id = 6
            LIMIT 1
        );
    END IF;

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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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
              AND s.employe_id <> NEW.sign_by
              AND NOT EXISTS (
                  SELECT 1 FROM ptpemaco_esign.verif_steps x
                  WHERE x.id_doc = NEW.no_document AND x.id_employe = s.employe_id
              )
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

-- TES: edit/buat surat uji dari Sekretariat Perusahaan (organization_id 23) dengan penandatangan Direktur Utama:
-- langkah yang terbentuk: 1 paraf Sekretaris Perusahaan, 2 paraf Direktur Umum dan Keuangan, 3 sign Direktur Utama.
