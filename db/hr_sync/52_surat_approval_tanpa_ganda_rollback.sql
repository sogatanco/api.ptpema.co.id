-- Jalankan dengan:  mysql --comments -u <user> -p < 52_surat_approval_tanpa_ganda_rollback.sql
-- Mengembalikan trigger ptpemaco_admnew.add_verif_doc_log2 dan after_update2 ke definisi sebelum 51. Isi lama tertanam di berkas ini,
-- sama dengan ptpemaco_admnew.bak_trigger_defs51. Aman dijalankan ulang. Tabel backup dibiarkan; hapus manual setelah stabil.

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

