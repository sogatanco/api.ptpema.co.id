-- Bersihkan objek apam yang tidak dipakai (hasil audit):
--   tabel struktur (tidak ada kode yang memakai; hanya model StructureMaster yang tidak dipanggil)  -> diganti nama zz_hapus_struktur (data utuh)
--   tabel task_approvals (kosong; kode memakai project_task_approval)                              -> diganti nama zz_hapus_task_approvals
--   6 view sisa percobaan: kpi_divisi, kpi_divisi2, gsdgsd, divisions_oke, atasan_terkait_oke, struktur_lengkap_oke_oke -> di-DROP;
--   definisinya disimpan di ptpemaco_apam.bak41_views dan tertanam di rollback 42.
-- Prasyarat: 35 sudah dijalankan. Skrip berhenti sendiri bila: objek tidak lengkap, definisi view berbeda dari yang diaudit, task_approvals berisi data,
-- atau ada view/trigger/routine lain yang menyebut salah satu objek ini. Dijalankan dua kali berhenti di sini.
-- Setelah stabil 1-2 minggu, zz_hapus_struktur dan zz_hapus_task_approvals boleh di-DROP (dump dulu); zz_hapus_struktur harus dihapus sebelum zz_hapus_positions.
-- WAJIB:  mysql --comments -u <user> -p < 41_apam_bersih_tabel_tak_terpakai.sql     Rollback: 42_apam_bersih_tabel_tak_terpakai_rollback.sql

DROP PROCEDURE IF EXISTS ptpemaco_apam.tmp_41_precheck;
DELIMITER $$
CREATE PROCEDURE ptpemaco_apam.tmp_41_precheck()
BEGIN
  DECLARE v INT;
  SELECT COUNT(*) INTO v FROM information_schema.tables WHERE table_schema = 'ptpemaco_apam' AND table_type = 'BASE TABLE' AND table_name IN ('struktur', 'task_approvals');
  IF v <> 2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Tabel struktur/task_approvals tidak lengkap (sudah dijalankan?)'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.tables WHERE table_schema = 'ptpemaco_apam' AND table_name IN ('zz_hapus_struktur', 'zz_hapus_task_approvals', 'bak41_views');
  IF v <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'zz_hapus_struktur/zz_hapus_task_approvals/bak41_views sudah ada'; END IF;
  IF (SELECT COUNT(*) FROM ptpemaco_apam.task_approvals) <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'task_approvals berisi data'; END IF;
  SELECT COUNT(*) INTO v FROM information_schema.views WHERE table_schema = 'ptpemaco_apam' AND
      (table_name = 'atasan_terkait_oke' AND MD5(REPLACE(view_definition, CHAR(13), '')) = 'c2b895ab04ffb440d1a145cbb17552ca') OR
      (table_name = 'divisions_oke' AND MD5(REPLACE(view_definition, CHAR(13), '')) = 'c00bdcd4085df5e73d6778c691b85561') OR
      (table_name = 'gsdgsd' AND MD5(REPLACE(view_definition, CHAR(13), '')) = '104e13dd2f43cba93c1d1d5adad353fa') OR
      (table_name = 'kpi_divisi' AND MD5(REPLACE(view_definition, CHAR(13), '')) = '343f8b04ae567fce589b7d4aad6c0c66') OR
      (table_name = 'kpi_divisi2' AND MD5(REPLACE(view_definition, CHAR(13), '')) = 'c1a9fd0950c39dafbc9ae30f3ed759c7') OR
      (table_name = 'struktur_lengkap_oke_oke' AND MD5(REPLACE(view_definition, CHAR(13), '')) = '068c1624fb9ee2bcaf14c66e41a9b9ae');
  IF v <> 6 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Definisi view berbeda dari yang diaudit; regenerasi skrip'; END IF;
  -- tidak boleh ada view/trigger/routine lain (selain 6 view itu) yang menyebut objek ini
  SELECT COUNT(*) INTO v FROM (
    SELECT table_schema, table_name AS nama, view_definition AS d FROM information_schema.views
     WHERE NOT (table_schema = 'ptpemaco_apam' AND table_name IN ('atasan_terkait_oke', 'divisions_oke', 'gsdgsd', 'kpi_divisi', 'kpi_divisi2', 'struktur_lengkap_oke_oke'))
    UNION ALL SELECT trigger_schema, trigger_name, action_statement FROM information_schema.triggers
    UNION ALL SELECT routine_schema, routine_name, routine_definition FROM information_schema.routines WHERE routine_name NOT LIKE 'tmp\_%'
  ) x
  WHERE x.table_schema LIKE 'ptpemaco%'
    AND (x.d REGEXP '(^|[^A-Za-z0-9_])(struktur|task_approvals)([^A-Za-z0-9_]|$)'
      OR x.d REGEXP 'ptpemaco_apam[`.]+(kpi_divisi|kpi_divisi2|gsdgsd|divisions_oke|atasan_terkait_oke|struktur_lengkap_oke_oke)([^A-Za-z0-9_]|$)');
  IF v <> 0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Masih ada view/trigger/routine lain yang menyebut objek ini'; END IF;
END$$
DELIMITER ;
CALL ptpemaco_apam.tmp_41_precheck();
DROP PROCEDURE ptpemaco_apam.tmp_41_precheck;

CREATE TABLE ptpemaco_apam.bak41_views (
  view_name VARCHAR(64) NOT NULL PRIMARY KEY,
  definer VARCHAR(288) NOT NULL,
  view_definition LONGTEXT NOT NULL,
  backed_up_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO ptpemaco_apam.bak41_views (view_name, definer, view_definition)
SELECT table_name, definer, view_definition FROM information_schema.views
WHERE table_schema = 'ptpemaco_apam' AND table_name IN ('atasan_terkait_oke', 'divisions_oke', 'gsdgsd', 'kpi_divisi', 'kpi_divisi2', 'struktur_lengkap_oke_oke');

RENAME TABLE ptpemaco_apam.struktur TO ptpemaco_apam.zz_hapus_struktur,
             ptpemaco_apam.task_approvals TO ptpemaco_apam.zz_hapus_task_approvals;

DROP VIEW ptpemaco_apam.`atasan_terkait_oke`;
DROP VIEW ptpemaco_apam.`divisions_oke`;
DROP VIEW ptpemaco_apam.`gsdgsd`;
DROP VIEW ptpemaco_apam.`kpi_divisi`;
DROP VIEW ptpemaco_apam.`kpi_divisi2`;
DROP VIEW ptpemaco_apam.`struktur_lengkap_oke_oke`;

-- Laporan: tabel_zz = 2, view_tersisa = 0, view_tersimpan = 6
SELECT
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ptpemaco_apam' AND table_name IN ('zz_hapus_struktur', 'zz_hapus_task_approvals')) AS tabel_zz,
  (SELECT COUNT(*) FROM information_schema.views WHERE table_schema = 'ptpemaco_apam' AND table_name IN ('atasan_terkait_oke', 'divisions_oke', 'gsdgsd', 'kpi_divisi', 'kpi_divisi2', 'struktur_lengkap_oke_oke')) AS view_tersisa,
  (SELECT COUNT(*) FROM ptpemaco_apam.bak41_views) AS view_tersimpan;

-- OPSIONAL (setelah yakin): tabel backup hasil skrip 37
-- DROP TABLE ptpemaco_apam.bak37_project_notifications, ptpemaco_apam.bak37_project_task_approval, ptpemaco_apam.bak37_project_task_favorite;
