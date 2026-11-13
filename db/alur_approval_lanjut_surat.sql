-- Pasang alur dinamis untuk SURAT saja: mengganti ptpemaco_admnew.add_verif_doc_log2 dan ptpemaco_admnew.after_update2.
-- Dipakai bila bagian SPPD (after_update1) belum bisa dipasang. Prasyarat: tabel aturan, data awal dan prosedur sudah ada (alur_approval.sql).
-- Isi kedua trigger sama persis dengan bagian yang sama di alur_approval.sql dan sudah diuji sama dengan trigger lama
-- (1.600 kombinasi divisi x penandatangan, insert dan update). Pengecekan awal memakai hash yang mengabaikan komentar dan spasi;
-- skrip berhenti tanpa mengubah apa pun bila logika trigger berbeda dari yang diuji, atau bila aturan/prosedur belum ada/ganda.
-- Definisi lama sudah tersimpan di ptpemaco_esign.bak_trigger_defs_alur (dari skrip utama); tidak ditimpa di sini.
-- WAJIB:  mysql --comments -u <user> -p < alur_approval_lanjut_surat.sql      Rollback: alur_approval_rollback.sql (atau pulihkan dari bak_trigger_defs_alur)
-- Jalankan saat tidak ada surat yang sedang dibuat/diedit.

DROP PROCEDURE IF EXISTS ptpemaco_esign.tmp_alur_surat_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.tmp_alur_surat_precheck()
BEGIN
  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM information_schema.routines
   WHERE routine_schema = 'ptpemaco_esign' AND routine_name IN ('bangun_alur', 'alur_cari');
  IF v_ok <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Prosedur bangun_alur/alur_cari belum ada: jalankan alur_approval.sql dulu';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM information_schema.tables
   WHERE table_schema = 'ptpemaco_esign' AND table_name IN ('alur_aturan', 'alur_langkah');
  IF v_ok <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabel alur_aturan/alur_langkah belum ada: jalankan alur_approval.sql dulu';
  END IF;
  IF (SELECT COUNT(*) FROM ptpemaco_esign.alur_aturan WHERE modul = 'sppd' AND aktif = 1) < 1
     OR (SELECT COUNT(*) FROM ptpemaco_esign.alur_aturan WHERE modul = 'surat' AND aktif = 1) < 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Belum ada aturan aktif untuk SPPD atau surat di alur_aturan';
  END IF;
  IF EXISTS (SELECT 1 FROM ptpemaco_esign.alur_aturan GROUP BY modul, id_base, IFNULL(id_divisi, 0) HAVING COUNT(*) > 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ada aturan alur ganda di alur_aturan (data awal terpasang dua kali): hapus yang ganda dulu';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM information_schema.triggers
   WHERE (trigger_schema = 'ptpemaco_admnew' AND trigger_name = 'add_verif_doc_log2'
          AND MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) = '69a5064cedb9c388142d37641dcfa2c7')
      OR (trigger_schema = 'ptpemaco_admnew' AND trigger_name = 'after_update2'
          AND MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) = 'dbb8c5fb9d2d7f1a57436ce689fce2e6');
  IF v_ok <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Logika add_verif_doc_log2/after_update2 di server berbeda dari yang diuji (atau sudah diganti)';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_esign.tmp_alur_surat_precheck();
DROP PROCEDURE ptpemaco_esign.tmp_alur_surat_precheck;

-- ptpemaco_admnew.add_verif_doc_log2
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_admnew`.`add_verif_doc_log2`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_admnew`.`add_verif_doc_log2` BEFORE INSERT ON `ptpemaco_admnew`.`surats` FOR EACH ROW BEGIN
    -- Alur paraf dan tanda tangan dibangun dari aturan di ptpemaco_esign.alur_aturan / alur_langkah (diatur di halaman Setting)
    CALL ptpemaco_esign.bangun_alur(
        'surat', NEW.no_document,
        (SELECT CAST(id_base AS CHAR) FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.sign_by LIMIT 1),
        NEW.id_divisi, NULL, NEW.sign_by, NEW.submitted_by, 0);

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

    -- Bangun ulang alur dari aturan di ptpemaco_esign.alur_aturan / alur_langkah
    CALL ptpemaco_esign.bangun_alur(
        'surat', NEW.no_document,
        (SELECT CAST(id_base AS CHAR) FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.sign_by LIMIT 1),
        NEW.id_divisi, NULL, NEW.sign_by, NEW.submitted_by, 0);
END$$
DELIMITER ;


SELECT trigger_schema, trigger_name, action_order, LENGTH(action_statement) AS panjang FROM information_schema.triggers
 WHERE trigger_name IN ('add_step_verif_1', 'after_update1', 'add_verif_doc_log2', 'after_update2') AND trigger_schema IN ('ptpemaco_sppdnew', 'ptpemaco_admnew') ORDER BY 1, 2;
