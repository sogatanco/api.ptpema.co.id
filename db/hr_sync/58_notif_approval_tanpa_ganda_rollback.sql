-- Jalankan dengan:  mysql --comments -u <user> -p < 58_notif_approval_tanpa_ganda_rollback.sql
-- Mengembalikan trigger ptpemaco_esign.notif2 dan notif_selainsppd2 ke definisi sebelum 57. Isi lama tertanam di berkas ini,
-- sama dengan ptpemaco_esign.bak_trigger_defs57. Aman dijalankan ulang. Tabel backup dibiarkan; hapus manual setelah stabil.

-- ptpemaco_esign.notif2
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`notif2`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_esign`.`notif2` AFTER UPDATE ON `ptpemaco_esign`.`verif_steps` FOR EACH ROW BEGIN 

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
END$$
DELIMITER ;

-- ptpemaco_esign.notif_selainsppd2
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`notif_selainsppd2`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_esign`.`notif_selainsppd2` AFTER UPDATE ON `ptpemaco_esign`.`verif_steps` FOR EACH ROW BEGIN
    IF (SELECT jenis_doc from documents where doc_id=NEW.id_doc limit 1)!=1 THEN 
		
        IF(NEW.status='approve') THEN
        	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT created_by from documents where doc_id=NEW.id_doc limit 1 ), 37, '#1');

		ELSE
        	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT created_by from documents where doc_id=NEW.id_doc limit 1 ), 38, '#1');

        END IF;
        
    END IF;
END$$
DELIMITER ;

