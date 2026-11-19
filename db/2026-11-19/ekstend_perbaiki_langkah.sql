-- Perbaiki extend yang diajukan SEBELUM ekstend_per_pengajuan.sql dijalankan dan masih menunggu persetujuan
-- (butuh_proses = 1, disetujui_at kosong, no_urut kosong, belum dibatalkan).
-- Trigger lama menghapus langkah 'extend' tiap ada pengajuan baru, sehingga SPPD dengan beberapa extend hanya punya SATU set langkah.
-- Skrip ini: (1) memberi no_urut per SPPD berurutan menurut id; (2) set langkah yang ada diberikan ke extend pertama (tipe extend-<n>,
-- status approval yang sudah masuk dipertahankan); (3) extend berikutnya mendapat salinan langkah baru (status kosong) bertipe extend-<n>.
-- Extend yang sudah disetujui / ditolak tidak diubah. Aman dijalankan berulang (extend yang sudah bernomor tidak diproses lagi).
-- Cadangan: ptpemaco_esign.bak_verif_extend_20261119 (langkah 'extend' dokumen terkait) dan ptpemaco_sppdnew.bak_ekstend_nomor_20261119 (id extend yang diberi nomor).
-- Prasyarat: ekstend_per_pengajuan.sql sudah dijalankan. Rollback: ekstend_perbaiki_langkah_rollback.sql
-- WAJIB:  mysql --comments -u <user> -p < ekstend_perbaiki_langkah.sql

CREATE TABLE IF NOT EXISTS ptpemaco_sppdnew.bak_ekstend_nomor_20261119 (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, id_sppd BIGINT NOT NULL, nomor_dokumen VARCHAR(255) NOT NULL, no_urut INT NOT NULL);
CREATE TABLE IF NOT EXISTS ptpemaco_esign.bak_verif_extend_20261119 LIKE ptpemaco_esign.verif_steps;

-- tabel kerja (MySQL tidak mendukung kursor pada tabel sementara yang dibuat di prosedur yang sama); dihapus di akhir
DROP TABLE IF EXISTS ptpemaco_sppdnew.tmp_ekstend_baru;
CREATE TABLE ptpemaco_sppdnew.tmp_ekstend_baru (id BIGINT UNSIGNED NOT NULL PRIMARY KEY, id_sppd BIGINT NOT NULL, nomor_dokumen VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, no_urut INT NOT NULL);

