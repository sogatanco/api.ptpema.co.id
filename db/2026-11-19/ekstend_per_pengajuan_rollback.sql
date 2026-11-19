-- Rollback ekstend_per_pengajuan.sql: kembalikan prosedur, trigger ss dan aturan tambah_hari ke versi alur_approval_tambahan.sql,
-- ubah langkah 'extend-<n>' kembali menjadi 'extend', lalu hapus kolom baru di ekstends.
-- PERHATIAN: extend yang sudah dibatalkan (dibatalkan_at terisi) akan tampak aktif kembali setelah kolomnya dihapus.
-- WAJIB:  mysql --comments -u <user> -p < ekstend_per_pengajuan_rollback.sql

UPDATE ptpemaco_esign.verif_steps SET type = 'extend' WHERE type LIKE 'extend-%';
UPDATE ptpemaco_esign.alur_aturan SET hapus_tipe = 'check_document,verif_realisasi,verif_document,extend', updated_at = NOW() WHERE modul = 'tambah_hari';

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
        VALUES (v_emp, v_nama, p_doc, v_step, c_tipe, NULL, NULL);
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

    -- Hapus langkah lama lalu tambah langkah penambahan hari dari aturan 'tambah_hari' di ptpemaco_esign.alur_aturan / alur_langkah (diatur di halaman Setting)
    CALL ptpemaco_esign.bangun_alur_tambahan('tambah_hari', v_id_doc, NULL, NULL);

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

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'no_urut');
SET @sql := IF(@ada = 1, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` DROP COLUMN `no_urut`, DROP COLUMN `dibatalkan_at`, DROP COLUMN `dibatalkan_oleh`, DROP COLUMN `alasan_batal`', 'SELECT ''kolom sudah tidak ada'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
