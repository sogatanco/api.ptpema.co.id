-- Tujuan : lanjutan hapus_log_sppd_dobel.sql. Menghapus log SPPD yang tampil kembar di layar:
--          dokumen, user, dan aktivitas sama, dengan waktu yang sama sampai menit (detik boleh beda).
--          Satu baris dipertahankan (id terkecil), sisanya dihapus. Hanya log milik dokumen SPPD.
-- Sumber : tabel ptpemaco_esign.logs (dibaca view ptpemaco_sppdnew.logs_pengajuan).
-- Urutan : jalankan setelah hapus_log_sppd_dobel.sql (boleh juga berdiri sendiri).
-- Rollback: hapus_log_sppd_dobel_menit_rollback.sql

USE ptpemaco_esign;

CREATE TABLE IF NOT EXISTS logs_sppd_dobel_menit_cadangan LIKE logs;

INSERT INTO logs_sppd_dobel_menit_cadangan
SELECT l.*
FROM logs l
JOIN (
    SELECT MIN(l2.id) AS id_simpan, l2.id_document, l2.employe_id, l2.activity,
           DATE_FORMAT(l2.created_at, '%Y-%m-%d %H:%i') AS menit
    FROM logs l2
    JOIN ptpemaco_sppdnew.sppds s ON s.nomor_dokumen = l2.id_document
    GROUP BY l2.id_document, l2.employe_id, l2.activity, DATE_FORMAT(l2.created_at, '%Y-%m-%d %H:%i')
    HAVING COUNT(*) > 1
) d ON d.id_document = l.id_document
   AND d.employe_id = l.employe_id
   AND d.activity = l.activity
   AND d.menit <=> DATE_FORMAT(l.created_at, '%Y-%m-%d %H:%i')
   AND l.id <> d.id_simpan
WHERE l.id NOT IN (SELECT id FROM logs_sppd_dobel_menit_cadangan);

DELETE l
FROM logs l
JOIN logs_sppd_dobel_menit_cadangan c ON c.id = l.id;

-- cek: harus 0
SELECT COUNT(*) AS sisa_dobel FROM (
    SELECT 1
    FROM logs l
    JOIN ptpemaco_sppdnew.sppds s ON s.nomor_dokumen = l.id_document
    GROUP BY l.id_document, l.employe_id, l.activity, DATE_FORMAT(l.created_at, '%Y-%m-%d %H:%i')
    HAVING COUNT(*) > 1
) x;
SELECT COUNT(*) AS baris_dihapus FROM logs_sppd_dobel_menit_cadangan;
