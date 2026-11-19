-- Approval extend per pengajuan: setiap extend punya langkah approval sendiri dengan tipe extend-1, extend-2, dst (nomor urut per SPPD).
--   ekstends.no_urut : nomor extend per SPPD (NULL = extend lama, tipe langkahnya tetap 'extend' dan alurnya tidak berubah)
--   ekstends.dibatalkan_at / dibatalkan_oleh / alasan_batal : pembatalan extend oleh admin SPPD atau pengaju (data tidak dihapus)
--   alur_aturan 'tambah_hari' : hapus_tipe tidak lagi menghapus langkah 'extend*' milik extend sebelumnya
--   ptpemaco_esign.bangun_alur_tambahan : bila session variable @alur_tipe_ganti terisi, langkah bertipe 'extend' dari aturan ditulis dengan tipe itu
--   trigger ptpemaco_sppdnew.`ss` (BEFORE INSERT ekstends) : mengisi no_urut dan menyetel @alur_tipe_ganti = 'extend-<no_urut>'
-- Aman dijalankan berulang. Rollback: ekstend_per_pengajuan_rollback.sql
-- WAJIB:  mysql --comments -u <user> -p < ekstend_per_pengajuan.sql   (jalankan saat tidak ada pengajuan extend yang sedang dibuat)

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'no_urut');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `no_urut` INT NULL DEFAULT NULL COMMENT ''nomor extend per SPPD; langkah approval bertipe extend-<no_urut>''', 'SELECT ''ekstends.no_urut sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'dibatalkan_at');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `dibatalkan_at` DATETIME NULL DEFAULT NULL COMMENT ''waktu extend dibatalkan''', 'SELECT ''ekstends.dibatalkan_at sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'dibatalkan_oleh');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `dibatalkan_oleh` VARCHAR(50) NULL DEFAULT NULL COMMENT ''employe_id pembatal''', 'SELECT ''ekstends.dibatalkan_oleh sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'alasan_batal');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `alasan_batal` VARCHAR(255) NULL DEFAULT NULL COMMENT ''alasan pembatalan''', 'SELECT ''ekstends.alasan_batal sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- extend sebelumnya (langkah 'extend' / 'extend-N') tidak lagi dihapus saat ada pengajuan extend baru
UPDATE ptpemaco_esign.alur_aturan SET hapus_tipe = 'check_document,verif_realisasi,verif_document', updated_at = NOW() WHERE modul = 'tambah_hari';

-- pembangun langkah: sama dengan versi di alur_approval_tambahan.sql, kecuali tipe langkah 'extend' boleh diganti lewat @alur_tipe_ganti
DROP PROCEDURE IF EXISTS ptpemaco_esign.bangun_alur_tambahan;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.bangun_alur_tambahan(
  IN p_modul VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_doc VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_pemohon VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_jabatan VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)
