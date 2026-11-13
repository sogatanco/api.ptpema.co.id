-- Rollback alur_approval.sql / alur_approval_lanjut.sql / alur_approval_lanjut_surat.sql: mengembalikan keempat trigger ke versi sebelum alur dinamis,
-- yaitu versi yang berjalan setelah db/notif/02 untuk SPPD (tanpa notifikasi) dan hasil 55 untuk surat. Lalu menghapus prosedur
-- ptpemaco_esign.bangun_alur dan alur_cari. Definisi persis sebelum pemasangan juga ada di ptpemaco_esign.bak_trigger_defs_alur.
-- Tabel aturan (alur_aturan, alur_langkah) dan tabel backup DIBIARKAN supaya aturan tidak hilang; hapus manual bila sudah tidak diperlukan:
--   DROP TABLE ptpemaco_esign.alur_langkah, ptpemaco_esign.alur_aturan;
-- Untuk memasang alur_approval.sql lagi sesudah rollback: hapus dulu tabel aturan dan backup (aturan kembali ke bawaan):
--   DROP TABLE ptpemaco_esign.alur_langkah, ptpemaco_esign.alur_aturan, ptpemaco_esign.bak_trigger_defs_alur;
-- Aman dijalankan ulang. Jalankan saat tidak ada pengajuan SPPD baru dan tidak ada surat yang sedang dibuat/diedit.
-- WAJIB:  mysql --comments -u <user> -p < alur_approval_rollback.sql

-- ptpemaco_sppdnew.add_step_verif_1
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

-- ptpemaco_sppdnew.after_update1
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`after_update1`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`after_update1` AFTER UPDATE ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW BEGIN

    -- 1. Deklarasikan variabel untuk step dinamis

    DECLARE v_step INT DEFAULT 1;



    INSERT INTO ptpemaco_esign.logs

        (employe_id, id_current_position, id_document, activity, created_at)

    VALUES

        (

            NEW.submitted_by,

            (SELECT position_name FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.submitted_by LIMIT 1),

            NEW.nomor_dokumen,

            'Melakukan update Pengajuan SPPD',

            IFNULL(NEW.updated_at, NOW())

        );



    DELETE FROM ptpemaco_esign.verif_steps

    WHERE id_doc = NEW.nomor_dokumen;





    /* =========================================================

       EMPLOYEE ADA

       ========================================================= */

    IF NEW.employe_id != '-' THEN



        DO 0;





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

            DO 0;





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

            DO 0;





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

            DO 0;





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

            DO 0;





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

            DO 0;





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

            DO 0;





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

            DO 0;





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

        DO 0;





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

DROP PROCEDURE IF EXISTS ptpemaco_esign.bangun_alur;
DROP PROCEDURE IF EXISTS ptpemaco_esign.alur_cari;

SELECT trigger_schema, trigger_name, MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) AS md5_norm FROM information_schema.triggers
 WHERE trigger_name IN ('add_step_verif_1', 'after_update1', 'add_verif_doc_log2', 'after_update2') AND trigger_schema IN ('ptpemaco_sppdnew', 'ptpemaco_admnew') ORDER BY 1, 2;
