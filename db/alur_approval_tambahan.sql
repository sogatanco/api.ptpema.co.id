-- Alur approval TAMBAHAN SPPD dinamis (melengkapi alur_approval.sql): pengajuan realisasi (baru dan perubahan), klaim hotel 30% dan penambahan hari.
-- Keempatnya menambah langkah ke dokumen SPPD yang sudah ada, jadi memakai prosedur sendiri (ptpemaco_esign.bangun_alur_tambahan) dan
-- aturan di tabel yang sama (alur_aturan / alur_langkah) dengan modul berikut (satu aturan per modul, id_base '*'):
--   real_baru   : trigger ptpemaco_sppdnew.`insert doc` (BEFORE INSERT realisasi)  : hapus check_document, verif_realisasi, verif_document;
--                 tambah check_document (jabatan 43 Supervisor Umum) + verif_document (PUK Divisi Umum, organization_id 20) dan verif_realisasi (jabatan 6)
--   real_ubah   : trigger `update` (AFTER UPDATE realisasi)                        : sama seperti real_baru. PERUBAHAN SENGAJA: trigger lama memakai pemegang jabatan 42
--                 (Manajer Umum) untuk check_document + verif_document; sekarang PUK Divisi Umum (organization_id 20), sama dengan real_baru
--   hotel30     : trigger `drafqw` (BEFORE INSERT tigapuluhs)                      : tambah pemohon (pegawai SPPD) dan approval_hotel (penandatangan SPPD)
--   tambah_hari : trigger `ss` (BEFORE INSERT ekstends)                            : hapus check_document, verif_realisasi, verif_document, extend; tambah extend (penandatangan SPPD)
-- Log dan bagian lain trigger tidak berubah. Aturan awal disalin dari definisi trigger yang berlaku (versi db/notif/02, tanpa notifikasi).
-- Bila orang untuk sebuah langkah tidak ditemukan, pengajuan GAGAL dengan pesan jelas (seperti trigger lama), bukan lolos tanpa langkah itu.
-- PRASYARAT: alur_approval.sql sudah terpasang (tabel, prosedur alur_cari). Skrip memeriksa hash logika keempat trigger (tanpa komentar/spasi) dan berhenti
--            tanpa mengubah apa pun bila berbeda, atau bila sudah pernah dijalankan.
-- Definisi lama disimpan di ptpemaco_esign.bak_trigger_defs_tambahan (dibuat tanpa IF NOT EXISTS: dijalankan dua kali berhenti di sini).
-- WAJIB:  mysql --comments -u <user> -p < alur_approval_tambahan.sql      Rollback: alur_approval_tambahan_rollback.sql
-- Jalankan saat tidak ada pengajuan realisasi / klaim hotel / penambahan hari yang sedang dibuat.
-- HAK AKSES: definer trigger ('ptpemaco'@'localhost') harus boleh menjalankan bangun_alur_tambahan:
--   GRANT EXECUTE ON PROCEDURE ptpemaco_esign.bangun_alur_tambahan TO 'ptpemaco'@'localhost';

DROP PROCEDURE IF EXISTS ptpemaco_esign.tmp_alur_tambahan_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.tmp_alur_tambahan_precheck()
BEGIN
  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM information_schema.routines WHERE routine_schema = 'ptpemaco_esign' AND routine_name = 'alur_cari';
  IF v_ok <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Prosedur alur_cari belum ada: jalankan alur_approval.sql dulu';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM information_schema.tables WHERE table_schema = 'ptpemaco_esign' AND table_name IN ('alur_aturan', 'alur_langkah');
  IF v_ok <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabel alur_aturan/alur_langkah belum ada: jalankan alur_approval.sql dulu';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM information_schema.columns WHERE table_schema = 'ptpemaco_esign' AND table_name = 'alur_aturan' AND column_name = 'hapus_tipe';
  IF v_ok <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kolom hapus_tipe sudah ada: skrip ini sudah pernah dijalankan';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM information_schema.triggers
   WHERE
        (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'insert doc' AND MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) = '2d8cf18a4e1bf0c0756de29ce19bea42')
      OR (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'update' AND MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) = '7afa8837b4fdd544851b78fead69e618')
      OR (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'ss' AND MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) = 'fe153e943e30861afa2ec4c46f94b95e')
      OR (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'drafqw' AND MD5(REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(action_statement, '(?s)/[*].*?[*]/', ''), '--.*', ''), '[[:space:]]+', '')) = '1f0a6fafd4a97cffa498085603ffb560');
  IF v_ok <> 4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Logika trigger insert doc / update / ss / drafqw di server berbeda dari yang diuji (atau sudah diganti)';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_esign.tmp_alur_tambahan_precheck();
