-- 44: rollback 43 - hentikan pembuatan cuti dinas otomatis.
-- Jalankan: mysql --comments -u <user> -p < 44_sppd_cuti_dinas_otomatis_rollback.sql
DROP TRIGGER IF EXISTS `ptpemaco_esign`.`sppd_cuti_dinas`;

-- Pengajuan yang sudah terlanjur dibuat di HR TIDAK dihapus otomatis. Daftarnya:
--   SELECT c.id_sppd, c.request_time_off_id, c.hasil FROM ptpemaco_sppdnew.sppd_cuti_dinas c WHERE c.request_time_off_id IS NOT NULL;
-- Bila ingin ikut dihapus (HATI-HATI, dump dulu):
--   DELETE FROM ptpemaco_hr_production.request_time_offs WHERE id IN (SELECT request_time_off_id FROM ptpemaco_sppdnew.sppd_cuti_dinas WHERE request_time_off_id IS NOT NULL AND (hasil LIKE 'dibuat%' OR hasil LIKE 'diperbarui%'));
-- Tabel catatan dibiarkan; hapus manual setelah yakin:
--   DROP TABLE ptpemaco_sppdnew.sppd_cuti_dinas;

SELECT COUNT(*) AS trigger_tersisa FROM information_schema.triggers WHERE trigger_schema = 'ptpemaco_esign' AND trigger_name = 'sppd_cuti_dinas';
