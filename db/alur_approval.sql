-- Alur approval SPPD dan surat (ADM) dinamis: aturan alur disimpan di tabel dan diatur dari halaman Setting (tab "Alur Approval"),
-- bukan lagi tertulis di trigger.
--
--   * ptpemaco_esign.alur_aturan   : satu baris = satu aturan alur.
--                                    SPPD  -> dipilih menurut id_base pengaju SPPD ('-' = SPPD tanpa pegawai tujuan).
--                                    Surat -> dipilih menurut id_base penandatangan ('*' = semua penandatangan lain), boleh khusus satu divisi.
--   * ptpemaco_esign.alur_langkah  : langkah berurutan tiap aturan (verifikasi_biaya / paraf / sign) beserta sumber orangnya.
--   * ptpemaco_esign.alur_cari     : mencari orang untuk satu langkah (jabatan tertentu, urutan ke-n pemegang id_base, PUK divisi,
--                                    direktur divisi, atasan terkait/direktur terkait pengaju, penandatangan).
--   * ptpemaco_esign.bangun_alur   : membaca aturan lalu menulis ptpemaco_esign.verif_steps (dan notifikasi SPPD).
--   * 4 trigger memanggil bangun_alur:
--       ptpemaco_sppdnew.add_step_verif_1 (BEFORE INSERT sppds)   ptpemaco_sppdnew.after_update1 (AFTER UPDATE sppds)
--       ptpemaco_admnew.add_verif_doc_log2 (BEFORE INSERT surats) ptpemaco_admnew.after_update2 (BEFORE UPDATE surats)
--     Bagian lain trigger tidak berubah (notifikasi ke pegawai tujuan, log, hapus langkah lama, penyimpanan dokumen e-sign).
--
-- Aturan awal (seed) disalin dari percabangan trigger yang berlaku sekarang, sehingga hasil alurnya SAMA. Setelah ini alur diubah dari Setting.
-- PRASYARAT: 33, 39 (atau hanya 33), 51 dan 55 sudah dijalankan. Skrip memeriksa MD5 definisi keempat trigger dan berhenti
--            (tanpa mengubah apa pun) bila berbeda, karena aturan awal hanya terjamin sama dengan definisi itu.
-- Definisi lama disimpan di ptpemaco_esign.bak_trigger_defs_alur (dibuat tanpa IF NOT EXISTS: dijalankan dua kali berhenti di sini).
-- WAJIB:  mysql --comments -u <user> -p < alur_approval.sql        Rollback: alur_approval_rollback.sql
-- Jalankan saat tidak ada pengajuan SPPD baru dan tidak ada surat yang sedang dibuat/diedit. Surat/SPPD yang sudah ada tidak berubah.
-- HAK AKSES: definer trigger ('ptpemaco'@'localhost') harus boleh menjalankan kedua prosedur, dan user database aplikasi Laravel (untuk "Uji alur"
-- di Setting) juga. Bila skrip dijalankan oleh user lain (mis. root), jalankan sesudahnya:
--   GRANT EXECUTE ON PROCEDURE ptpemaco_esign.bangun_alur TO 'ptpemaco'@'localhost';
--   GRANT EXECUTE ON PROCEDURE ptpemaco_esign.alur_cari   TO 'ptpemaco'@'localhost';
--   (dan GRANT yang sama untuk user aplikasi bila berbeda dari 'ptpemaco'@'localhost')

