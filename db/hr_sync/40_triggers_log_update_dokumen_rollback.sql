-- Jalankan dengan:  mysql --comments -u <user> -p < 40_triggers_log_update_dokumen_rollback.sql
-- Mengembalikan ptpemaco_admnew.after_update2 dan ptpemaco_sppdnew.after_update1 ke definisi sebelum 39. Isi lama tertanam di berkas ini,
-- sama dengan ptpemaco_admnew.bak_trigger_defs39. Aman dijalankan ulang. Tabel backup dibiarkan; hapus manual setelah stabil.

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
            INNER JOIN ptpemaco_hr_production.positions p
                ON p.id = 13
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
            INNER JOIN ptpemaco_hr_production.positions p
                ON p.id = 13
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
                INNER JOIN ptpemaco_hr_production.positions p
                    ON p.id = 13
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
                INNER JOIN ptpemaco_hr_production.positions p
                    ON p.id = 13
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
            INNER JOIN ptpemaco_hr_production.positions p
                ON p.id = 13
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
            INNER JOIN ptpemaco_hr_production.positions p
                ON p.id = 13
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
            INNER JOIN ptpemaco_hr_production.positions p
                ON p.id = 13
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
            INNER JOIN ptpemaco_hr_production.positions p
                ON p.id = 13
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
        INNER JOIN ptpemaco_hr_production.positions p
            ON p.id = 13
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
SET SESSION sql_mode = DEFAULT;

-- Laporan: 2 baris; sama_dengan_backup = 1
SELECT t.trigger_name, t.action_statement = b.action_statement AS sama_dengan_backup
FROM information_schema.triggers t
JOIN ptpemaco_admnew.bak_trigger_defs39 b ON b.trigger_schema = t.trigger_schema AND b.trigger_name = t.trigger_name
ORDER BY 1;
