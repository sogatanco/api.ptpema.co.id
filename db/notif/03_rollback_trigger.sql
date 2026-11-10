-- Mengembalikan trigger notifikasi lama persis seperti sebelum db/notif/02_hapus_trigger_notifikasi.sql dijalankan.
-- Setelah dijalankan, set NOTIF_BARU_AKTIF=false di .env backend supaya notifikasi tidak ganda.

DELIMITER $$

-- ptpemaco_admnew.dispo
DROP TRIGGER IF EXISTS `ptpemaco_admnew`.`dispo`$$
CREATE TRIGGER `ptpemaco_admnew`.`dispo` AFTER INSERT ON `ptpemaco_admnew`.`disposisi` FOR EACH ROW
BEGIN 
INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.employe_id, 
                                                                                             
                                                                     IF(
        NEW.`dispo_to` = 'position',
        (
        SELECT
            `struktur_lengkap_oke`.`employe_id`
        FROM
            `ptpemaco_apam`.`struktur_lengkap_oke`
        WHERE
            `struktur_lengkap_oke`.`position_id` = NEW.`id_penerima`
        LIMIT 1
    ),
    (
    SELECT
        `divisions`.`PUK`
    FROM
        `ptpemaco_apam`.`divisions`
    WHERE
        `divisions`.`organization_id` = NEW.`id_penerima`
    LIMIT 1
)
    )                  , 40, '#4');

END$$

-- ptpemaco_admnew.notif_cc
DROP TRIGGER IF EXISTS `ptpemaco_admnew`.`notif_cc`$$
CREATE TRIGGER `ptpemaco_admnew`.`notif_cc` BEFORE INSERT ON `ptpemaco_admnew`.`cc` FOR EACH ROW
BEGIN 
INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES((SELECT employe_id from disposisi where id=NEW.id_dispo limit 1), 
                                                                                             
                                                                     IF(
        NEW.`cc_to` = 'position',
        (
        SELECT
            `struktur_lengkap_oke`.`employe_id`
        FROM
            `ptpemaco_apam`.`struktur_lengkap_oke`
        WHERE
            `struktur_lengkap_oke`.`position_id` = NEW.`id_penerima`
        LIMIT 1
    ),
    (
    SELECT
        `divisions`.`PUK`
    FROM
        `ptpemaco_apam`.`divisions`
    WHERE
        `divisions`.`organization_id` = NEW.`id_penerima`
    LIMIT 1
)
    )                  , 41, '#5');

END$$

-- ptpemaco_esign.after_add
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`after_add`$$
CREATE TRIGGER `ptpemaco_esign`.`after_add` BEFORE INSERT ON `ptpemaco_esign`.`documents` FOR EACH ROW
BEGIN

IF NEW.jenis_doc !=1 THEN

INSERT INTO logs(employe_id,id_current_position, id_document, activity, created_at) VALUES(NEW.created_by,(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=NEW.created_by limit 1), NEW.doc_id, 'Membuat Dokumen', NEW.created_at);

INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.created_by, (SELECT id_employe from verif_steps where id_doc=NEW.doc_id ORDER by step ASC limit 1 ), 39, '#2');

END IF;

END$$

-- ptpemaco_esign.after_update
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`after_update`$$
CREATE TRIGGER `ptpemaco_esign`.`after_update` BEFORE UPDATE ON `ptpemaco_esign`.`documents` FOR EACH ROW
BEGIN

IF NEW.jenis_doc !=1 THEN

INSERT INTO logs(employe_id,id_current_position, id_document, activity, created_at) VALUES(NEW.created_by,(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=NEW.created_by limit 1), NEW.doc_id, 'Melakukan Perubahan Dokumen', NEW.updated_at);

INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.created_by, (SELECT id_employe from verif_steps where id_doc=NEW.doc_id ORDER by step ASC limit 1 ), 39, '#2');

END IF;

END$$

-- ptpemaco_esign.notif2
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`notif2`$$
CREATE TRIGGER `ptpemaco_esign`.`notif2` AFTER UPDATE ON `ptpemaco_esign`.`verif_steps` FOR EACH ROW
BEGIN 

IF (SELECT jenis_doc from documents where doc_id=NEW.id_doc limit 1)=1 THEN 


