-- Notifikasi approval tanpa ganda dan tanpa "tidak disetujui" palsu.
-- Masalah: trigger ptpemaco_esign.notif2 (SPPD) dan notif_selainsppd2 (surat) berjalan di SETIAP UPDATE tabel verif_steps, tanpa mengecek
-- apakah status berubah. Aplikasi mengubah baris verif_steps lebih dari sekali per persetujuan (SuratTteService::recordChain mengisi
-- chain_sha256 setelah status disimpan), sehingga notifikasi terbit dua kali. SuratController::readSurat mengisi baca=1 saat approver membuka
-- surat; karena status masih kosong, trigger menganggapnya penolakan dan mengirim "Dokumen tidak disetujui" (tipe 38) ke pembuat surat.
-- Perbaikan: seluruh isi kedua trigger hanya dijalankan bila NEW.status tidak kosong DAN berbeda dari OLD.status. Isi trigger selebihnya
-- tidak berubah. Notifikasi yang sudah ada TIDAK diubah. Trigger lain (after_review2, sppd_cuti_dinas*) tidak disentuh.
-- Skrip memeriksa MD5 definisi trigger saat ini, menyimpan definisi lama di ptpemaco_esign.bak_trigger_defs57 (tanpa IF NOT EXISTS: bila
-- dijalankan dua kali berhenti di sini), lalu DROP + CREATE.
-- WAJIB:  mysql --comments -u <user> -p < 57_notif_approval_tanpa_ganda.sql     Rollback: 58_notif_approval_tanpa_ganda_rollback.sql
-- Jalankan saat tidak ada persetujuan yang sedang diproses.

DROP PROCEDURE IF EXISTS ptpemaco_esign.tmp_trg57_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.tmp_trg57_precheck()
BEGIN
  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM information_schema.triggers
   WHERE (trigger_schema = 'ptpemaco_esign' AND trigger_name = 'notif2' AND MD5(REPLACE(action_statement, CHAR(13), '')) = '3ccb62e3a9898eb3d7dd4d63eebcc361') OR
      (trigger_schema = 'ptpemaco_esign' AND trigger_name = 'notif_selainsppd2' AND MD5(REPLACE(action_statement, CHAR(13), '')) = '94b1a502fdaa3bd44b74e32b5651eec3');
  IF v_ok <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Definisi trigger verif_steps di server berbeda dari yang dipakai skrip; regenerasi skrip';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_esign.tmp_trg57_precheck();
DROP PROCEDURE ptpemaco_esign.tmp_trg57_precheck;

CREATE TABLE ptpemaco_esign.bak_trigger_defs57 (
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
INSERT INTO ptpemaco_esign.bak_trigger_defs57
  (trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement)
SELECT trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement
FROM information_schema.triggers
WHERE trigger_schema = 'ptpemaco_esign' AND trigger_name IN ('notif2', 'notif_selainsppd2');

-- ptpemaco_esign.notif2
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`notif2`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_esign`.`notif2` AFTER UPDATE ON `ptpemaco_esign`.`verif_steps` FOR EACH ROW BEGIN
  -- hanya saat status BERUBAH (dari kosong ke approve/rejected): UPDATE lain pada baris yang sama (baca=1, chain_sha256, signature)
  -- tidak lagi membuat notifikasi ganda atau notifikasi "tidak disetujui" palsu
  IF NEW.status IS NOT NULL AND NOT (OLD.status <=> NEW.status) THEN 

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
  END IF;
END$$
DELIMITER ;

-- ptpemaco_esign.notif_selainsppd2
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`notif_selainsppd2`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_esign`.`notif_selainsppd2` AFTER UPDATE ON `ptpemaco_esign`.`verif_steps` FOR EACH ROW BEGIN
  -- hanya saat status BERUBAH (dari kosong ke approve/rejected): UPDATE lain pada baris yang sama (baca=1, chain_sha256, signature)
  -- tidak lagi membuat notifikasi ganda atau notifikasi "tidak disetujui" palsu
  IF NEW.status IS NOT NULL AND NOT (OLD.status <=> NEW.status) THEN
      IF (SELECT jenis_doc from documents where doc_id=NEW.id_doc limit 1)!=1 THEN 
		
          IF(NEW.status='approve') THEN
          	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT created_by from documents where doc_id=NEW.id_doc limit 1 ), 37, '#1');

  		ELSE
          	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.id_employe, (SELECT created_by from documents where doc_id=NEW.id_doc limit 1 ), 38, '#1');

          END IF;
        
      END IF;
  END IF;
END$$
DELIMITER ;