DROP PROCEDURE ptpemaco_esign.tmp_alur_tambahan_precheck;

-- ---------------------------------------------------------------- backup definisi trigger lama
CREATE TABLE ptpemaco_esign.bak_trigger_defs_tambahan (
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
INSERT INTO ptpemaco_esign.bak_trigger_defs_tambahan
  (trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement)
SELECT trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement
FROM information_schema.triggers
WHERE trigger_schema = 'ptpemaco_sppdnew' AND trigger_name IN ('insert doc', 'update', 'ss', 'drafqw');

-- ---------------------------------------------------------------- tabel aturan: modul lebih panjang, kolom hapus_tipe
-- hapus_tipe: daftar tipe langkah (dipisah koma) yang dihapus dari dokumen sebelum langkah baru ditambahkan
ALTER TABLE ptpemaco_esign.alur_aturan MODIFY COLUMN modul VARCHAR(30) NOT NULL;
ALTER TABLE ptpemaco_esign.alur_aturan ADD COLUMN hapus_tipe VARCHAR(120) NULL AFTER id_divisi;

-- ---------------------------------------------------------------- aturan awal (disalin dari trigger insert doc / update / ss / drafqw)
-- sumber tambahan: puk_divisi (nilai = organization_id), ttd_dokumen (orang di langkah sign dokumen), pemohon_sppd (pegawai SPPD)
INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, hapus_tipe, keterangan, created_at, updated_at)
VALUES ('real_baru', 'Pengajuan realisasi SPPD (baru)', '*', 'check_document,verif_realisasi,verif_document', 'Cek dokumen oleh Supervisor Umum, verifikasi dokumen oleh PUK Divisi Umum, lalu verifikasi realisasi oleh Direktur Umum dan Keuangan', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'check_document', 'posisi', 43, 0, NULL, NULL, NULL, 0, 0, 0),
  (@a, 2, 'verif_document', 'puk_divisi', 20, 0, NULL, NULL, NULL, 0, 0, 0),
  (@a, 3, 'verif_realisasi', 'posisi', 6, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, hapus_tipe, keterangan, created_at, updated_at)
VALUES ('real_ubah', 'Pengajuan realisasi SPPD (perubahan)', '*', 'check_document,verif_realisasi,verif_document', 'Cek dokumen oleh Supervisor Umum, verifikasi dokumen oleh PUK Divisi Umum, lalu verifikasi realisasi oleh Direktur Umum dan Keuangan', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'check_document', 'posisi', 43, 0, NULL, NULL, NULL, 0, 0, 0),
  (@a, 2, 'verif_document', 'puk_divisi', 20, 0, NULL, NULL, NULL, 0, 0, 0),
  (@a, 3, 'verif_realisasi', 'posisi', 6, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, hapus_tipe, keterangan, created_at, updated_at)
VALUES ('hotel30', 'Klaim biaya hotel 30%', '*', NULL, 'Pemohon (pegawai SPPD), lalu persetujuan penandatangan SPPD', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'pemohon', 'pemohon_sppd', NULL, 0, NULL, NULL, NULL, 0, 0, 0),
  (@a, 2, 'approval_hotel', 'ttd_dokumen', NULL, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, hapus_tipe, keterangan, created_at, updated_at)
VALUES ('tambah_hari', 'Penambahan hari SPPD', '*', 'check_document,verif_realisasi,verif_document,extend', 'Persetujuan penandatangan SPPD atas penambahan hari; langkah realisasi lama dihapus', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'extend', 'ttd_dokumen', NULL, 0, NULL, NULL, NULL, 0, 0, 0);

-- ---------------------------------------------------------------- pembangun langkah tambahan
-- p_modul   : real_baru | real_ubah | hotel30 | tambah_hari
-- p_doc     : nomor dokumen SPPD (kolom id_doc di verif_steps)
-- p_pemohon, p_jabatan : pegawai SPPD dan jabatannya (hanya dipakai sumber pemohon_sppd, yaitu klaim hotel 30%)
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

