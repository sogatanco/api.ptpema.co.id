-- Membatalkan 25_apam_cleanup_apply.sql: mengembalikan nama asli objek (selama belum di-DROP permanen). Aman diulang.
-- Jalankan:  mysql -u <user> -p < 26_apam_cleanup_rollback.sql
-- Bila Master/PositionController versi lama (menulis `struktur`) dikembalikan, jalankan ini lebih dulu.

DROP PROCEDURE IF EXISTS ptpemaco_apam.tmp_cleanup_restore;
DELIMITER $$
CREATE PROCEDURE ptpemaco_apam.tmp_cleanup_restore()
BEGIN
  DECLARE v INT;
  SELECT COUNT(*) INTO v FROM information_schema.tables WHERE table_schema = 'ptpemaco_apam' AND table_name LIKE 'zz\_hapus\_%';
  IF v = 6 THEN
    RENAME TABLE
      ptpemaco_apam.zz_hapus_nota_dinas             TO ptpemaco_apam.nota_dinas,
      ptpemaco_apam.zz_hapus_struktur               TO ptpemaco_apam.struktur,
      ptpemaco_apam.zz_hapus_personal_access_tokens TO ptpemaco_apam.personal_access_tokens,
      ptpemaco_apam.zz_hapus_task_approvals         TO ptpemaco_apam.task_approvals,
      ptpemaco_apam.zz_hapus_gsdgsd                 TO ptpemaco_apam.gsdgsd,
      ptpemaco_apam.zz_hapus_kpi_divisi2            TO ptpemaco_apam.kpi_divisi2;
  ELSEIF v <> 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jumlah objek zz_hapus_* bukan 6 (sebagian sudah di-DROP?); pulihkan dari dump';
  END IF;
END$$
DELIMITER ;
CALL ptpemaco_apam.tmp_cleanup_restore();
DROP PROCEDURE ptpemaco_apam.tmp_cleanup_restore;

SELECT table_name FROM information_schema.tables WHERE table_schema = 'ptpemaco_apam' AND table_name IN ('nota_dinas','struktur','personal_access_tokens','task_approvals','gsdgsd','kpi_divisi2') ORDER BY 1;