DROP PROCEDURE IF EXISTS ptpemaco_esign.tmp_alur_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.tmp_alur_precheck()
BEGIN
  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM information_schema.triggers
   WHERE (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'add_step_verif_1' AND MD5(REPLACE(action_statement, CHAR(13), '')) = 'febf811ae91aa089fab47ea9d72c0c5f') OR
      (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name = 'after_update1' AND MD5(REPLACE(action_statement, CHAR(13), '')) IN ('4ff0c25c978fef05390e8404bcebe9bb', '6826cb5221fb45f475a1b28ffd277634')) OR
      (trigger_schema = 'ptpemaco_admnew' AND trigger_name = 'add_verif_doc_log2' AND MD5(REPLACE(action_statement, CHAR(13), '')) = 'a254a607c6b9a7dce533528a73151cef') OR
      (trigger_schema = 'ptpemaco_admnew' AND trigger_name = 'after_update2' AND MD5(REPLACE(action_statement, CHAR(13), '')) = '0c437a06f5b716d7e97d144a88934774');
  IF v_ok <> 4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Definisi trigger SPPD/surat di server berbeda dari hasil 33/39/51/55 (jalankan itu dulu, atau regenerasi skrip)';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id IN (1, 3, 6, 13, 51, 68);
  IF v_ok < 6 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jabatan 1, 3, 6, 13, 51 atau 68 tidak ada pemegangnya di struktur_lengkap_oke';
  END IF;
  SELECT COUNT(*) INTO v_ok FROM ptpemaco_hr_production.positions WHERE (id = 13 AND TRIM(position_name) = 'Manajer Keuangan') OR (id = 51 AND position_name IS NOT NULL);
  IF v_ok <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jabatan HR 13 (Manajer Keuangan) atau 51 tidak ditemukan';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_esign.tmp_alur_precheck();
DROP PROCEDURE ptpemaco_esign.tmp_alur_precheck;

