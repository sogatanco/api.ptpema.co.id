-- Rollback alur_approval_role.sql: bangun_alur dikembalikan ke versi alur_approval.sql, alur_cari_role dan kolom baru dihapus.
-- PERINGATAN: langkah yang memakai sumber 'role' harus diubah/dihapus dari Setting lebih dulu (setelah rollback, sumber 'role' tidak dikenal).
-- WAJIB:  mysql --comments -u <user> -p < alur_approval_role_rollback.sql

-- ---------------------------------------------------------------- pembangun alur
-- p_modul   : 'sppd' atau 'surat'
-- p_doc     : nomor dokumen (kolom id_doc di verif_steps)
-- p_kunci   : sppd = id_base pegawai tujuan atau '-' ; surat = id_base penandatangan (NULL bila tidak dikenal)
-- p_org     : organization_id divisi (surat: id_divisi surat; sppd: NULL = diambil dari divisi pengaju)
-- p_pengaju : sppd = pegawai tujuan (atau pengaju bila tanpa pegawai tujuan); dipakai untuk dir_terkait dan "lewati bila sama pengaju"
-- p_ttd     : surat = penandatangan (sign_by)
-- p_actor   : pembuat notifikasi SPPD
-- p_notif   : 1 = tulis notifikasi ke ptpemaco_apam.notifications (lama), 0 = tidak. Trigger selalu memakai 0.
DROP PROCEDURE IF EXISTS ptpemaco_esign.bangun_alur;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.bangun_alur(
  IN p_modul VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_doc VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_kunci VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_org BIGINT,
  IN p_pengaju VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_ttd VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_actor VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_notif TINYINT)
BEGIN
  DECLARE v_aturan INT UNSIGNED DEFAULT NULL;
  DECLARE v_step INT DEFAULT 1;
  DECLARE v_n INT DEFAULT 0;
  DECLARE v_i INT DEFAULT 0;
  DECLARE c_tipe VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE c_sumber VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE c_nilai INT;
  DECLARE c_off INT;
  DECLARE c_cad_sumber VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE c_cad_nilai INT;
  DECLARE c_lewati VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE c_ganda TINYINT;
  DECLARE c_nama_hr TINYINT;
  DECLARE c_notif TINYINT;
  DECLARE v_emp VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE v_nama VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE v_ada TINYINT;
  DECLARE v_lewati TINYINT;
  DECLARE cur CURSOR FOR
    SELECT tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif
    FROM ptpemaco_esign.alur_langkah
    WHERE id_aturan = v_aturan
    ORDER BY urutan, id;

  IF p_modul = 'sppd' THEN
    IF p_org IS NULL THEN
      SET p_org = (SELECT organization_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = p_pengaju LIMIT 1);
    END IF;
    SET v_aturan = (SELECT id FROM ptpemaco_esign.alur_aturan
                    WHERE modul = 'sppd' AND aktif = 1 AND FIND_IN_SET(p_kunci, id_base) > 0 ORDER BY id LIMIT 1);
  ELSE
    -- urutan prioritas: divisi + penandatangan, divisi + semua penandatangan, semua divisi + penandatangan, semua divisi + semua penandatangan
    SET v_aturan = (SELECT id FROM ptpemaco_esign.alur_aturan
                    WHERE modul = 'surat' AND aktif = 1 AND id_divisi = p_org AND FIND_IN_SET(p_kunci, id_base) > 0 ORDER BY id LIMIT 1);
    IF v_aturan IS NULL THEN
      SET v_aturan = (SELECT id FROM ptpemaco_esign.alur_aturan
                      WHERE modul = 'surat' AND aktif = 1 AND id_divisi = p_org AND FIND_IN_SET('*', id_base) > 0 ORDER BY id LIMIT 1);
    END IF;
    IF v_aturan IS NULL THEN
      SET v_aturan = (SELECT id FROM ptpemaco_esign.alur_aturan
                      WHERE modul = 'surat' AND aktif = 1 AND id_divisi IS NULL AND FIND_IN_SET(p_kunci, id_base) > 0 ORDER BY id LIMIT 1);
    END IF;
    IF v_aturan IS NULL THEN
      SET v_aturan = (SELECT id FROM ptpemaco_esign.alur_aturan
                      WHERE modul = 'surat' AND aktif = 1 AND id_divisi IS NULL AND FIND_IN_SET('*', id_base) > 0 ORDER BY id LIMIT 1);
    END IF;
  END IF;

  IF v_aturan IS NOT NULL THEN
    SET v_n = (SELECT COUNT(*) FROM ptpemaco_esign.alur_langkah WHERE id_aturan = v_aturan);
    OPEN cur;
    WHILE v_i < v_n DO
      SET v_i = v_i + 1;
      FETCH cur INTO c_tipe, c_sumber, c_nilai, c_off, c_cad_sumber, c_cad_nilai, c_lewati, c_ganda, c_nama_hr, c_notif;

      CALL ptpemaco_esign.alur_cari(c_sumber, c_nilai, c_off, c_nama_hr, p_org, p_pengaju, p_ttd, v_emp, v_nama, v_ada);
      IF v_ada = 0 AND c_cad_sumber IS NOT NULL THEN
        CALL ptpemaco_esign.alur_cari(c_cad_sumber, c_cad_nilai, 0, 0, p_org, p_pengaju, p_ttd, v_emp, v_nama, v_ada);
      END IF;

      SET v_lewati = 0;
      IF v_emp IS NULL THEN
        SET v_lewati = 1;
      ELSEIF c_lewati = 'pengaju' AND v_emp = p_pengaju THEN
        SET v_lewati = 1;
      ELSEIF c_lewati = 'penandatangan' AND v_emp = p_ttd THEN
        SET v_lewati = 1;
      ELSEIF c_ganda = 1 AND EXISTS (SELECT 1 FROM ptpemaco_esign.verif_steps x WHERE x.id_doc = p_doc AND x.id_employe = v_emp) THEN
        SET v_lewati = 1;
      END IF;

      IF v_lewati = 0 THEN
        INSERT INTO ptpemaco_esign.verif_steps
          (id_employe, id_current_position, id_doc, step, type, status, ket)
        VALUES (v_emp, v_nama, p_doc, v_step, c_tipe, NULL, NULL);
        IF c_notif = 1 AND p_notif = 1 THEN
          INSERT INTO ptpemaco_apam.notifications (actor, recipient, entity_type_id, entity_id)
          VALUES (p_actor, v_emp, 32, '#3');
        END IF;
        SET v_step = v_step + 1;
      END IF;
    END WHILE;
    CLOSE cur;
  END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS ptpemaco_esign.alur_cari_role;
ALTER TABLE ptpemaco_esign.alur_langkah DROP COLUMN nama_step, DROP COLUMN nilai_teks;
