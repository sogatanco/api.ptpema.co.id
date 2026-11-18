-- Bersihkan log persetujuan yang tercatat ganda di ptpemaco_esign.logs (tombol Logs SPPD dan surat ADM).
-- Penyebab: setelah persetujuan disimpan, aplikasi menjalankan UPDATE kedua pada verif_steps (menyimpan chain_sha256),
-- dan trigger after_review2 berjalan di setiap UPDATE sehingga mencatat log yang sama dua kali (mulai sekitar 2026-10-16).
-- Perbaikan kodenya sudah disertakan di aplikasi (hash rantai disimpan dalam satu UPDATE). Skrip ini hanya merapikan data lama.
--
-- Yang dihapus: baris dengan dokumen, karyawan, aktivitas, dan waktu (sampai detik) yang persis sama, mulai 2026-10-01.
-- Satu baris (id terkecil) selalu dipertahankan. Baris yang dihapus disimpan dulu di logs_duplikat_20261118 untuk rollback.
-- Aman dijalankan berulang. Rollback: logs_hapus_duplikat_rollback.sql
-- Disarankan: jalankan bagian "PERIKSA" di bawah lebih dulu untuk melihat jumlah yang akan dihapus.

-- PERIKSA (tidak mengubah data): jumlah baris duplikat sejak 2026-10-01
-- SELECT COUNT(*) AS akan_dihapus FROM `ptpemaco_esign`.`logs` l
--   JOIN (SELECT MIN(id) AS id_simpan, id_document, employe_id, activity, created_at
--         FROM `ptpemaco_esign`.`logs` WHERE created_at >= '2026-10-01'
--         GROUP BY id_document, employe_id, activity, created_at HAVING COUNT(*) > 1) d
--     ON l.id_document = d.id_document AND l.employe_id = d.employe_id AND l.activity = d.activity
--    AND l.created_at <=> d.created_at AND l.id <> d.id_simpan;

-- 1) tabel cadangan (struktur sama dengan logs)
CREATE TABLE IF NOT EXISTS `ptpemaco_esign`.`logs_duplikat_20261118` LIKE `ptpemaco_esign`.`logs`;

-- 2) salin baris duplikat yang akan dihapus ke cadangan
INSERT IGNORE INTO `ptpemaco_esign`.`logs_duplikat_20261118`
SELECT l.*
FROM `ptpemaco_esign`.`logs` l
JOIN (SELECT MIN(id) AS id_simpan, id_document, employe_id, activity, created_at
      FROM `ptpemaco_esign`.`logs`
      WHERE created_at >= '2026-10-01'
      GROUP BY id_document, employe_id, activity, created_at
      HAVING COUNT(*) > 1) d
  ON l.id_document = d.id_document AND l.employe_id = d.employe_id AND l.activity = d.activity
 AND l.created_at <=> d.created_at AND l.id <> d.id_simpan;

-- 3) hapus dari logs
DELETE FROM `ptpemaco_esign`.`logs`
WHERE id IN (SELECT id FROM `ptpemaco_esign`.`logs_duplikat_20261118`);

-- hasil: jumlah baris yang dihapus (tersimpan di cadangan)
SELECT COUNT(*) AS baris_duplikat_dihapus FROM `ptpemaco_esign`.`logs_duplikat_20261118`;
