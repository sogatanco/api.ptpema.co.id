-- Mengganti PIC project yang nonaktif di HR dengan pengganti menurut HR (aturan: bila berbeda, HR yang dipakai).
-- Cakupan: 147 project belum selesai: 126 dengan pengganti dari HR + 21 project (Manajemen Investasi, SPI) dengan PIC baru = PUK dari view divisions.
-- Daftar pasangan (project, riwayat PIC lama, PIC baru) tertanam di bawah, disusun dari HR pada 2 Nov 2026. EDIT daftar ini bila ada yang tidak disetujui
-- (hapus barisnya). Skrip memeriksa tiap baris: riwayat lama masih aktif dan milik PIC lama, project belum selesai, PIC baru ada di `employees` dan aktif di HR;
-- baris yang tidak lolos dilewati dan dilaporkan.
-- Cara kerja (sama dengan penggantian PIC di aplikasi): riwayat lama active = 0 dan status 'done'; riwayat baru (status 'replacement', active = 1) ditambahkan.
-- Backup + penanda ada di tabel baru ptpemaco_apam.project_pic_sync_bak (dibuat tanpa IF NOT EXISTS: dijalankan dua kali berhenti di sini).
-- DIJALANKAN MANUAL setelah konfirmasi, jam sepi. Rollback: 30_project_pic_sync_rollback.sql
-- Jalankan:  mysql -u <user> -p < 29_project_pic_sync_from_hr.sql

