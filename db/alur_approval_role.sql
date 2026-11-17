-- Alur approval SPPD dan surat: (1) nama langkah bebas (kolom nama_step) dan (2) sumber orang baru "role" (role akses sistem di
-- ptpemaco_apam.users.roles, array JSON). Langkah dengan sumber 'role' dikerjakan oleh pemilik role itu: pemegang ke-n (urutan_sumber, mulai 0)
-- menurut urutan users.id, yang masih terdaftar di struktur_lengkap_oke.
--
--   * alur_langkah.nama_step  : nama langkah (opsional, hanya tampilan di Setting; tidak mengubah verif_steps.type)
--   * alur_langkah.nilai_teks : nama role (sumber 'role')
--   * ptpemaco_esign.alur_cari_role : prosedur baru pencari orang menurut role
--   * ptpemaco_esign.bangun_alur    : dibuat ulang; satu-satunya perubahan: sumber 'role' memanggil alur_cari_role
--                                     (alur_cari dan keempat trigger TIDAK berubah, jadi alur yang sudah ada tetap sama)
-- Definisi bangun_alur sebelum perubahan disimpan di ptpemaco_esign.bak_bangun_alur_role.
-- PRASYARAT: alur_approval.sql sudah terpasang. Berhenti tanpa mengubah apa pun bila kolom sudah ada.
-- HAK AKSES: definer trigger ('ptpemaco'@'localhost') harus boleh menjalankan alur_cari_role:
--   GRANT EXECUTE ON PROCEDURE ptpemaco_esign.alur_cari_role TO 'ptpemaco'@'localhost';
-- WAJIB:  mysql --comments -u <user> -p < alur_approval_role.sql      Rollback: alur_approval_role_rollback.sql
-- Jalankan sebelum memasang versi aplikasi (backend) yang menulis kolom baru.

DROP PROCEDURE IF EXISTS ptpemaco_esign.tmp_alur_role_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.tmp_alur_role_precheck()
BEGIN
  DECLARE v_n INT;
  SELECT COUNT(*) INTO v_n FROM information_schema.routines
   WHERE routine_schema = 'ptpemaco_esign' AND routine_name IN ('bangun_alur', 'alur_cari');
  IF v_n <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Prosedur bangun_alur/alur_cari belum ada: jalankan alur_approval.sql dulu';
  END IF;
  SELECT COUNT(*) INTO v_n FROM information_schema.columns
   WHERE table_schema = 'ptpemaco_esign' AND table_name = 'alur_langkah' AND column_name IN ('nama_step', 'nilai_teks');
  IF v_n <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kolom nama_step/nilai_teks sudah ada: skrip ini sudah pernah dijalankan';
  END IF;
  SELECT COUNT(*) INTO v_n FROM information_schema.columns
   WHERE table_schema = 'ptpemaco_apam' AND table_name = 'users' AND column_name IN ('employe_id', 'roles');
  IF v_n <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ptpemaco_apam.users tidak punya kolom employe_id dan roles';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_esign.tmp_alur_role_precheck();
DROP PROCEDURE ptpemaco_esign.tmp_alur_role_precheck;

-- ---------------------------------------------------------------- backup definisi bangun_alur
CREATE TABLE ptpemaco_esign.bak_bangun_alur_role AS
SELECT routine_name, routine_definition, sql_mode, definer, NOW() AS backed_up_at
FROM information_schema.routines
WHERE routine_schema = 'ptpemaco_esign' AND routine_name = 'bangun_alur';

-- ---------------------------------------------------------------- kolom baru
ALTER TABLE ptpemaco_esign.alur_langkah
  ADD COLUMN nama_step VARCHAR(100) NULL AFTER tipe,
  ADD COLUMN nilai_teks VARCHAR(60) NULL AFTER nilai;

-- ---------------------------------------------------------------- pencari orang menurut role
-- o_emp  : employe_id pemegang role ke-(p_off+1) menurut users.id yang aktif di struktur (NULL bila tidak ada)
-- o_nama : nama jabatan orang itu
-- o_ada  : 1 bila ditemukan
DROP PROCEDURE IF EXISTS ptpemaco_esign.alur_cari_role;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.alur_cari_role(
  IN p_role VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_off INT,
  OUT o_emp VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, OUT o_nama VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, OUT o_ada TINYINT)
BEGIN
  SET o_emp = NULL;
  SET o_nama = NULL;
  SET o_ada = 0;
  IF p_role IS NOT NULL AND p_role <> '' THEN
    SELECT s.employe_id, s.position_name INTO o_emp, o_nama
    FROM ptpemaco_apam.users u
    JOIN ptpemaco_apam.struktur_lengkap_oke s ON s.employe_id COLLATE utf8mb4_unicode_ci = u.employe_id COLLATE utf8mb4_unicode_ci
    WHERE JSON_VALID(u.roles) AND JSON_CONTAINS(u.roles, JSON_QUOTE(p_role)) AND s.position_name IS NOT NULL
    ORDER BY u.id
    LIMIT 1 OFFSET p_off;
    SET o_ada = IF(o_emp IS NULL, 0, 1);
  END IF;
END$$
DELIMITER ;

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
  DECLARE c_teks VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE v_emp VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE v_nama VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE v_ada TINYINT;
  DECLARE v_lewati TINYINT;
  DECLARE cur CURSOR FOR
    SELECT tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif, nilai_teks
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
      FETCH cur INTO c_tipe, c_sumber, c_nilai, c_off, c_cad_sumber, c_cad_nilai, c_lewati, c_ganda, c_nama_hr, c_notif, c_teks;

      IF c_sumber = 'role' THEN
        CALL ptpemaco_esign.alur_cari_role(c_teks, c_off, v_emp, v_nama, v_ada);
      ELSE
        CALL ptpemaco_esign.alur_cari(c_sumber, c_nilai, c_off, c_nama_hr, p_org, p_pengaju, p_ttd, v_emp, v_nama, v_ada);
      END IF;
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

-- ---------------------------------------------------------------- laporan
SELECT column_name FROM information_schema.columns WHERE table_schema = 'ptpemaco_esign' AND table_name = 'alur_langkah' AND column_name IN ('nama_step', 'nilai_teks');
SELECT routine_name FROM information_schema.routines WHERE routine_schema = 'ptpemaco_esign' AND routine_name IN ('bangun_alur', 'alur_cari_role');
