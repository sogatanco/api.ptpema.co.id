-- Tujuan : menghapus log SPPD yang dobel (dokumen, user, aktivitas, dan waktu sama persis).
--          Satu baris dipertahankan (id terkecil), sisanya dihapus. Hanya log milik dokumen SPPD.
-- Sumber : tabel ptpemaco_esign.logs (dibaca view ptpemaco_sppdnew.logs_pengajuan).
-- Urutan : 1) jalankan file ini, 2) cek hasil di akhir script.
-- Rollback: hapus_log_sppd_dobel_rollback.sql (mengembalikan baris dari tabel cadangan).

USE ptpemaco_esign;

-- 1. cadangan baris yang akan dihapus
CREATE TABLE IF NOT EXISTS logs_sppd_dobel_cadangan LIKE logs;

INSERT INTO logs_sppd_dobel_cadangan
SELECT l.*
FROM logs l
JOIN (
    SELECT MIN(l2.id) AS id_simpan, l2.id_document, l2.employe_id, l2.activity, l2.created_at
    FROM logs l2
    JOIN ptpemaco_sppdnew.sppds s ON s.nomor_dokumen = l2.id_document
    GROUP BY l2.id_document, l2.employe_id, l2.activity, l2.created_at
    HAVING COUNT(*) > 1
) d ON d.id_document = l.id_document
   AND d.employe_id = l.employe_id
   AND d.activity = l.activity
   AND d.created_at <=> l.created_at
   AND l.id <> d.id_simpan
WHERE l.id NOT IN (SELECT id FROM logs_sppd_dobel_cadangan);

-- 2. hapus baris dobel
DELETE l
FROM logs l
JOIN logs_sppd_dobel_cadangan c ON c.id = l.id;

-- 3. cek: harus 0
SELECT COUNT(*) AS sisa_dobel FROM (
    SELECT 1
    FROM logs l
    JOIN ptpemaco_sppdnew.sppds s ON s.nomor_dokumen = l.id_document
    GROUP BY l.id_document, l.employe_id, l.activity, l.created_at
    HAVING COUNT(*) > 1
) x;
SELECT COUNT(*) AS baris_dihapus FROM logs_sppd_dobel_cadangan;