DROP PROCEDURE IF EXISTS ptpemaco_sppdnew.tmp_perbaiki_langkah_extend;
DELIMITER $$
CREATE PROCEDURE ptpemaco_sppdnew.tmp_perbaiki_langkah_extend()
BEGIN
  DECLARE selesai INT DEFAULT 0;
  DECLARE v_id BIGINT;
  DECLARE v_doc VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  DECLARE v_no INT;
  DECLARE v_pertama INT;
  DECLARE v_base INT;
  DECLARE cur CURSOR FOR SELECT id, nomor_dokumen, no_urut FROM tmp_ekstend_baru ORDER BY id_sppd, no_urut, id;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET selesai = 1;

  -- (1) extend menunggu yang belum bernomor, per SPPD berurutan menurut id; nomor dilanjutkan dari nomor tertinggi yang sudah ada
  TRUNCATE TABLE ptpemaco_sppdnew.tmp_ekstend_baru;
  INSERT INTO tmp_ekstend_baru (id, id_sppd, nomor_dokumen, no_urut)
  SELECT x.id, x.id_sppd, x.nomor_dokumen,
         x.rn + COALESCE((SELECT MAX(e2.no_urut) FROM ptpemaco_sppdnew.ekstends e2 JOIN ptpemaco_sppdnew.tujuan_sppds t2 ON t2.id = e2.id_tujuan WHERE t2.id_sppd = x.id_sppd), 0)
  FROM (
    SELECT e.id, t.id_sppd, s.nomor_dokumen, ROW_NUMBER() OVER (PARTITION BY t.id_sppd ORDER BY e.id) AS rn
    FROM ptpemaco_sppdnew.ekstends e
    JOIN ptpemaco_sppdnew.tujuan_sppds t ON t.id = e.id_tujuan
    JOIN ptpemaco_sppdnew.sppds s ON s.id = t.id_sppd
    WHERE e.butuh_proses = 1 AND e.disetujui_at IS NULL AND e.no_urut IS NULL AND e.dibatalkan_at IS NULL
  ) x;

  -- cadangan: id extend yang diberi nomor + langkah 'extend' dokumen terkait (sebelum diubah)
  INSERT IGNORE INTO ptpemaco_sppdnew.bak_ekstend_nomor_20261119 (id, id_sppd, nomor_dokumen, no_urut) SELECT id, id_sppd, nomor_dokumen, no_urut FROM tmp_ekstend_baru;
  INSERT IGNORE INTO ptpemaco_esign.bak_verif_extend_20261119
  SELECT v.* FROM ptpemaco_esign.verif_steps v WHERE v.type = 'extend' AND v.id_doc COLLATE utf8mb4_unicode_ci IN (SELECT nomor_dokumen FROM tmp_ekstend_baru);

  UPDATE ptpemaco_sppdnew.ekstends e JOIN tmp_ekstend_baru b ON b.id = e.id SET e.no_urut = b.no_urut WHERE e.no_urut IS NULL;

  -- lanjutan bila putaran sebelumnya terhenti: extend yang sudah bernomor (tercatat di cadangan) tetapi dokumennya masih punya langkah 'extend' lama
  INSERT IGNORE INTO tmp_ekstend_baru (id, id_sppd, nomor_dokumen, no_urut)
  SELECT b.id, b.id_sppd, b.nomor_dokumen, b.no_urut FROM ptpemaco_sppdnew.bak_ekstend_nomor_20261119 b
  WHERE b.nomor_dokumen COLLATE utf8mb4_unicode_ci IN (SELECT v.id_doc COLLATE utf8mb4_unicode_ci FROM ptpemaco_esign.verif_steps v WHERE v.type = 'extend');

  -- (2)(3) extend pertama tiap SPPD memakai set langkah yang ada; extend berikutnya mendapat salinan baru
  OPEN cur;
  baca: LOOP
    FETCH cur INTO v_id, v_doc, v_no;
    IF selesai = 1 THEN LEAVE baca; END IF;
    SET v_pertama = (SELECT MIN(no_urut) FROM tmp_ekstend_baru WHERE nomor_dokumen = v_doc);
    IF v_no > v_pertama THEN
      SET v_base = (SELECT MAX(step) FROM ptpemaco_esign.verif_steps WHERE id_doc = v_doc);
      INSERT INTO ptpemaco_esign.verif_steps (id_employe, id_current_position, id_doc, step, type, status, ket)
      SELECT c.id_employe, c.id_current_position, c.id_doc, v_base + c.rn, CONCAT('extend-', v_no), NULL, NULL
      FROM (SELECT s.*, ROW_NUMBER() OVER (ORDER BY s.step, s.id) AS rn FROM ptpemaco_esign.bak_verif_extend_20261119 s WHERE s.id_doc = v_doc AND s.type = 'extend') c;
    END IF;
  END LOOP;
  CLOSE cur;

  -- set langkah yang sudah ada menjadi milik extend pertama
  UPDATE ptpemaco_esign.verif_steps v
  JOIN (SELECT nomor_dokumen, MIN(no_urut) AS pertama FROM tmp_ekstend_baru GROUP BY nomor_dokumen) m ON m.nomor_dokumen = v.id_doc
  SET v.type = CONCAT('extend-', m.pertama)
  WHERE v.type = 'extend';
END$$
DELIMITER ;

CALL ptpemaco_sppdnew.tmp_perbaiki_langkah_extend();
DROP PROCEDURE ptpemaco_sppdnew.tmp_perbaiki_langkah_extend;
DROP TABLE ptpemaco_sppdnew.tmp_ekstend_baru;
