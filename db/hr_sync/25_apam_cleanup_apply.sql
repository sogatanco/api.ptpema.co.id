-- Membersihkan objek apam yang tidak dipakai lagi setelah data karyawan/organisasi terpusat di HR.
-- Aman: objek TIDAK dihapus, hanya diganti nama menjadi zz_hapus_<nama> (data utuh). Hapus permanen dilakukan manual
-- setelah 1-2 minggu tanpa masalah (lihat bagian akhir berkas ini). Rollback: 26_apam_cleanup_rollback.sql
--
--   nota_dinas              (4 baris)  digantikan tabel pic di HR; tidak ada rujukan kode; hanya dibaca view lama struktur_lengkap_oke
--   struktur                (82 baris) cerminan jabatan lama; hanya ditulis Master/PositionController (kode branch synch sudah tidak menulisnya)
--   personal_access_tokens  (0 baris)  bawaan Sanctum, tidak dipakai (autentikasi JWT)
--   task_approvals          (0 baris)  tabel lama, tidak ada rujukan kode (yang dipakai: project_task_approval)
--   view gsdgsd, kpi_divisi2           tidak ada rujukan kode maupun view/trigger lain
--
-- PRASYARAT (diperiksa skrip): 13 dan 21 sudah dipasang dan tidak ada view yang masih merujuk struktur/nota_dinas.
--   Karena view cadangan 13 (*_backup) merujuk keduanya, jalankan skrip ini HANYA setelah cadangan itu dihapus (TUTORIAL bagian "Setelah stabil"),
--   yang berarti 14_views_live_rollback.sql sudah tidak dipakai lagi. Deploy kode branch synch (PositionController) SEBELUM ini.
-- Jalankan:  mysql -u <user> -p < 25_apam_cleanup_apply.sql

DROP PROCEDURE IF EXISTS ptpemaco_apam.tmp_cleanup_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_apam.tmp_cleanup_precheck()
BEGIN
  DECLARE v INT;
  SELECT COUNT(*) INTO v FROM information_schema.views WHERE table_schema = 'ptpemaco_apam' AND table_name = 'employees_hr';
  IF v <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '21_employees_hr_view.sql belum dipasang'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.views WHERE table_schema = 'ptpemaco_apam' AND view_definition LIKE '%ptpemaco_hr_production%' AND table_name = 'struktur_lengkap_oke';
  IF v <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '13_views_live_apply.sql belum dipasang'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.views
   WHERE table_schema LIKE 'ptpemaco\_%' AND table_schema NOT IN ('ptpemaco_sysdev', 'ptpemaco_admdev')
     AND (view_definition LIKE '%`ptpemaco_apam`.`struktur`%' OR view_definition LIKE '%`ptpemaco_apam`.`nota_dinas`%');
  IF v <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Masih ada view yang merujuk struktur/nota_dinas (mis. *_backup dari 13); hapus dulu'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.tables WHERE table_schema = 'ptpemaco_apam' AND table_name LIKE 'zz\_hapus\_%';
  IF v <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sudah ada objek zz_hapus_*; skrip ini pernah dijalankan'; END IF;
END$$
DELIMITER ;
CALL ptpemaco_apam.tmp_cleanup_precheck();
DROP PROCEDURE ptpemaco_apam.tmp_cleanup_precheck;

RENAME TABLE
  ptpemaco_apam.nota_dinas             TO ptpemaco_apam.zz_hapus_nota_dinas,
  ptpemaco_apam.struktur               TO ptpemaco_apam.zz_hapus_struktur,
  ptpemaco_apam.personal_access_tokens TO ptpemaco_apam.zz_hapus_personal_access_tokens,
  ptpemaco_apam.task_approvals         TO ptpemaco_apam.zz_hapus_task_approvals,
  ptpemaco_apam.gsdgsd                 TO ptpemaco_apam.zz_hapus_gsdgsd,
  ptpemaco_apam.kpi_divisi2            TO ptpemaco_apam.zz_hapus_kpi_divisi2;

-- Laporan: 6 baris zz_hapus_*; uji aplikasi (login, Daily, Task, Project, Tiket, SPPD, Surat) lalu tunggu 1-2 minggu.
SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = 'ptpemaco_apam' AND table_name LIKE 'zz\_hapus\_%' ORDER BY 1;

-- Hapus permanen (MANUAL, nanti, setelah stabil; tidak bisa dibatalkan, buat dump dulu):
-- DROP TABLE ptpemaco_apam.zz_hapus_nota_dinas, ptpemaco_apam.zz_hapus_struktur, ptpemaco_apam.zz_hapus_personal_access_tokens, ptpemaco_apam.zz_hapus_task_approvals;
-- DROP VIEW  ptpemaco_apam.zz_hapus_gsdgsd, ptpemaco_apam.zz_hapus_kpi_divisi2;