CREATE TABLE ptpemaco_apam.project_pic_sync_bak (
  history_id bigint unsigned NOT NULL,
  project_id bigint unsigned NOT NULL,
  old_pic varchar(20) NOT NULL,
  old_status varchar(20) NOT NULL,
  old_active tinyint(1) NOT NULL,
  new_pic varchar(20) NOT NULL,
  new_history_id bigint unsigned DEFAULT NULL,
  backed_up_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (history_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TEMPORARY TABLE tmp_pic_map (
  project_id bigint unsigned NOT NULL,
  history_id bigint unsigned NOT NULL,
  old_pic varchar(20) NOT NULL,
  new_pic varchar(20) NOT NULL,
  PRIMARY KEY (history_id)
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tmp_pic_map (project_id, history_id, old_pic, new_pic) VALUES
  (76, 78, '202308034P', '202402093K'),
  (160, 162, '202308034P', '202402093K'),
  (161, 163, '202308034P', '202402093K'),
  (252, 258, '202308034P', '202402093K'),
  (256, 262, '202308034P', '202402093K'),
  (258, 264, '202308034P', '202402093K'),
  (259, 265, '202308034P', '202402093K'),
  (29, 30, '202306071K', '202102014'),
  (71, 73, '202306071K', '202102014'),
  (82, 84, '202306071K', '202102014'),
  (84, 86, '202306071K', '202102014'),
  (92, 94, '202306071K', '202102014'),
  (342, 348, '202306071K', '202102014'),
  (343, 349, '202306071K', '202102014'),
  (344, 350, '202306071K', '202102014'),
  (346, 352, '202306071K', '202102014'),
  (352, 358, '202306071K', '202102014'),
  (353, 359, '202306071K', '202102014'),
  (354, 360, '202306071K', '202102014'),
  (73, 75, '202209044', '202506114K'),
  (75, 77, '202209044', '202506114K'),
  (80, 82, '202209044', '202506114K'),
  (81, 83, '202209044', '202506114K'),
  (83, 85, '202209044', '202506114K'),
  (139, 141, '202209044', '202506114K'),
  (140, 142, '202209044', '202506114K'),
  (162, 164, '202209044', '202506114K'),
  (210, 216, '202107038', '202402093K'),
  (212, 218, '202107038', '202402093K'),
  (261, 267, '202107038', '202402093K'),
  (262, 268, '202107038', '202402093K'),
  (263, 269, '202107038', '202402093K'),
  (264, 270, '202107038', '202402093K'),
  (265, 271, '202107038', '202402093K'),
  (266, 272, '202107038', '202402093K'),
  (269, 275, '202107038', '202402093K'),
  (272, 278, '202107038', '202402093K'),
  (273, 279, '202107038', '202402093K'),
  (274, 280, '202107038', '202402093K'),
  (275, 281, '202107038', '202402093K'),
  (288, 294, '202107038', '202402093K'),
  (293, 299, '202107038', '202402093K'),
  (296, 302, '202107038', '202402093K'),
  (297, 303, '202107038', '202402093K'),
  (91, 93, '202110041', '202202042'),
  (93, 95, '202110041', '202202042'),
  (113, 115, '202110041', '202202042'),
  (114, 116, '202110041', '202202042'),
  (123, 125, '202110041', '202202042'),
  (124, 126, '202110041', '202202042'),
  (138, 140, '202110041', '202202042'),
  (155, 157, '202110041', '202202042'),
  (203, 209, '202110041', '202202042'),
  (205, 211, '202110041', '202202042'),
  (206, 212, '202110041', '202202042'),
  (236, 242, '202110041', '202202042'),
  (237, 243, '202110041', '202202042'),
  (238, 244, '202110041', '202202042'),
  (239, 245, '202110041', '202202042'),
  (362, 384, '202110041', '202202042'),
  (271, 277, '202307072K', '202506116K'),
  (279, 285, '202307072K', '202506116K'),
  (280, 286, '202307072K', '202506116K'),
  (281, 287, '202307072K', '202506116K'),
  (282, 288, '202307072K', '202506116K'),
  (283, 289, '202307072K', '202506116K'),
  (284, 290, '202307072K', '202506116K'),
  (285, 291, '202307072K', '202506116K'),
  (286, 292, '202307072K', '202506116K'),
  (287, 293, '202307072K', '202506116K'),
  (289, 295, '202307072K', '202506116K'),
  (290, 296, '202307072K', '202506116K'),
  (291, 297, '202307072K', '202506116K'),
  (292, 298, '202307072K', '202506116K'),
  (294, 300, '202307072K', '202506116K'),
  (299, 305, '202307072K', '202506116K'),
  (300, 306, '202307072K', '202506116K'),
  (301, 307, '202307072K', '202506116K'),
  (302, 308, '202307072K', '202506116K'),
  (303, 309, '202307072K', '202506116K'),
  (304, 310, '202307072K', '202506116K'),
  (305, 311, '202307072K', '202506116K'),
  (306, 312, '202307072K', '202506116K'),
  (307, 313, '202307072K', '202506116K'),
  (308, 314, '202307072K', '202506116K'),
  (309, 315, '202307072K', '202506116K'),
  (310, 316, '202307072K', '202506116K'),
  (311, 317, '202307072K', '202506116K'),
  (313, 319, '202307072K', '202506116K'),
  (314, 320, '202307072K', '202506116K'),
  (315, 321, '202307072K', '202506116K'),
  (316, 322, '202307072K', '202506116K'),
  (317, 323, '202307072K', '202506116K'),
  (318, 324, '202307072K', '202506116K'),
  (319, 325, '202307072K', '202506116K'),
  (320, 326, '202307072K', '202506116K'),
  (321, 327, '202307072K', '202506116K'),
  (322, 328, '202307072K', '202506116K'),
  (323, 329, '202307072K', '202506116K'),
  (324, 330, '202307072K', '202506116K'),
  (325, 331, '202307072K', '202506116K'),
  (326, 332, '202307072K', '202506116K'),
  (327, 333, '202307072K', '202506116K'),
  (328, 334, '202307072K', '202506116K'),
  (329, 335, '202307072K', '202506116K'),
  (330, 336, '202307072K', '202506116K'),
  (331, 337, '202307072K', '202506116K'),
  (332, 338, '202307072K', '202506116K'),
  (333, 339, '202307072K', '202506116K'),
  (48, 50, '202202047K', '202208044K'),
  (90, 92, '202202047K', '202208044K'),
  (121, 123, '202202047K', '202208044K'),
  (126, 128, '202202047K', '202208044K'),
  (137, 139, '202202047K', '202208044K'),
  (164, 166, '202202047K', '202208044K'),
  (180, 183, '202202047K', '202208044K'),
  (183, 186, '202202047K', '202208044K'),
  (214, 220, '202202047K', '202208044K'),
  (224, 230, '202202047K', '202208044K'),
  (201, 207, '202208043', '202304066K'),
  (190, 193, '202208043', '202506117K'),
  (191, 194, '202208043', '202506117K'),
  (193, 196, '202208043', '202506117K'),
  (194, 197, '202208043', '202506117K'),
  (197, 200, '202208043', '202506117K'),
  (198, 201, '202208043', '202506117K');

-- 21 project di divisi tanpa Manajer/Supervisor aktif di HR (Manajemen Investasi 19, Satuan Pengawas Internal 24): PIC baru = PUK dari view ptpemaco_apam.divisions.
-- Butuh view `divisions` versi HR (13_views_live_apply.sql) sudah terpasang; bila PUK kosong atau nonaktif di HR, baris dilewati oleh pemeriksaan di bawah.
INSERT INTO tmp_pic_map (project_id, history_id, old_pic, new_pic)
SELECT v.project_id, v.history_id, v.old_pic, d.PUK
FROM (VALUES
  ROW(222, 228, '202101012', 19),
  ROW(223, 229, '202101012', 19),
  ROW(249, 255, '202101012', 19),
  ROW(250, 256, '202101012', 19),
  ROW(251, 257, '202101012', 19),
  ROW(368, 390, '202104030', 19),
  ROW(87, 89, '202104033', 24),
  ROW(88, 90, '202104033', 24),
  ROW(94, 96, '202104033', 24),
  ROW(98, 100, '202104033', 24),
  ROW(100, 102, '202104033', 24),
  ROW(103, 105, '202104033', 24),
  ROW(172, 174, '202104033', 24),
  ROW(227, 233, '202104033', 24),
  ROW(228, 234, '202104033', 24),
  ROW(229, 235, '202104033', 24),
  ROW(230, 236, '202104033', 24),
  ROW(231, 237, '202104033', 24),
  ROW(232, 238, '202104033', 24),
  ROW(267, 273, '202104033', 24),
  ROW(268, 274, '202104033', 24)
) AS v(project_id, history_id, old_pic, division_id)
JOIN ptpemaco_apam.divisions d ON d.organization_id = v.division_id
WHERE d.PUK IS NOT NULL;

-- baris yang lolos pemeriksaan
CREATE TEMPORARY TABLE tmp_pic_ok AS
SELECT m.project_id, m.history_id, m.old_pic, m.new_pic
FROM tmp_pic_map m
JOIN ptpemaco_apam.project_histories x ON x.history_id = m.history_id AND x.project_id = m.project_id AND x.employe_id = m.old_pic AND x.active = 1
JOIN ptpemaco_apam.projects p ON p.project_id = m.project_id AND p.status <> 'done'
JOIN ptpemaco_apam.employees e ON e.employe_id = m.new_pic
JOIN ptpemaco_hr_production.users h ON h.employe_id COLLATE utf8mb4_unicode_ci = m.new_pic COLLATE utf8mb4_unicode_ci AND h.active = 1;

INSERT INTO ptpemaco_apam.project_pic_sync_bak (history_id, project_id, old_pic, old_status, old_active, new_pic)
SELECT x.history_id, x.project_id, x.employe_id, x.status, x.active, m.new_pic
FROM tmp_pic_ok m JOIN ptpemaco_apam.project_histories x ON x.history_id = m.history_id;

SET @first_new_id = (SELECT IFNULL(MAX(history_id), 0) FROM ptpemaco_apam.project_histories);

START TRANSACTION;
UPDATE ptpemaco_apam.project_histories x
JOIN tmp_pic_ok m ON m.history_id = x.history_id
SET x.active = 0, x.status = 'done';

INSERT INTO ptpemaco_apam.project_histories (project_id, employe_id, history_desc, file, active, status, review_by, comment, created_at, updated_at)
SELECT m.project_id, m.new_pic, 'Penyesuaian PIC mengikuti data HR (PIC sebelumnya nonaktif)', NULL, 1, 'replacement', NULL, NULL, NOW(), NOW()
FROM tmp_pic_ok m;

UPDATE ptpemaco_apam.project_pic_sync_bak b
JOIN ptpemaco_apam.project_histories n ON n.project_id = b.project_id AND n.employe_id = b.new_pic AND n.history_id > @first_new_id
SET b.new_history_id = n.history_id;
COMMIT;

-- Laporan: diganti = lolos pemeriksaan; dilewati = baris daftar yang tidak lolos (periksa manual); sisa_pic_nonaktif = project belum selesai yang PIC aktifnya masih nonaktif di HR
SELECT (SELECT COUNT(*) FROM tmp_pic_map) AS di_daftar,
       (SELECT COUNT(*) FROM ptpemaco_apam.project_pic_sync_bak WHERE new_history_id IS NOT NULL) AS diganti,
       (SELECT COUNT(*) FROM tmp_pic_map) - (SELECT COUNT(*) FROM tmp_pic_ok) AS dilewati,
       (SELECT COUNT(*) FROM ptpemaco_apam.projects p
          JOIN ptpemaco_apam.project_histories x ON x.project_id = p.project_id AND x.active = 1
          JOIN ptpemaco_hr_production.users h ON h.employe_id COLLATE utf8mb4_unicode_ci = x.employe_id COLLATE utf8mb4_unicode_ci
         WHERE p.status <> 'done' AND h.active = 0) AS sisa_pic_nonaktif;