IF NEW.type='check_document' OR NEW.type='verif_realisasi' THEN

	IF NEW.status='approve' THEN 
    	
        IF (SELECT COUNT(id_employe) from  ptpemaco_esign.`verif_steps` WHERE id_doc = NEW.id_doc and step=(NEW.step+1))>0 THEN
            INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES((SELECT submitted_by from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), (SELECT id_employe from  ptpemaco_esign.`verif_steps` WHERE id_doc = NEW.id_doc and step=(NEW.step+1) limit 1 ), 32, '#3');
         END IF;

    	IF (SELECT employe_id from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 )!='-' THEN 
        	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT employe_id from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), 28, '#2');
        END IF;
        INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT submitted_by from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), 28, '#1');
    
    ELSE
    	IF (SELECT employe_id from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 )!='-' THEN 
        	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT employe_id from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), 29, '#2');
        END IF;
        INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT submitted_by from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), 29, '#1');
	END IF;



ELSE

	IF NEW.status='approve' THEN 
    	
        IF (SELECT COUNT(id_employe) from  ptpemaco_esign.`verif_steps` WHERE id_doc = NEW.id_doc and step=(NEW.step+1))>0 THEN
            INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES((SELECT submitted_by from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), (SELECT id_employe from  ptpemaco_esign.`verif_steps` WHERE id_doc = NEW.id_doc and step=(NEW.step+1) limit 1 ), 32, '#3');
         END IF;

    	IF (SELECT employe_id from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 )!='-' THEN 
        	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT employe_id from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), 26, '#2');
        END IF;
        INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT submitted_by from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), 26, '#1');
    
    ELSE
    	IF (SELECT employe_id from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 )!='-' THEN 
        	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT employe_id from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), 27, '#2');
        END IF;
        INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT submitted_by from ptpemaco_sppdnew.sppds WHERE nomor_dokumen = NEW.id_doc limit 1 ), 27, '#1');
	END IF;
END IF;


END IF;  
END$$

-- ptpemaco_esign.notif_selainsppd2
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`notif_selainsppd2`$$
CREATE TRIGGER `ptpemaco_esign`.`notif_selainsppd2` AFTER UPDATE ON `ptpemaco_esign`.`verif_steps` FOR EACH ROW
BEGIN
    IF (SELECT jenis_doc from documents where doc_id=NEW.id_doc limit 1)!=1 THEN 
		
        IF(NEW.status='approve') THEN
        	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT created_by from documents where doc_id=NEW.id_doc limit 1 ), 37, '#1');

		ELSE
        	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT created_by from documents where doc_id=NEW.id_doc limit 1 ), 38, '#1');

        END IF;
        
    END IF;
END$$

-- ptpemaco_mobil_op.notif
DROP TRIGGER IF EXISTS `ptpemaco_mobil_op`.`notif`$$
CREATE TRIGGER `ptpemaco_mobil_op`.`notif` BEFORE INSERT ON `ptpemaco_mobil_op`.`permintaan` FOR EACH ROW
INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) 
SELECT new.created_by, employe_id, 43, '#2' FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE roles like '%PICMobil%'$$

-- ptpemaco_mobil_op.notif_review
DROP TRIGGER IF EXISTS `ptpemaco_mobil_op`.`notif_review`$$
CREATE TRIGGER `ptpemaco_mobil_op`.`notif_review` AFTER UPDATE ON `ptpemaco_mobil_op`.`permintaan` FOR EACH ROW
BEGIN
  IF NOT (OLD.status <=> NEW.status) THEN
  	IF NEW.status=1 THEN
    	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES (NEW.review_by, NEW.created_by, 44, '#2');
    ELSEIF NEW.status=0 THEN
    	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES (NEW.review_by, NEW.created_by, 45, '#2');
    END IF;
  END IF;
END$$

-- ptpemaco_sppdnew.add_step_verif_1
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`add_step_verif_1`$$
CREATE TRIGGER `ptpemaco_sppdnew`.`add_step_verif_1` BEFORE INSERT ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW
BEGIN
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

END$$

-- ptpemaco_sppdnew.after_update1
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`after_update1`$$
CREATE TRIGGER `ptpemaco_sppdnew`.`after_update1` AFTER UPDATE ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW
BEGIN
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

END$$

