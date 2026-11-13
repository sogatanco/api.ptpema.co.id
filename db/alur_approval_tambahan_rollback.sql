-- Rollback alur_approval_tambahan.sql: mengembalikan trigger insert doc, update, ss dan drafqw ke versi sebelum alur dinamis
-- (insert doc/update/ss: versi db/notif/02 tanpa notifikasi; drafqw: versi asli) dan menghapus prosedur bangun_alur_tambahan.
-- Definisi persis sebelum pemasangan juga ada di ptpemaco_esign.bak_trigger_defs_tambahan.
-- Aturan (modul real_baru, real_ubah, hotel30, tambah_hari), kolom hapus_tipe dan tabel backup DIBIARKAN; hapus manual bila perlu:
--   DELETE FROM ptpemaco_esign.alur_aturan WHERE modul IN ('real_baru', 'real_ubah', 'hotel30', 'tambah_hari');
-- Aman dijalankan ulang.
-- WAJIB:  mysql --comments -u <user> -p < alur_approval_tambahan_rollback.sql
-- ptpemaco_sppdnew.`insert doc`
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`insert doc`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`insert doc` BEFORE INSERT ON `ptpemaco_sppdnew`.`realisasi` FOR EACH ROW BEGIN

	DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='check_document';
    DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='verif_realisasi';
    DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='verif_document';

	CREATE TEMPORARY TABLE temp_table AS SELECT `step` FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) ORDER BY step DESC LIMIT 1;
    
    
    DO 0;
    
    
    DO 0;
    
    INSERT INTO ptpemaco_esign.verif_steps(id_employe, id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT PUK from ptpemaco_apam.divisions WHERE organization_id=20 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT PUK from ptpemaco_apam.divisions WHERE organization_id=20 limit 1) limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+1), 'check_document', NULL, NULL);
    
    INSERT INTO ptpemaco_esign.verif_steps(id_employe, id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT PUK from ptpemaco_apam.divisions WHERE organization_id=20 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT PUK from ptpemaco_apam.divisions WHERE organization_id=20 limit 1) limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+2), 'verif_document', NULL, NULL);
    
	
    
    
    
    INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+3), 'verif_realisasi', NULL, NULL);
    
    
    
    
    
    
    DROP TEMPORARY TABLE temp_table;
    
    INSERT INTO ptpemaco_esign.logs(employe_id,id_current_position, id_document, activity, created_at) VALUES(NEW.submitted_by,(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=NEW.submitted_by limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), 'Membuat Laporan Realisasi', NEW.created_at);
END$$
DELIMITER ;

-- ptpemaco_sppdnew.`update`
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`update`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`update` AFTER UPDATE ON `ptpemaco_sppdnew`.`realisasi` FOR EACH ROW BEGIN



	DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='check_document';

    DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='verif_realisasi';

    DELETE FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) AND type='verif_document';



	CREATE TEMPORARY TABLE temp_table AS SELECT `step` FROM ptpemaco_esign.`verif_steps` WHERE id_doc=(SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1) ORDER BY step DESC LIMIT 1;

    

    

    DO 0;

    

    

    DO 0;

    

    INSERT INTO ptpemaco_esign.verif_steps(id_employe, id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=42 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=42 limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+1), 'check_document', NULL, NULL);

    

    INSERT INTO ptpemaco_esign.verif_steps(id_employe, id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=42 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=42 limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+2), 'verif_document', NULL, NULL);

    

	

    

    

    

    INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), ((SELECT step from temp_table)+3), 'verif_realisasi', NULL, NULL);

    

    

    

    

    

    

    DROP TEMPORARY TABLE temp_table;

    

    INSERT INTO ptpemaco_esign.logs(employe_id,id_current_position, id_document, activity, created_at) VALUES(NEW.submitted_by,(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=NEW.submitted_by limit 1), (SELECT nomor_dokumen from sppds where id=NEW.id_sppd LIMIT 1), 'Update Laporan Realisasi', NEW.created_at);

END$$
DELIMITER ;

-- ptpemaco_sppdnew.`ss`
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`ss`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`ss` BEFORE INSERT ON `ptpemaco_sppdnew`.`ekstends` FOR EACH ROW BEGIN

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

    DO 0;



    -- Kirim notifikasi ke pemilik SPPD

    DO 0;



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

END$$
DELIMITER ;

-- ptpemaco_sppdnew.`drafqw`
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`drafqw`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`drafqw` BEFORE INSERT ON `ptpemaco_sppdnew`.`tigapuluhs` FOR EACH ROW BEGIN
    DECLARE v_id_doc VARCHAR(255);
    DECLARE v_last_step INT;
    DECLARE v_id_employe VARCHAR(255); 
    DECLARE v_id_pos VARCHAR(255);     
    DECLARE v_submitter_pos VARCHAR(255);

    
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
       (
            SELECT jabatan
            FROM sppds
            WHERE id = (
                SELECT id_sppd
                FROM tujuan_sppds
                WHERE id = NEW.id_tujuan
                LIMIT 1
            )
            LIMIT 1
        ),
        v_id_doc,
        v_last_step + 1,
        'pemohon',
        NULL,
        NULL
    );
    
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
        v_last_step + 2,
        'approval_hotel',
        NULL,
        NULL
    );

    
    SET v_submitter_pos := (
        SELECT position_name
        FROM ptpemaco_apam.struktur_lengkap_oke
        WHERE employe_id = NEW.submitted_by
        LIMIT 1
    );

    
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
        'Mengajukan klaim biaya hotel 30%',
        NEW.created_at
    );
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS ptpemaco_esign.bangun_alur_tambahan;
SELECT trigger_name, MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) AS md5_norm FROM information_schema.triggers
 WHERE trigger_schema = 'ptpemaco_sppdnew' AND trigger_name IN ('insert doc', 'update', 'ss', 'drafqw') ORDER BY 1;
