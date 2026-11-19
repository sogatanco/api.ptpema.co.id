-- Penyetuju extend (dan klaim hotel 30%, yang memakai sumber 'ttd_dokumen' yang sama) = penandatangan SPPD.
-- Bila penandatangan itu sudah tidak aktif sebagai karyawan, sistem mencari pemegang jabatan yang sama yang aktif sekarang
-- (contoh: Mawardi Nur tidak aktif -> Direktur Utama yang aktif). Pencarian lewat ptpemaco_apam.employees.position_id ke struktur_lengkap_oke
-- (hanya berisi karyawan aktif); cadangan: nama jabatan yang tercatat di langkah sign. Bila tidak ada pengganti aktif, pengajuan gagal dengan pesan jelas.
-- Skrip ini juga memindahkan langkah extend yang MASIH MENUNGGU (status kosong) dari orang yang tidak aktif ke pemegang jabatan aktif.
-- Prasyarat: ekstend_per_pengajuan.sql sudah dijalankan (prosedur dibangun ulang dari versi itu). Aman dijalankan berulang.
-- Rollback: ekstend_penyetuju_aktif_rollback.sql (mengembalikan prosedur; langkah yang sudah dipindahkan dicatat di bak_penyetuju_extend_20261119)
-- WAJIB:  mysql --comments -u <user> -p < ekstend_penyetuju_aktif.sql

CREATE TABLE IF NOT EXISTS ptpemaco_esign.bak_penyetuju_extend_20261119 (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, id_employe VARCHAR(255) NOT NULL, id_current_position VARCHAR(255) NULL);

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
  DECLARE v_posisi INT;
  DECLARE v_ganti VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE v_gnama VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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

        -- penandatangan SPPD sudah tidak aktif (tidak ada di struktur aktif): cari pemegang jabatan yang sama yang aktif sekarang
        IF v_emp IS NOT NULL AND NOT EXISTS (SELECT 1 FROM ptpemaco_apam.struktur_lengkap_oke s WHERE s.employe_id COLLATE utf8mb4_unicode_ci = v_emp) THEN
          SET v_ganti = NULL;
          SET v_gnama = NULL;
          SET v_posisi = (SELECT e.position_id FROM ptpemaco_apam.employees e WHERE e.employe_id COLLATE utf8mb4_unicode_ci = v_emp LIMIT 1);
          IF v_posisi IS NOT NULL THEN
            SELECT s.employe_id, s.position_name INTO v_ganti, v_gnama
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_id = v_posisi AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
            LIMIT 1;
          END IF;
          -- cadangan: samakan lewat nama jabatan yang tercatat di langkah sign
          IF v_ganti IS NULL AND v_nama IS NOT NULL THEN
            SELECT s.employe_id, s.position_name INTO v_ganti, v_gnama
            FROM ptpemaco_apam.struktur_lengkap_oke s
            WHERE s.position_name COLLATE utf8mb4_unicode_ci = v_nama AND s.employe_id IS NOT NULL
            LIMIT 1;
          END IF;
          IF v_ganti IS NOT NULL THEN
            SET v_emp = v_ganti;
            SET v_nama = v_gnama;
          ELSE
            SET v_emp = NULL; -- tidak ada pengganti aktif: pengajuan gagal dengan pesan di bawah
          END IF;
        END IF;
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

-- pindahkan langkah extend yang menunggu dari penyetuju tidak aktif ke pemegang jabatan aktif (cadangan nilai lama dulu)
INSERT IGNORE INTO ptpemaco_esign.bak_penyetuju_extend_20261119 (id, id_employe, id_current_position)
SELECT v.id, v.id_employe, v.id_current_position FROM ptpemaco_esign.verif_steps v
WHERE v.type LIKE 'extend%' AND v.status IS NULL
  AND NOT EXISTS (SELECT 1 FROM ptpemaco_apam.struktur_lengkap_oke s WHERE s.employe_id COLLATE utf8mb4_unicode_ci = v.id_employe COLLATE utf8mb4_unicode_ci)
  AND EXISTS (SELECT 1 FROM ptpemaco_apam.employees e JOIN ptpemaco_apam.struktur_lengkap_oke s2 ON s2.position_id = e.position_id WHERE e.employe_id COLLATE utf8mb4_unicode_ci = v.id_employe COLLATE utf8mb4_unicode_ci AND s2.employe_id IS NOT NULL);

UPDATE ptpemaco_esign.verif_steps v
JOIN ptpemaco_apam.employees e ON e.employe_id COLLATE utf8mb4_unicode_ci = v.id_employe COLLATE utf8mb4_unicode_ci
JOIN (SELECT position_id, MIN(employe_id) AS employe_id, MIN(position_name) AS position_name FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id IS NOT NULL GROUP BY position_id) s ON s.position_id = e.position_id
SET v.id_employe = s.employe_id, v.id_current_position = s.position_name
WHERE v.type LIKE 'extend%' AND v.status IS NULL
  AND NOT EXISTS (SELECT 1 FROM ptpemaco_apam.struktur_lengkap_oke x WHERE x.employe_id COLLATE utf8mb4_unicode_ci = v.id_employe COLLATE utf8mb4_unicode_ci);
