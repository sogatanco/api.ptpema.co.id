-- Lanjutan alur_approval.sql untuk SPPD: mengganti ptpemaco_sppdnew.add_step_verif_1 dan ptpemaco_sppdnew.after_update1 dengan versi yang
-- memanggil ptpemaco_esign.bangun_alur, TANPA menulis notifikasi (sama seperti trigger SPPD sekarang setelah db/notif/02).
-- Aman dijalankan bila add_step_verif_1 masih versi db/notif/02 ATAU sudah versi baru dari skrip sebelumnya (yang masih menulis notifikasi:
-- skrip ini memperbaikinya). Prasyarat: tabel aturan, data awal dan prosedur sudah ada (alur_approval.sql), tidak ada aturan ganda.
-- after_update1 di server harus persis versi db/notif/02 (hash tanpa komentar/spasi); bila berbeda skrip berhenti tanpa mengubah apa pun.
-- Sudah diuji sama dengan trigger lama: 200 pegawai tujuan + 200 SPPD tanpa pegawai tujuan, insert dan update, langkah dan notifikasi (tidak ada).
-- Definisi lama sudah tersimpan di ptpemaco_esign.bak_trigger_defs_alur (dari skrip utama); tidak ditimpa di sini.
-- WAJIB:  mysql --comments -u <user> -p < alur_approval_lanjut.sql      Rollback: alur_approval_rollback.sql
-- Jalankan saat tidak ada pengajuan SPPD baru.

DROP PROCEDURE IF EXISTS ptpemaco_esign.tmp_alur_lanjut_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.tmp_alur_lanjut_precheck()
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
  IF (SELECT COUNT(*) FROM ptpemaco_esign.alur_aturan WHERE modul = 'sppd' AND aktif = 1) < 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Belum ada aturan aktif untuk SPPD di alur_aturan';
  END IF;
  IF EXISTS (SELECT 1 FROM ptpemaco_esign.alur_aturan GROUP BY modul, id_base, IFNULL(id_divisi, 0) HAVING COUNT(*) > 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ada aturan alur ganda di alur_aturan (data awal terpasang dua kali): hapus yang ganda dulu';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM information_schema.triggers
   WHERE trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'after_update1'
     AND MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) = '4abf66c7c6d09276b89c5b14aa6b3cae';
  IF v_ok <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Logika after_update1 di server berbeda dari yang diuji (atau sudah diganti)';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM information_schema.triggers
   WHERE trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'add_step_verif_1'
     AND (MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) = 'e0b11bb0b2981c830a641fd5a2e8f87a' OR LOCATE('bangun_alur', action_statement) > 0);
  IF v_ok <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Logika add_step_verif_1 di server berbeda dari yang diuji';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_esign.tmp_alur_lanjut_precheck();
DROP PROCEDURE ptpemaco_esign.tmp_alur_lanjut_precheck;

-- notifikasi tidak dipakai lagi (ditangani aplikasi); kosongkan penanda dari data awal versi lama
UPDATE ptpemaco_esign.alur_langkah SET notif = 0 WHERE notif <> 0;

-- ptpemaco_sppdnew.add_step_verif_1
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`add_step_verif_1`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`add_step_verif_1` BEFORE INSERT ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW BEGIN
    -- Alur persetujuan SPPD dibangun dari aturan di ptpemaco_esign.alur_aturan / alur_langkah (diatur di halaman Setting).
    -- Notifikasi tidak ditulis di sini: ditangani aplikasi lewat ptpemaco_notif (db/notif/02).
    IF NEW.employe_id != '-' THEN
        CALL ptpemaco_esign.bangun_alur(
            'sppd', NEW.nomor_dokumen,
            (SELECT CAST(id_base AS CHAR) FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.employe_id LIMIT 1),
            NULL, NEW.employe_id, NULL, NEW.submitted_by, 0);
    ELSE
        CALL ptpemaco_esign.bangun_alur('sppd', NEW.nomor_dokumen, '-', NULL, NEW.submitted_by, NULL, NEW.submitted_by, 0);
    END IF;
END$$
DELIMITER ;

-- ptpemaco_sppdnew.after_update1
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`after_update1`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`after_update1` AFTER UPDATE ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW BEGIN
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

    -- Alur persetujuan SPPD dibangun ulang dari aturan di ptpemaco_esign.alur_aturan / alur_langkah.
    -- Notifikasi tidak ditulis di sini: ditangani aplikasi lewat ptpemaco_notif (db/notif/02).
    IF NEW.employe_id != '-' THEN
        CALL ptpemaco_esign.bangun_alur(
            'sppd', NEW.nomor_dokumen,
            (SELECT CAST(id_base AS CHAR) FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.employe_id LIMIT 1),
            NULL, NEW.employe_id, NULL, NEW.submitted_by, 0);
    ELSE
        CALL ptpemaco_esign.bangun_alur('sppd', NEW.nomor_dokumen, '-', NULL, NEW.submitted_by, NULL, NEW.submitted_by, 0);
    END IF;
END$$
DELIMITER ;

SELECT trigger_schema, trigger_name, action_order, LENGTH(action_statement) AS panjang FROM information_schema.triggers
 WHERE trigger_name IN ('add_step_verif_1', 'after_update1') AND trigger_schema = 'ptpemaco_sppdnew' ORDER BY 1, 2;