-- ptpemaco_sppdnew.insert doc
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`insert doc`$$
CREATE TRIGGER `ptpemaco_sppdnew`.`insert doc` BEFORE INSERT ON `ptpemaco_sppdnew`.`realisasi` FOR EACH ROW
BEGIN

	DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='check_document';
    DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='verif_realisasi';
    DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='verif_document';

	CREATE TEMPORARY TABLE temp_table AS SELECT `step` FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) ORDER BY step DESC LIMIT 1;
    
    
    INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT PUK from ptpemaco_apam.divisions WHERE organization_id=20 limit 1), 32, '#3');
    
    
    INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT employe_id from sppds where id=NEW.id_sppd LIMIT 1), 31, '#3');
    
    INSERT INTO ptpemaco_esign.verif_steps(id_employe, id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT PUK from ptpemaco_apam.divisions WHERE organization_id=20 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT PUK from ptpemaco_apam.divisions WHERE organization_id=20 limit 1) limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+1), 'check_document', NULL, NULL);
    
    INSERT INTO ptpemaco_esign.verif_steps(id_employe, id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT PUK from ptpemaco_apam.divisions WHERE organization_id=20 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT PUK from ptpemaco_apam.divisions WHERE organization_id=20 limit 1) limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+2), 'verif_document', NULL, NULL);
    
	
    
    
    
    INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+3), 'verif_realisasi', NULL, NULL);
    
    
    
    
    
    
    DROP TEMPORARY TABLE temp_table;
    
    INSERT INTO ptpemaco_esign.logs(employe_id,id_current_position, id_document, activity, created_at) VALUES(NEW.submitted_by,(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=NEW.submitted_by limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), 'Membuat Laporan Realisasi', NEW.created_at);
END$$

-- ptpemaco_sppdnew.ss
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`ss`$$
CREATE TRIGGER `ptpemaco_sppdnew`.`ss` BEFORE INSERT ON `ptpemaco_sppdnew`.`ekstends` FOR EACH ROW
BEGIN
    DECLARE v_id_doc VARCHAR(255);
    DECLARE v_last_step INT;
    DECLARE v_id_employe VARCHAR(255); -- disesuaikan
    DECLARE v_id_pos VARCHAR(255);     -- disesuaikan
    DECLARE v_submitter_pos VARCHAR(255);

    -- Ambil nomor dokumen
    SET v_id_doc := (
        SELECT nomor_dokumen
        FROM sppds
        WHERE id = (
            SELECT id_sppd
            FROM tujuan_sppds
            WHERE id = NEW.id_tujuan
            LIMIT 1
        )
        LIMIT 1
    );

    -- Hapus langkah verifikasi tertentu
    DELETE FROM ptpemaco_esign.verif_steps
    WHERE id_doc = v_id_doc AND type IN ('check_document', 'verif_realisasi', 'verif_document', 'extend');

    -- Ambil step terakhir
    SET v_last_step := (
        SELECT step
        FROM (
            SELECT step
            FROM ptpemaco_esign.verif_steps
            WHERE id_doc = v_id_doc
            ORDER BY step DESC
            LIMIT 1
        ) AS sub_last
    );

    -- Ambil id_employe dan id_current_position dari type 'sign'
    SET v_id_employe := (
        SELECT id_employe
        FROM (
            SELECT id_employe
            FROM ptpemaco_esign.verif_steps
            WHERE id_doc = v_id_doc AND type = 'sign'
            LIMIT 1
        ) AS sub_emp
    );

    SET v_id_pos := (
        SELECT id_current_position
        FROM (
            SELECT id_current_position
            FROM ptpemaco_esign.verif_steps
            WHERE id_doc = v_id_doc AND type = 'sign'
            LIMIT 1
        ) AS sub_pos
    );

    -- Kirim notifikasi ke penandatangan
    INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id)
    VALUES (NEW.submitted_by, v_id_employe, 32, '#3');

    -- Kirim notifikasi ke pemilik SPPD
    INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id)
    VALUES (
        NEW.submitted_by,
        (
            SELECT employe_id
            FROM sppds
            WHERE id = (
                SELECT id_sppd
                FROM tujuan_sppds
                WHERE id = NEW.id_tujuan
                LIMIT 1
            )
            LIMIT 1
        ),
        46,
        '#3'
    );

    -- Insert verif_steps baru (type = extend)
    INSERT INTO ptpemaco_esign.verif_steps (
        id_employe,
        id_current_position,
        id_doc,
        step,
        type,
        status,
        ket
    )
    VALUES (
        v_id_employe,
        v_id_pos,
        v_id_doc,
        v_last_step + 1,
        'extend',
        NULL,
        NULL
    );

    -- Ambil posisi pengirim
    SET v_submitter_pos := (
        SELECT position_name
        FROM ptpemaco_apam.struktur_lengkap_oke
        WHERE employe_id = NEW.submitted_by
        LIMIT 1
    );

    -- Catat ke log
    INSERT INTO ptpemaco_esign.logs (
        employe_id,
        id_current_position,
        id_document,
        activity,
        created_at
    )
    VALUES (
        NEW.submitted_by,
        v_submitter_pos,
        v_id_doc,
        'Mengajukan penambahan hari',
        NEW.created_at
    );
END$$

-- ptpemaco_sppdnew.update
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`update`$$
CREATE TRIGGER `ptpemaco_sppdnew`.`update` AFTER UPDATE ON `ptpemaco_sppdnew`.`realisasi` FOR EACH ROW
BEGIN

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
END$$

DELIMITER ;