-- ---------------------------------------------------------------- trigger
-- ptpemaco_sppdnew.`insert doc`
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`insert doc`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`insert doc` BEFORE INSERT ON `ptpemaco_sppdnew`.`realisasi` FOR EACH ROW BEGIN
    -- Langkah tambahan pengajuan realisasi dibangun dari aturan 'real_baru' di ptpemaco_esign.alur_aturan / alur_langkah (diatur di halaman Setting)
    CALL ptpemaco_esign.bangun_alur_tambahan('real_baru', (SELECT nomor_dokumen FROM sppds WHERE id = NEW.id_sppd LIMIT 1), NULL, NULL);

    INSERT INTO ptpemaco_esign.logs(employe_id, id_current_position, id_document, activity, created_at) VALUES(NEW.submitted_by, (SELECT position_name FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.submitted_by LIMIT 1), (SELECT nomor_dokumen FROM sppds WHERE id = NEW.id_sppd LIMIT 1), 'Membuat Laporan Realisasi', NEW.created_at);
END$$
DELIMITER ;

-- ptpemaco_sppdnew.`update`
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`update`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`update` AFTER UPDATE ON `ptpemaco_sppdnew`.`realisasi` FOR EACH ROW BEGIN
    -- Langkah tambahan pembaruan realisasi dibangun dari aturan 'real_ubah' di ptpemaco_esign.alur_aturan / alur_langkah (diatur di halaman Setting)
    CALL ptpemaco_esign.bangun_alur_tambahan('real_ubah', (SELECT nomor_dokumen FROM sppds WHERE id = NEW.id_sppd LIMIT 1), NULL, NULL);

    INSERT INTO ptpemaco_esign.logs(employe_id, id_current_position, id_document, activity, created_at) VALUES(NEW.submitted_by, (SELECT position_name FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.submitted_by LIMIT 1), (SELECT nomor_dokumen FROM sppds WHERE id = NEW.id_sppd LIMIT 1), 'Update Laporan Realisasi', NEW.created_at);
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

-- ptpemaco_sppdnew.`drafqw`
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`drafqw`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`drafqw` BEFORE INSERT ON `ptpemaco_sppdnew`.`tigapuluhs` FOR EACH ROW BEGIN
    DECLARE v_id_doc VARCHAR(255);
    DECLARE v_submitter_pos VARCHAR(255);

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

    -- Langkah klaim hotel 30% dibangun dari aturan 'hotel30' di ptpemaco_esign.alur_aturan / alur_langkah (diatur di halaman Setting)
    CALL ptpemaco_esign.bangun_alur_tambahan(
        'hotel30', v_id_doc,
        (SELECT employe_id FROM sppds WHERE id = (SELECT id_sppd FROM tujuan_sppds WHERE id = NEW.id_tujuan LIMIT 1) LIMIT 1),
        (SELECT jabatan FROM sppds WHERE id = (SELECT id_sppd FROM tujuan_sppds WHERE id = NEW.id_tujuan LIMIT 1) LIMIT 1));

    SET v_submitter_pos := (
        SELECT position_name
        FROM ptpemaco_apam.struktur_lengkap_oke
        WHERE employe_id = NEW.submitted_by
        LIMIT 1
    );

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
        'Mengajukan klaim biaya hotel 30%',
        NEW.created_at
    );
END$$
DELIMITER ;

-- ---------------------------------------------------------------- laporan
-- PERINGATAN: bila kolom pemegang bernilai 0, pengajuan yang memakai langkah itu akan GAGAL (sama seperti trigger lama), sampai ada pemegangnya
-- atau aturannya diubah di Setting > Alur Approval.
SELECT 'real_baru dan real_ubah: PUK divisi 20 (Divisi Umum)' AS sumber, COUNT(*) AS pemegang FROM ptpemaco_apam.divisions d JOIN ptpemaco_apam.struktur_lengkap_oke s ON s.employe_id = d.PUK WHERE d.organization_id = 20
UNION ALL SELECT 'real_baru dan real_ubah: jabatan 6', COUNT(*) FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 6;
SELECT modul, COUNT(*) AS aturan FROM ptpemaco_esign.alur_aturan GROUP BY modul;
SELECT trigger_name, action_order, LENGTH(action_statement) AS panjang FROM information_schema.triggers
 WHERE trigger_schema = 'ptpemaco_sppdnew' AND trigger_name IN ('insert doc', 'update', 'ss', 'drafqw') ORDER BY 1;
