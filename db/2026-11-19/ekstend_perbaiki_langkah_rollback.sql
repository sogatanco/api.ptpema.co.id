-- Rollback ekstend_perbaiki_langkah.sql: kembalikan nomor extend dan langkah approval seperti sebelum perbaikan (dari tabel cadangan).
-- Langkah 'extend-<n>' pada dokumen yang diperbaiki dihapus lalu langkah 'extend' asli dipulihkan; no_urut extend yang diperbaiki dikosongkan.
-- Hanya dokumen yang tercatat di cadangan yang disentuh. Tabel cadangan dibiarkan (hapus manual bila tidak perlu).
-- WAJIB:  mysql --comments -u <user> -p < ekstend_perbaiki_langkah_rollback.sql

DELETE v FROM ptpemaco_esign.verif_steps v
WHERE v.type LIKE 'extend-%' AND v.id_doc COLLATE utf8mb4_unicode_ci IN (SELECT DISTINCT nomor_dokumen COLLATE utf8mb4_unicode_ci FROM ptpemaco_sppdnew.bak_ekstend_nomor_20261119)
  AND v.type IN (SELECT CONCAT('extend-', b.no_urut) COLLATE utf8mb4_unicode_ci FROM ptpemaco_sppdnew.bak_ekstend_nomor_20261119 b WHERE b.nomor_dokumen COLLATE utf8mb4_unicode_ci = v.id_doc COLLATE utf8mb4_unicode_ci);

INSERT IGNORE INTO ptpemaco_esign.verif_steps SELECT * FROM ptpemaco_esign.bak_verif_extend_20261119;

UPDATE ptpemaco_sppdnew.ekstends e JOIN ptpemaco_sppdnew.bak_ekstend_nomor_20261119 b ON b.id = e.id SET e.no_urut = NULL;
