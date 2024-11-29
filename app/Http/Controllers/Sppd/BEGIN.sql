BEGIN

    IF NEW.employe_id != '-' THEN
		INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, NEW.employe_id, 30, '#2');
        
    	IF (SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=1 OR (SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=2 THEN
        
        	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1), 32, '#3');
    
        	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1),(SELECT position_name from ptpemaco_apam.positions WHERE position_id=13 limit 1),NEW.nomor_dokumen,1,'verifikasi_biaya', NULL, NULL);
          
        	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position, id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE id_base=4 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE id_base=4 limit 1),NEW.nomor_dokumen,2,'paraf', NULL, NULL);
            INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position, id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE id_base=4 limit 1,1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE id_base=4 limit 1,1),NEW.nomor_dokumen,3,'paraf', NULL, NULL);
            INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE id_base=4 limit 2,1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE id_base=4 limit 2,1),NEW.nomor_dokumen,4,'paraf', NULL, NULL);
            
            INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE id_base=3 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE id_base=3 limit 1),NEW.nomor_dokumen,5,'sign', NULL, NULL);
            
     
        ELSEIF (SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=3 THEN
        	
            INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1), 32, '#3');
        
        	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1),(SELECT position_name from ptpemaco_apam.positions WHERE position_id=13 limit 1),NEW.nomor_dokumen,1,'verifikasi_biaya', NULL, NULL);
            
        	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=109 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=109 limit 1),NEW.nomor_dokumen,2,'paraf', NULL, NULL);
            
            INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=1 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=1 limit 1),NEW.nomor_dokumen,3,'sign', NULL, NULL);
            
        
        ELSEIF (SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=4 THEN
        
        INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1), 32, '#3');
        	
        	IF (SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1) != NEW.employe_id THEN
            
            
            	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1),(SELECT position_name from ptpemaco_apam.positions WHERE position_id=13 limit 1),NEW.nomor_dokumen,1,'verifikasi_biaya', NULL, NULL);

            	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),NEW.nomor_dokumen,2,'paraf', NULL, NULL);

                INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),NEW.nomor_dokumen,3,'sign', NULL, NULL);
            ELSE
            	 INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1),(SELECT position_name from ptpemaco_apam.positions WHERE position_id=13 limit 1),NEW.nomor_dokumen,1,'verifikasi_biaya', NULL, NULL);

            	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),NEW.nomor_dokumen,2,'sign', NULL, NULL);
            
            END IF;
          	
           
            
            
            ELSEIF(SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=6 OR (SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=7 THEN
            
            INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1), 32, '#3');
            
    		INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1),(SELECT position_name from ptpemaco_apam.positions WHERE position_id=13 limit 1),NEW.nomor_dokumen,1,'verifikasi_biaya', NULL, NULL);
            
				IF ((SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1)=(SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1)) OR (SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) is NULL THEN
            		INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),NEW.nomor_dokumen,2,'paraf', NULL, NULL);
            		
                    INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),NEW.nomor_dokumen,3,'sign', NULL, NULL);            
             
            ELSE
            	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) limit 1),NEW.nomor_dokumen,2,'paraf', NULL, NULL);
                
        		INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),NEW.nomor_dokumen,3,'paraf', NULL, NULL);

        		INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),NEW.nomor_dokumen,4,'sign', NULL, NULL);
        	END IF;
	
        ELSEIF(SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=8 OR (SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=9 OR (SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=10 THEN
       
    		IF (SELECT `manager_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) is NOT NULL THEN
            
            	INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT `manager_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1), 32, '#3');
                
                INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT `manager_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT `manager_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) limit 1),NEW.nomor_dokumen,1,'paraf', NULL, NULL);
            
            ELSE
            	
    			IF (SELECT `supervisor_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) is NOT NULL THEN
                	
                    INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT `supervisor_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1), 32, '#3');
                    
                    INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT `supervisor_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT `supervisor_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) limit 1),NEW.nomor_dokumen,1,'paraf', NULL, NULL);
                ELSE
                
                	IF (SELECT `manager_eksekutif` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) is NOT NULL THEN
                    	 
                         INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT `manager_eksekutif` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1), 32, '#3');
                        
                        INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT `manager_eksekutif` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT `manager_eksekutif` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) limit 1), NEW.nomor_dokumen,1,'paraf', NULL, NULL);
                    
                    END IF;
                
                END IF;
             
            END IF;
            
            INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1),(SELECT position_name from ptpemaco_apam.positions WHERE position_id=13 limit 1),NEW.nomor_dokumen,2,'verifikasi_biaya', NULL, NULL);
            
            IF(SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) is NOT NULL THEN
            	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.employe_id LIMIT 1) limit 1),NEW.nomor_dokumen,3,'sign', NULL, NULL);
            
            ELSE
            	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),NEW.nomor_dokumen,3,'sign', NULL, NULL);           
            
            END IF;
        
        ELSEIF(SELECT `id_base` FROM ptpemaco_apam.`struktur_lengkap_oke` WHERE employe_id=NEW.employe_id LIMIT 1)=5 THEN
        
        	 INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1), 32, '#3');
        	
        	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1),(SELECT position_name from ptpemaco_apam.positions WHERE position_id=13 limit 1),NEW.nomor_dokumen,1,'verifikasi_biaya', NULL, NULL);
        	
        	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=109 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=109 limit 1),NEW.nomor_dokumen,2,'paraf', NULL, NULL);
            
            INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=1 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=1 limit 1),NEW.nomor_dokumen,3,'sign', NULL, NULL);
        
        END IF;
        
    ELSE
    	 INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id) VALUES(NEW.submitted_by, (SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1), 32, '#3');
         
    	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=13 limit 1),(SELECT position_name from ptpemaco_apam.positions WHERE position_id=13 limit 1),NEW.nomor_dokumen,1,'verifikasi_biaya', NULL, NULL);
        
        IF (SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.submitted_by LIMIT 1) is NULL THEN
        	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=6 limit 1),NEW.nomor_dokumen,2,'paraf', NULL, NULL);
        ELSE
        	INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.submitted_by LIMIT 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE employe_id=(SELECT `dir_terkait` FROM ptpemaco_apam.`atasan_terkait` WHERE `employe_id`=NEW.submitted_by LIMIT 1) limit 1),NEW.nomor_dokumen,2,'paraf', NULL, NULL);  
        
        END IF;
        INSERT INTO ptpemaco_esign.verif_steps(id_employe,id_current_position,id_doc, step, type, status, ket) VALUES ((SELECT employe_id from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),(SELECT position_name from ptpemaco_apam.struktur_lengkap_oke WHERE position_id=3 limit 1),NEW.nomor_dokumen,3,'sign', NULL, NULL);

    END IF;
END