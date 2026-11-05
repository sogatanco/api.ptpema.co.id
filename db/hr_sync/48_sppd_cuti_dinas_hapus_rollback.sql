-- 48: rollback 47 - cuti otomatis tidak lagi ikut terhapus saat SPPD/perpanjangan diubah atau dihapus.
-- Jalankan: mysql --comments -u <user> -p < 48_sppd_cuti_dinas_hapus_rollback.sql
-- Cuti yang sudah terhapus oleh 47 tidak dikembalikan (SPPD yang disetujui ulang membuat cuti baru lewat 43/45).
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`sppd_cuti_dinas_ekstend_hapus`;
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`sppd_cuti_dinas_sppd_hapus`;
DROP TRIGGER IF EXISTS `ptpemaco_sppdnew`.`sppd_cuti_dinas_sppd_ubah`;
DROP PROCEDURE IF EXISTS `ptpemaco_sppdnew`.`hapus_cuti_dinas_sppd`;

SELECT (SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = 'ptpemaco_sppdnew' AND trigger_name IN ('sppd_cuti_dinas_ekstend_hapus', 'sppd_cuti_dinas_sppd_hapus', 'sppd_cuti_dinas_sppd_ubah')) AS trigger_tersisa,
       (SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = 'ptpemaco_sppdnew' AND routine_name = 'hapus_cuti_dinas_sppd') AS prosedur_tersisa;
