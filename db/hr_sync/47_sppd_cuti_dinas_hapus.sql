-- 47: Cuti "Dinas Luar Kota" otomatis (43 dan 45) ikut TERHAPUS bila sumbernya berubah.
--   1. ekstends dihapus (aplikasi memperbarui perpanjangan dengan hapus lalu simpan ulang)  -> cuti perpanjangan itu dihapus di HR.
--      Setelah perpanjangan baru disetujui, 45 membuat cuti perpanjangan yang baru.
--   2. sppds dihapus  -> cuti SPPD itu dan semua cuti perpanjangannya dihapus di HR.
--   3. sppds diubah   -> sama seperti dihapus: SPPD yang diedit kembali menunggu persetujuan (trigger after_update1 membangun ulang langkah persetujuan
--      dan menghapus langkah perpanjangan), jadi cutinya dihapus dan dibuat lagi oleh 43 saat disetujui ulang.
-- Hanya baris yang tercatat di sppd_cuti_dinas / sppd_cuti_dinas_ekstend (dibuat otomatis oleh 43/45) yang dihapus, dan hanya bila master_id = 3.
-- Pengajuan dinas luar kota yang diisi karyawan sendiri tidak disentuh. Galat dicatat di kolom hasil dan TIDAK menggagalkan penghapusan/pengubahan SPPD.
-- Prasyarat: 43 dan 45 sudah dijalankan. Hak tambahan untuk user definer trigger:
--   GRANT DELETE ON ptpemaco_hr_production.request_time_offs TO 'ptpemaco'@'localhost';
-- WAJIB:  mysql --comments -u <user> -p < 47_sppd_cuti_dinas_hapus.sql     Rollback: 48_sppd_cuti_dinas_hapus_rollback.sql

DROP PROCEDURE IF EXISTS ptpemaco_sppdnew.tmp_47_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_sppdnew.tmp_47_precheck()
BEGIN
  DECLARE v INT;
  SELECT COUNT(*) INTO v FROM information_schema.tables WHERE table_schema = 'ptpemaco_sppdnew' AND table_name IN ('sppd_cuti_dinas', 'sppd_cuti_dinas_ekstend');
  IF v <> 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jalankan 43 dan 45 dulu (tabel sppd_cuti_dinas/sppd_cuti_dinas_ekstend belum ada)'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.triggers
   WHERE trigger_schema = 'ptpemaco_sppdnew' AND trigger_name IN ('sppd_cuti_dinas_ekstend_hapus', 'sppd_cuti_dinas_sppd_hapus', 'sppd_cuti_dinas_sppd_ubah');
  IF v <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Trigger penghapusan cuti sudah ada (sudah dijalankan?)'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.routines WHERE routine_schema = 'ptpemaco_sppdnew' AND routine_name = 'hapus_cuti_dinas_sppd';
  IF v <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Prosedur hapus_cuti_dinas_sppd sudah ada'; END IF;
END$$
DELIMITER ;
CALL ptpemaco_sppdnew.tmp_47_precheck();
DROP PROCEDURE ptpemaco_sppdnew.tmp_47_precheck;

-- Menghapus cuti SPPD dan cuti perpanjangannya (dipanggil saat sppds diubah atau dihapus)
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` PROCEDURE `ptpemaco_sppdnew`.`hapus_cuti_dinas_sppd`(IN p_id BIGINT UNSIGNED, IN p_nomor VARCHAR(255))
BEGIN
  DECLARE v_msg TEXT DEFAULT NULL;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT;
    UPDATE ptpemaco_sppdnew.sppd_cuti_dinas SET hasil = LEFT(CONCAT('galat hapus: ', IFNULL(v_msg, '')), 500) WHERE id_sppd = p_id;
    UPDATE ptpemaco_sppdnew.sppd_cuti_dinas_ekstend SET hasil = LEFT(CONCAT('galat hapus: ', IFNULL(v_msg, '')), 500)
     WHERE nomor_dokumen = p_nomor COLLATE utf8mb4_unicode_ci;
  END;

  DELETE r FROM ptpemaco_hr_production.request_time_offs r
    JOIN ptpemaco_sppdnew.sppd_cuti_dinas c ON c.request_time_off_id = r.id
   WHERE c.id_sppd = p_id AND r.master_id = 3;
  DELETE FROM ptpemaco_sppdnew.sppd_cuti_dinas WHERE id_sppd = p_id;

  DELETE r FROM ptpemaco_hr_production.request_time_offs r
    JOIN ptpemaco_sppdnew.sppd_cuti_dinas_ekstend c ON c.request_time_off_id = r.id
   WHERE c.nomor_dokumen = p_nomor COLLATE utf8mb4_unicode_ci AND r.master_id = 3;
  DELETE FROM ptpemaco_sppdnew.sppd_cuti_dinas_ekstend WHERE nomor_dokumen = p_nomor COLLATE utf8mb4_unicode_ci;
END$$
DELIMITER ;

SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`sppd_cuti_dinas_ekstend_hapus`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`sppd_cuti_dinas_ekstend_hapus` AFTER DELETE ON `ptpemaco_sppdnew`.`ekstends` FOR EACH ROW
BEGIN
  DECLARE v_msg TEXT DEFAULT NULL;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    GET DIAGNOSTICS CONDITION 1 v_msg = MESSAGE_TEXT;
    UPDATE ptpemaco_sppdnew.sppd_cuti_dinas_ekstend SET hasil = LEFT(CONCAT('galat hapus: ', IFNULL(v_msg, '')), 500) WHERE id_tujuan = OLD.id_tujuan;
  END;

  DELETE r FROM ptpemaco_hr_production.request_time_offs r
    JOIN ptpemaco_sppdnew.sppd_cuti_dinas_ekstend c ON c.request_time_off_id = r.id
   WHERE c.id_tujuan = OLD.id_tujuan AND r.master_id = 3;
  DELETE FROM ptpemaco_sppdnew.sppd_cuti_dinas_ekstend WHERE id_tujuan = OLD.id_tujuan;
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`sppd_cuti_dinas_sppd_hapus`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`sppd_cuti_dinas_sppd_hapus` AFTER DELETE ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW
BEGIN
  CALL ptpemaco_sppdnew.hapus_cuti_dinas_sppd(OLD.id, OLD.nomor_dokumen);
END$$
DELIMITER ;

DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`sppd_cuti_dinas_sppd_ubah`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`sppd_cuti_dinas_sppd_ubah` AFTER UPDATE ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW
BEGIN
  CALL ptpemaco_sppdnew.hapus_cuti_dinas_sppd(NEW.id, NEW.nomor_dokumen);
END$$
DELIMITER ;
SET SESSION sql_mode = DEFAULT;

-- Laporan: trigger_ada = 3, prosedur_ada = 1
SELECT (SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = 'ptpemaco_sppdnew' AND trigger_name IN ('sppd_cuti_dinas_ekstend_hapus', 'sppd_cuti_dinas_sppd_hapus', 'sppd_cuti_dinas_sppd_ubah')) AS trigger_ada,
       (SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = 'ptpemaco_sppdnew' AND routine_name = 'hapus_cuti_dinas_sppd') AS prosedur_ada;