BEGIN
  DECLARE v_aturan INT UNSIGNED DEFAULT NULL;
  DECLARE v_hapus VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
  DECLARE v_step INT DEFAULT 1;
  DECLARE v_n INT DEFAULT 0;
  DECLARE v_i INT DEFAULT 0;
  DECLARE c_tipe VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE c_sumber VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE c_nilai INT;
  DECLARE c_off INT;
  DECLARE c_lewati VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE c_ganda TINYINT;
  DECLARE c_nama_hr TINYINT;
  DECLARE v_emp VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE v_nama VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE v_ada TINYINT;
  DECLARE v_lewati TINYINT;
  DECLARE cur CURSOR FOR
    SELECT tipe, sumber, nilai, urutan_sumber, lewati_jika_sama, lewati_ganda, nama_hr
    FROM ptpemaco_esign.alur_langkah
    WHERE id_aturan = v_aturan
    ORDER BY urutan, id;

  SET v_aturan = (SELECT id FROM ptpemaco_esign.alur_aturan WHERE modul = p_modul AND aktif = 1 ORDER BY id LIMIT 1);

  IF v_aturan IS NOT NULL THEN
    -- hapus langkah lama dengan tipe tertentu, lalu tambahkan langkah baru sesudah langkah terakhir yang tersisa
    SET v_hapus = (SELECT hapus_tipe FROM ptpemaco_esign.alur_aturan WHERE id = v_aturan);
    IF v_hapus IS NOT NULL AND v_hapus <> '' THEN
      DELETE FROM ptpemaco_esign.verif_steps WHERE id_doc = p_doc AND FIND_IN_SET(type, v_hapus) > 0;
    END IF;
    SET v_step = (SELECT step FROM ptpemaco_esign.verif_steps WHERE id_doc = p_doc ORDER BY step DESC LIMIT 1);
    IF v_step IS NULL THEN
      -- dokumen belum punya langkah apa pun: trigger lama gagal di sini (nomor langkah NULL), jadi gagalkan juga
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Alur tambahan SPPD: dokumen belum punya langkah approval';
    END IF;
    SET v_step = v_step + 1;

    SET v_n = (SELECT COUNT(*) FROM ptpemaco_esign.alur_langkah WHERE id_aturan = v_aturan);
    OPEN cur;
    WHILE v_i < v_n DO
      SET v_i = v_i + 1;
      FETCH cur INTO c_tipe, c_sumber, c_nilai, c_off, c_lewati, c_ganda, c_nama_hr;

      SET v_emp = NULL;
      SET v_nama = NULL;
      IF c_sumber = 'ttd_dokumen' THEN
        -- penandatangan dokumen SPPD: orang di langkah sign yang sudah ada
        SELECT id_employe, id_current_position INTO v_emp, v_nama
        FROM ptpemaco_esign.verif_steps
        WHERE id_doc = p_doc AND type = 'sign'
        LIMIT 1;
      ELSEIF c_sumber = 'pemohon_sppd' THEN
        SET v_emp = p_pemohon;
        SET v_nama = p_jabatan;
      ELSEIF c_sumber = 'puk_divisi' THEN
        CALL ptpemaco_esign.alur_cari('puk', NULL, 0, 0, c_nilai, NULL, NULL, v_emp, v_nama, v_ada);
      ELSE
        CALL ptpemaco_esign.alur_cari(c_sumber, c_nilai, c_off, c_nama_hr, NULL, NULL, NULL, v_emp, v_nama, v_ada);
      END IF;

      -- orang untuk langkah ini tidak ditemukan: gagalkan pengajuan (trigger lama juga gagal karena kolom id_employe wajib terisi),
      -- supaya tidak ada dokumen dengan alur yang bolong
      IF v_emp IS NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Alur tambahan SPPD: orang untuk salah satu langkah tidak ditemukan (periksa aturan di Setting > Alur Approval)';
      END IF;

      SET v_lewati = 0;
      IF c_ganda = 1 AND EXISTS (SELECT 1 FROM ptpemaco_esign.verif_steps x WHERE x.id_doc = p_doc AND x.id_employe = v_emp) THEN
        SET v_lewati = 1;
      END IF;

      IF v_lewati = 0 THEN
        INSERT INTO ptpemaco_esign.verif_steps
          (id_employe, id_current_position, id_doc, step, type, status, ket)
        VALUES (v_emp, v_nama, p_doc, v_step, IF(c_tipe = 'extend' AND @alur_tipe_ganti IS NOT NULL, @alur_tipe_ganti, c_tipe), NULL, NULL);
        SET v_step = v_step + 1;
      END IF;
    END WHILE;
    CLOSE cur;
  END IF;
END$$
DELIMITER ;

-- ptpemaco_sppdnew.`ss`
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`ss`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`ss` BEFORE INSERT ON `ptpemaco_sppdnew`.`ekstends` FOR EACH ROW BEGIN
    DECLARE v_id_doc VARCHAR(255);
    DECLARE v_id_sppd BIGINT;
    DECLARE v_submitter_pos VARCHAR(255);

    SET v_id_sppd := (SELECT id_sppd FROM tujuan_sppds WHERE id = NEW.id_tujuan LIMIT 1);
    SET v_id_doc := (SELECT nomor_dokumen FROM sppds WHERE id = v_id_sppd LIMIT 1);

    -- nomor extend per SPPD (tidak dipakai ulang walau extend dibatalkan); langkah approval-nya bertipe extend-<nomor>
    SET NEW.no_urut := (SELECT COALESCE(MAX(e.no_urut), 0) + 1 FROM ekstends e JOIN tujuan_sppds t ON t.id = e.id_tujuan WHERE t.id_sppd = v_id_sppd);

    -- Hapus langkah realisasi lama lalu tambah langkah extend dari aturan 'tambah_hari' (Setting); langkah extend sebelumnya dibiarkan
    SET @alur_tipe_ganti := CONCAT('extend-', NEW.no_urut);
    CALL ptpemaco_esign.bangun_alur_tambahan('tambah_hari', v_id_doc, NULL, NULL);
    SET @alur_tipe_ganti := NULL;

    SET v_submitter_pos := (SELECT position_name FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.submitted_by LIMIT 1);

    INSERT INTO ptpemaco_esign.logs (employe_id, id_current_position, id_document, activity, created_at)
    VALUES (NEW.submitted_by, v_submitter_pos, v_id_doc, CONCAT('Mengajukan penambahan hari (extend-', NEW.no_urut, ')'), NEW.created_at);
END$$
DELIMITER ;