-- ---------------------------------------------------------------- backup definisi trigger lama
CREATE TABLE ptpemaco_esign.bak_trigger_defs_alur (
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
INSERT INTO ptpemaco_esign.bak_trigger_defs_alur
  (trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement)
SELECT trigger_schema, trigger_name, event_object_table, action_timing, event_manipulation, definer, sql_mode, action_statement
FROM information_schema.triggers
WHERE (trigger_schema = 'ptpemaco_sppdnew' AND trigger_name IN ('add_step_verif_1', 'after_update1'))
   OR (trigger_schema = 'ptpemaco_admnew' AND trigger_name IN ('add_verif_doc_log2', 'after_update2'));

-- ---------------------------------------------------------------- tabel aturan
-- modul    : 'sppd' atau 'surat'
-- id_base  : daftar id_base dipisah koma. SPPD: id_base pegawai tujuan, atau '-' (SPPD tanpa pegawai tujuan).
--            Surat: id_base penandatangan, atau '*' (penandatangan lain yang tidak punya aturan sendiri).
-- id_divisi: hanya surat; NULL = berlaku untuk semua divisi, isi organization_id = khusus divisi itu (didahulukan).
CREATE TABLE ptpemaco_esign.alur_aturan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  modul VARCHAR(10) NOT NULL,
  nama VARCHAR(150) NOT NULL,
  id_base VARCHAR(60) NOT NULL,
  id_divisi BIGINT NULL,
  keterangan VARCHAR(255) NULL,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  diubah_oleh VARCHAR(255) NULL,
  created_at TIMESTAMP NULL DEFAULT NULL,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_alur_aturan_modul (modul, aktif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- tipe              : verifikasi_biaya | paraf | sign
-- sumber            : posisi (nilai = position_id) | id_base (nilai = id_base, urutan_sumber = pemegang ke-n mulai 0) | puk | direktur_divisi
--                     | dir_terkait | penandatangan
-- cadangan_*        : dipakai bila sumber utama tidak punya nilai sama sekali (khusus dir_terkait: pegawai tidak punya direktur terkait)
-- lewati_jika_sama  : NULL | 'pengaju' | 'penandatangan' : langkah dilewati bila orangnya sama dengan pengaju/penandatangan
-- lewati_ganda      : 1 = langkah dilewati bila orang itu sudah ada di alur dokumen yang sama
-- nama_hr           : 1 = nama jabatan di langkah diambil dari master jabatan HR (bukan dari struktur_lengkap_oke); hanya untuk sumber posisi
-- notif             : 1 = orang di langkah ini diberi notifikasi pengajuan SPPD (tipe 32); surat tidak memakainya
CREATE TABLE ptpemaco_esign.alur_langkah (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  id_aturan INT UNSIGNED NOT NULL,
  urutan INT NOT NULL,
  tipe VARCHAR(20) NOT NULL,
  sumber VARCHAR(20) NOT NULL,
  nilai INT NULL,
  urutan_sumber INT NOT NULL DEFAULT 0,
  cadangan_sumber VARCHAR(20) NULL,
  cadangan_nilai INT NULL,
  lewati_jika_sama VARCHAR(20) NULL,
  lewati_ganda TINYINT(1) NOT NULL DEFAULT 0,
  nama_hr TINYINT(1) NOT NULL DEFAULT 0,
  notif TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_alur_langkah_aturan (id_aturan, urutan),
  CONSTRAINT fk_alur_langkah_aturan FOREIGN KEY (id_aturan) REFERENCES ptpemaco_esign.alur_aturan (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- aturan awal: SPPD (disalin dari add_step_verif_1 / after_update1)
-- kolom: (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif)

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, keterangan, created_at, updated_at)
VALUES ('sppd', 'Komisaris Utama dan Komisaris', '1,2', 'Verifikasi Manajer Keuangan, paraf tiga direktur, ditandatangani Direktur Utama', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'verifikasi_biaya', 'posisi', 13, 0, NULL, NULL, NULL, 0, 1, 1),
  (@a, 2, 'paraf', 'id_base', 4, 0, NULL, NULL, NULL, 0, 0, 0),
  (@a, 3, 'paraf', 'id_base', 4, 1, NULL, NULL, NULL, 0, 0, 0),
  (@a, 4, 'paraf', 'id_base', 4, 2, NULL, NULL, NULL, 0, 0, 0),
  (@a, 5, 'sign', 'id_base', 3, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, keterangan, created_at, updated_at)
VALUES ('sppd', 'Direktur Utama', '3', 'Verifikasi Manajer Keuangan, paraf Sekretaris Komisaris, ditandatangani Komisaris Utama', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'verifikasi_biaya', 'posisi', 13, 0, NULL, NULL, NULL, 0, 1, 1),
  (@a, 2, 'paraf', 'posisi', 68, 0, NULL, NULL, NULL, 0, 0, 0),
  (@a, 3, 'sign', 'posisi', 1, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, keterangan, created_at, updated_at)
VALUES ('sppd', 'Direktur', '4', 'Verifikasi Manajer Keuangan, paraf Direktur Umum dan Keuangan (dilewati bila pengaju sendiri), ditandatangani Direktur Utama', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'verifikasi_biaya', 'posisi', 13, 0, NULL, NULL, NULL, 0, 1, 1),
  (@a, 2, 'paraf', 'posisi', 6, 0, NULL, NULL, 'pengaju', 0, 0, 0),
  (@a, 3, 'sign', 'posisi', 3, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, keterangan, created_at, updated_at)
VALUES ('sppd', 'Komite', '5', 'Verifikasi Manajer Keuangan, paraf Sekretaris Komisaris, ditandatangani Komisaris Utama', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'verifikasi_biaya', 'posisi', 13, 0, NULL, NULL, NULL, 0, 1, 1),
  (@a, 2, 'paraf', 'posisi', 68, 0, NULL, NULL, NULL, 0, 0, 0),
  (@a, 3, 'sign', 'posisi', 1, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, keterangan, created_at, updated_at)
VALUES ('sppd', 'Manajer Eksekutif dan Manajer', '6,7', 'Verifikasi Manajer Keuangan, paraf direktur terkait (bila ada), paraf Direktur Umum dan Keuangan, ditandatangani Direktur Utama', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'verifikasi_biaya', 'posisi', 13, 0, NULL, NULL, NULL, 0, 1, 1),
  (@a, 2, 'paraf', 'dir_terkait', NULL, 0, NULL, NULL, NULL, 1, 0, 0),
  (@a, 3, 'paraf', 'posisi', 6, 0, NULL, NULL, NULL, 1, 0, 0),
  (@a, 4, 'sign', 'posisi', 3, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, keterangan, created_at, updated_at)
VALUES ('sppd', 'Supervisor dan Staf', '8,9', 'Paraf PUK divisi, verifikasi Manajer Keuangan, ditandatangani direktur terkait (bila tidak ada: Direktur Utama)', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'paraf', 'puk', NULL, 0, NULL, NULL, NULL, 0, 0, 1),
  (@a, 2, 'verifikasi_biaya', 'posisi', 13, 0, NULL, NULL, NULL, 0, 1, 0),
  (@a, 3, 'sign', 'dir_terkait', NULL, 0, 'posisi', 3, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, keterangan, created_at, updated_at)
VALUES ('sppd', 'Tenaga Pendukung', '10', 'Verifikasi Manajer Keuangan, ditandatangani PUK divisi', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'verifikasi_biaya', 'posisi', 13, 0, NULL, NULL, NULL, 0, 1, 1),
  (@a, 2, 'sign', 'puk', NULL, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, keterangan, created_at, updated_at)
VALUES ('sppd', 'SPPD tanpa pegawai tujuan', '-', 'Verifikasi Manajer Keuangan, paraf direktur terkait pengaju (bila tidak ada: Direktur Umum dan Keuangan), ditandatangani Direktur Utama', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'verifikasi_biaya', 'posisi', 13, 0, NULL, NULL, NULL, 0, 1, 1),
  (@a, 2, 'paraf', 'dir_terkait', NULL, 0, 'posisi', 6, NULL, 0, 0, 0),
  (@a, 3, 'sign', 'posisi', 3, 0, NULL, NULL, NULL, 0, 0, 0);

-- ---------------------------------------------------------------- aturan awal: surat (disalin dari add_verif_doc_log2 / after_update2)
INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, id_divisi, keterangan, created_at, updated_at)
VALUES ('surat', 'Ditandatangani Direktur Utama', '3', NULL, 'Paraf PUK, Sekretaris Perusahaan, direktur divisi; lalu Direktur Utama menandatangani', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'paraf', 'puk', NULL, 0, NULL, NULL, 'penandatangan', 1, 0, 0),
  (@a, 2, 'paraf', 'posisi', 51, 0, NULL, NULL, 'penandatangan', 1, 1, 0),
  (@a, 3, 'paraf', 'direktur_divisi', NULL, 0, NULL, NULL, 'penandatangan', 1, 0, 0),
  (@a, 4, 'sign', 'penandatangan', NULL, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, id_divisi, keterangan, created_at, updated_at)
VALUES ('surat', 'Ditandatangani Direktur', '4', NULL, 'Paraf PUK dan Sekretaris Perusahaan; lalu direktur menandatangani', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'paraf', 'puk', NULL, 0, NULL, NULL, 'penandatangan', 1, 0, 0),
  (@a, 2, 'paraf', 'posisi', 51, 0, NULL, NULL, 'penandatangan', 1, 1, 0),
  (@a, 3, 'sign', 'penandatangan', NULL, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, id_divisi, keterangan, created_at, updated_at)
VALUES ('surat', 'Ditandatangani Manajer Eksekutif', '6', NULL, 'Paraf PUK dan Sekretaris Perusahaan; lalu penandatangan menandatangani', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'paraf', 'puk', NULL, 0, NULL, NULL, 'penandatangan', 1, 0, 0),
  (@a, 2, 'paraf', 'posisi', 51, 0, NULL, NULL, 'penandatangan', 1, 1, 0),
  (@a, 3, 'sign', 'penandatangan', NULL, 0, NULL, NULL, NULL, 0, 0, 0);

INSERT INTO ptpemaco_esign.alur_aturan (modul, nama, id_base, id_divisi, keterangan, created_at, updated_at)
VALUES ('surat', 'Penandatangan lainnya', '*', NULL, 'Paraf Sekretaris Perusahaan; lalu penandatangan menandatangani', NOW(), NOW());
SET @a = LAST_INSERT_ID();
INSERT INTO ptpemaco_esign.alur_langkah (id_aturan, urutan, tipe, sumber, nilai, urutan_sumber, cadangan_sumber, cadangan_nilai, lewati_jika_sama, lewati_ganda, nama_hr, notif) VALUES
  (@a, 1, 'paraf', 'posisi', 51, 0, NULL, NULL, 'penandatangan', 1, 1, 0),
  (@a, 2, 'sign', 'penandatangan', NULL, 0, NULL, NULL, NULL, 0, 0, 0);

-- ---------------------------------------------------------------- pencari orang untuk satu langkah
-- o_emp  : employe_id orang yang ditemukan (NULL bila tidak ada)
-- o_nama : nama jabatan untuk kolom id_current_position
-- o_ada  : 1 bila sumber punya nilai (untuk dir_terkait: pegawai punya direktur terkait, walaupun orangnya tidak ada di struktur)
DROP PROCEDURE IF EXISTS ptpemaco_esign.alur_cari;
DELIMITER $$
CREATE PROCEDURE ptpemaco_esign.alur_cari(
  IN p_sumber VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_nilai INT, IN p_off INT, IN p_nama_hr TINYINT,
  IN p_org BIGINT, IN p_pengaju VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, IN p_ttd VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  OUT o_emp VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, OUT o_nama VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, OUT o_ada TINYINT)
BEGIN
  DECLARE v_dir VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
  DECLARE v_dt VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL;
  SET o_emp = NULL;
  SET o_nama = NULL;
  SET o_ada = 0;

  IF p_sumber = 'posisi' THEN
    IF p_nama_hr = 1 THEN
      SELECT s.employe_id, p.position_name INTO o_emp, o_nama
      FROM ptpemaco_apam.struktur_lengkap_oke s
      JOIN ptpemaco_hr_production.positions p ON p.id = p_nilai
      WHERE s.position_id = p_nilai AND s.employe_id IS NOT NULL AND p.position_name IS NOT NULL
      LIMIT 1;
    ELSE
      SELECT s.employe_id, s.position_name INTO o_emp, o_nama
      FROM ptpemaco_apam.struktur_lengkap_oke s
      WHERE s.position_id = p_nilai AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
      LIMIT 1;
    END IF;
    SET o_ada = IF(o_emp IS NULL, 0, 1);

  ELSEIF p_sumber = 'id_base' THEN
    SELECT s.employe_id, s.position_name INTO o_emp, o_nama
    FROM ptpemaco_apam.struktur_lengkap_oke s
    WHERE s.id_base = p_nilai AND s.employe_id IS NOT NULL AND s.position_name IS NOT NULL
    LIMIT 1 OFFSET p_off;
    SET o_ada = IF(o_emp IS NULL, 0, 1);

  ELSEIF p_sumber = 'puk' THEN
    SELECT d.PUK, s.position_name INTO o_emp, o_nama
    FROM ptpemaco_apam.divisions d
    JOIN ptpemaco_apam.struktur_lengkap_oke s ON s.employe_id = d.PUK
    WHERE d.organization_id = p_org AND d.PUK IS NOT NULL AND s.position_name IS NOT NULL
    LIMIT 1;
    SET o_ada = IF(o_emp IS NULL, 0, 1);

  ELSEIF p_sumber = 'direktur_divisi' THEN
    SET v_dir = (SELECT dir FROM ptpemaco_apam.divisions WHERE organization_id = p_org LIMIT 1);
    -- divisi langsung di bawah Direktur Utama (tidak punya direktur): paraf direktur dipegang Direktur Umum dan Keuangan (jabatan 6)
    IF (SELECT id_base FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = v_dir LIMIT 1) = 3 THEN
      SET v_dir = (SELECT employe_id FROM ptpemaco_apam.struktur_lengkap_oke WHERE position_id = 6 LIMIT 1);
    END IF;
    SELECT s.employe_id, s.position_name INTO o_emp, o_nama
    FROM ptpemaco_apam.struktur_lengkap_oke s
    WHERE s.employe_id = v_dir
    LIMIT 1;
    SET o_ada = IF(o_emp IS NULL, 0, 1);

  ELSEIF p_sumber = 'dir_terkait' THEN
    SET v_dt = (SELECT dir_terkait FROM ptpemaco_apam.atasan_terkait WHERE employe_id = p_pengaju LIMIT 1);
    SET o_ada = IF(v_dt IS NULL, 0, 1);
    SELECT a.dir_terkait, s.position_name INTO o_emp, o_nama
    FROM ptpemaco_apam.atasan_terkait a
    JOIN ptpemaco_apam.struktur_lengkap_oke s ON s.employe_id = a.dir_terkait
    WHERE a.employe_id = p_pengaju AND a.dir_terkait IS NOT NULL AND s.position_name IS NOT NULL
    LIMIT 1;

  ELSEIF p_sumber = 'penandatangan' THEN
    SELECT s.employe_id, s.position_name INTO o_emp, o_nama
    FROM ptpemaco_apam.struktur_lengkap_oke s
    WHERE s.employe_id = p_ttd
    LIMIT 1;
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
-- p_notif   : 1 = tulis notifikasi SPPD, 0 = tidak (simulasi di halaman Setting)
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

-- ---------------------------------------------------------------- trigger SPPD
-- ptpemaco_sppdnew.add_step_verif_1
SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`add_step_verif_1`;
DELIMITER $$
CREATE DEFINER=`ptpemaco`@`localhost` TRIGGER `ptpemaco_sppdnew`.`add_step_verif_1` BEFORE INSERT ON `ptpemaco_sppdnew`.`sppds` FOR EACH ROW BEGIN
    -- Alur persetujuan SPPD dibangun dari aturan di ptpemaco_esign.alur_aturan / alur_langkah (diatur di halaman Setting)
    IF NEW.employe_id != '-' THEN
        INSERT INTO ptpemaco_apam.notifications
            (actor, recipient, entity_type_id, entity_id)
        VALUES
            (NEW.submitted_by, NEW.employe_id, 30, '#2');

        CALL ptpemaco_esign.bangun_alur(
            'sppd', NEW.nomor_dokumen,
            (SELECT CAST(id_base AS CHAR) FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.employe_id LIMIT 1),
            NULL, NEW.employe_id, NULL, NEW.submitted_by, 1);
    ELSE
        CALL ptpemaco_esign.bangun_alur('sppd', NEW.nomor_dokumen, '-', NULL, NEW.submitted_by, NULL, NEW.submitted_by, 1);
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

    -- Alur persetujuan SPPD dibangun ulang dari aturan di ptpemaco_esign.alur_aturan / alur_langkah
    IF NEW.employe_id != '-' THEN
        INSERT INTO ptpemaco_apam.notifications
            (actor, recipient, entity_type_id, entity_id)
        VALUES
            (NEW.submitted_by, NEW.employe_id, 30, '#2');

        CALL ptpemaco_esign.bangun_alur(
            'sppd', NEW.nomor_dokumen,
            (SELECT CAST(id_base AS CHAR) FROM ptpemaco_apam.struktur_lengkap_oke WHERE employe_id = NEW.employe_id LIMIT 1),
            NULL, NEW.employe_id, NULL, NEW.submitted_by, 1);
    ELSE
        CALL ptpemaco_esign.bangun_alur('sppd', NEW.nomor_dokumen, '-', NULL, NEW.submitted_by, NULL, NEW.submitted_by, 1);
    END IF;
END$$
DELIMITER ;

-- ---------------------------------------------------------------- trigger surat
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

-- ---------------------------------------------------------------- laporan
SELECT modul, COUNT(*) AS aturan FROM ptpemaco_esign.alur_aturan GROUP BY modul;
SELECT trigger_schema, trigger_name, action_order FROM information_schema.triggers
 WHERE trigger_name IN ('add_step_verif_1', 'after_update1', 'add_verif_doc_log2', 'after_update2') ORDER BY 1, 2;
