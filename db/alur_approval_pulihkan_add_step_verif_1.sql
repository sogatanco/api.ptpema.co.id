-- DARURAT: mengembalikan HANYA trigger ptpemaco_sppdnew.add_step_verif_1 (BEFORE INSERT sppds) ke versi sebelum alur dinamis
-- (versi db/notif/02: tanpa notifikasi). Trigger lain, tabel aturan dan prosedur tidak disentuh.
-- WAJIB:  mysql --comments -u <user> -p < alur_approval_pulihkan_add_step_verif_1.sql
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`add_step_verif_1`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`add_step_verif_1` BEFORE INSERT ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW BEGIN

    -- 1. Deklarasikan variabel untuk step agar dinamis

    DECLARE v_step INT DEFAULT 1;



    IF NEW.employe_id != '-' THEN



        DO 0;



        /* =========================================================

           ID BASE 1 / 2

           ========================================================= */

        IF (

            SELECT id_base

            FROM ptpemaco_apam.struktur_lengkap_oke

            WHERE employe_id = NEW.employe_id

            LIMIT 1

        ) IN (1, 2) THEN



            DO 0;



            /* DYNAMIC STEP */

            INSERT INTO ptpemaco_esign.verif_steps

                (id_employe, id_current_position, id_doc, step, type, status, ket)

            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL

            FROM ptpemaco_apam.struktur_lengkap_oke s

            JOIN ptpemaco_hr_production.positions p ON p.id = 13

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



            DO 0;



            /* DYNAMIC STEP */

            INSERT INTO ptpemaco_esign.verif_steps

                (id_employe, id_current_position, id_doc, step, type, status, ket)

            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL

            FROM ptpemaco_apam.struktur_lengkap_oke s

            JOIN ptpemaco_hr_production.positions p ON p.id = 13

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



            DO 0;



            IF (SELECT employe_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 6 LIMIT 1) != NEW.employe_id THEN



                /* DYNAMIC STEP */

                INSERT INTO ptpemaco_esign.verif_steps

                    (id_employe, id_current_position, id_doc, step, type, status, ket)

                SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL

                FROM ptpemaco_apam.struktur_lengkap_oke s

                JOIN ptpemaco_hr_production.positions p ON p.id = 13

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

                JOIN ptpemaco_hr_production.positions p ON p.id = 13

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



            DO 0;



            /* DYNAMIC STEP */

            INSERT INTO ptpemaco_esign.verif_steps

                (id_employe, id_current_position, id_doc, step, type, status, ket)

            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL

            FROM ptpemaco_apam.struktur_lengkap_oke s

            JOIN ptpemaco_hr_production.positions p ON p.id = 13

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



            DO 0;



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

            JOIN ptpemaco_hr_production.positions p ON p.id = 13

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

            JOIN ptpemaco_hr_production.positions p ON p.id = 13

            WHERE s.position_id = 13 AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL

            LIMIT 1;

            IF ROW_COUNT() > 0 THEN SET v_step = v_step + 1; END IF;



            DO 0;



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



            DO 0;



            /* DYNAMIC STEP */

            INSERT INTO ptpemaco_esign.verif_steps

                (id_employe, id_current_position, id_doc, step, type, status, ket)

            SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL

            FROM ptpemaco_apam.struktur_lengkap_oke s

            JOIN ptpemaco_hr_production.positions p ON p.id = 13

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



        DO 0;



        /* DYNAMIC STEP */

        INSERT INTO ptpemaco_esign.verif_steps

            (id_employe, id_current_position, id_doc, step, type, status, ket)

        SELECT s.employe_id, p.position_name, NEW.nomor_dokumen, v_step, 'verifikasi_biaya', NULL, NULL

        FROM ptpemaco_apam.struktur_lengkap_oke s

        JOIN ptpemaco_hr_production.positions p ON p.id = 13

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
SELECT trigger_name, LENGTH(action_statement) AS panjang FROM information_schema.triggers WHERE trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'add_step_verif_1';
