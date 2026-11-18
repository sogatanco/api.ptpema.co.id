-- View hitungan_biaya: kolom uang_muka disamakan dengan aturan uang muka aplikasi
--   uang makan 100% + transportasi lokal 100% + hotel 100% (per malam) + BBM 100%; komponen lain (uang saku) 0%.
-- Rumus lama sudah sama, kecuali satu hal: bila salah satu komponen NULL (mis. tarif master_biaya belum ada untuk kategori/golongan itu),
-- seluruh uang_muka menjadi NULL (tampil 0) padahal komponen lain (hotel, BBM) ada. Sekarang tiap komponen dibungkus ifnull(...,0).
-- Kolom lain pada view TIDAK diubah; definisi aslinya disalin dari server sendiri (tidak ditimpa dari definisi lokal),
-- jadi aman terhadap perbedaan penulisan alias antar versi MySQL. View turunan (list_sppd, list_sppd2, realisasi_biaya) tetap valid
-- karena nama dan urutan kolom sama.
-- Definisi asli disimpan di tabel ptpemaco_sppdnew.view_backup_20261118 (hanya disimpan sekali, tidak ditimpa bila script diulang).
-- Aman dijalankan berulang. Rollback: hitungan_biaya_uang_muka_rollback.sql
-- Bila penanda kolom tidak ditemukan, script berhenti dengan pesan dan view tidak diubah.

CREATE TABLE IF NOT EXISTS `ptpemaco_sppdnew`.`view_backup_20261118` (
  `nama` VARCHAR(64) NOT NULL,
  `definer` VARCHAR(255) NOT NULL,
  `security_type` VARCHAR(16) NOT NULL,
  `definisi` LONGTEXT NOT NULL,
  `dibuat` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `ptpemaco_sppdnew`.`view_backup_20261118` (`nama`, `definer`, `security_type`, `definisi`)
SELECT `TABLE_NAME`, `DEFINER`, `SECURITY_TYPE`, `VIEW_DEFINITION`
FROM `information_schema`.`VIEWS`
WHERE `TABLE_SCHEMA` = 'ptpemaco_sppdnew' AND `TABLE_NAME` = 'hitungan_biaya';

-- selalu bertolak dari definisi asli (cadangan), sehingga hasilnya sama walau script dijalankan berulang
SET @def = (SELECT `definisi` FROM `ptpemaco_sppdnew`.`view_backup_20261118` WHERE `nama` = 'hitungan_biaya');
SET @definer = (SELECT `definer` FROM `ptpemaco_sppdnew`.`view_backup_20261118` WHERE `nama` = 'hitungan_biaya');
SET @sec = (SELECT `security_type` FROM `ptpemaco_sppdnew`.`view_backup_20261118` WHERE `nama` = 'hitungan_biaya');

-- awal dan akhir tiap ekspresi kolom (teks di antara penanda "AS `kolom`")
SET @m_um1 = 'AS `rate_hitung_hari`,';
SET @m_tl1 = 'AS `fix_um`,';
SET @m_ht1 = 'AS `fix_tiket`,';
SET @m_bb1 = 'AS `rate_hotel`,';
SET @m_uk1 = 'AS `total_biaya`,';

SET @a_um = LOCATE(@m_um1, @def) + CHAR_LENGTH(@m_um1);  SET @z_um = LOCATE(' AS `fix_um`', @def);
SET @a_tl = LOCATE(@m_tl1, @def) + CHAR_LENGTH(@m_tl1);  SET @z_tl = LOCATE(' AS `fix_tl`', @def);
SET @a_ht = LOCATE(@m_ht1, @def) + CHAR_LENGTH(@m_ht1);  SET @z_ht = LOCATE(' AS `fix_hotel`', @def);
SET @a_bb = LOCATE(@m_bb1, @def) + CHAR_LENGTH(@m_bb1);  SET @z_bb = LOCATE(' AS `bbm`', @def);
SET @a_uk = LOCATE(@m_uk1, @def) + CHAR_LENGTH(@m_uk1);  SET @z_uk = LOCATE(' AS `uang_muka`', @def);

SET @ok = (@def IS NOT NULL)
  AND LOCATE(@m_um1, @def) > 0 AND @z_um > @a_um
  AND LOCATE(@m_tl1, @def) > 0 AND @z_tl > @a_tl
  AND LOCATE(@m_ht1, @def) > 0 AND @z_ht > @a_ht
  AND LOCATE(@m_bb1, @def) > 0 AND @z_bb > @a_bb
  AND LOCATE(@m_uk1, @def) > 0 AND @z_uk > @a_uk;

SET @baru = CONCAT(
  '(ifnull((', SUBSTRING(@def, @a_um, @z_um - @a_um), '),0)',
  ' + ifnull((', SUBSTRING(@def, @a_tl, @z_tl - @a_tl), '),0)',
  ' + ifnull((', SUBSTRING(@def, @a_ht, @z_ht - @a_ht), '),0)',
  ' + ifnull((', SUBSTRING(@def, @a_bb, @z_bb - @a_bb), '),0))'
);

SET @sql = IF(@ok,
  CONCAT('CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`', SUBSTRING_INDEX(@definer, '@', 1), '`@`', SUBSTRING_INDEX(@definer, '@', -1),
         '` SQL SECURITY ', @sec, ' VIEW `ptpemaco_sppdnew`.`hitungan_biaya` AS ',
         LEFT(@def, @a_uk - 1), @baru, SUBSTRING(@def, @z_uk)),
  'SELECT ''Penanda kolom pada definisi view tidak ditemukan, view hitungan_biaya TIDAK diubah'' AS pesan');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT IF(@ok, 'hitungan_biaya.uang_muka diperbarui', 'DIBATALKAN: view tidak diubah') AS hasil;
