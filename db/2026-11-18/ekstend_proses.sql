-- Extend SPPD (penambahan hari / tujuan baru) yang disetujui masuk proses divisi umum (bila perlu tiket) lalu divisi keuangan (uang muka).
--   ekstends: jenis (perpanjang / tujuan_baru), kategori + nama + moda untuk tujuan baru, perlu_tiket, dan status proses (by_umum, uangmuka).
--   proses_tiket / proses_termin: kolom id_ekstend (0 = SPPD awal) agar tiket dan termin extend terpisah dari proses SPPD awal.
--   proses_termin: kunci unik menjadi (id_sppd, id_tujuan, id_ekstend, termin).
-- Baris extend lama tidak berubah (butuh_proses = 0) sehingga tidak masuk proses baru.
-- Jalankan sebelum memasang versi aplikasi (backend dan frontend) yang memakai kolom ini.
-- Aman dijalankan berulang. Rollback: ekstend_proses_rollback.sql

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'tipe');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `tipe` VARCHAR(20) NOT NULL DEFAULT ''perpanjang'' COMMENT ''perpanjang = tambah hari tujuan yang ada; tujuan_baru = tujuan tambahan''', 'SELECT ''ekstends.tipe sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'id_kategori');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `id_kategori` INT NULL DEFAULT NULL COMMENT ''kategori SPPD (categori_sppds.id) untuk tujuan baru''', 'SELECT ''ekstends.id_kategori sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'detail_tujuan');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `detail_tujuan` VARCHAR(255) NULL DEFAULT NULL COMMENT ''nama tujuan baru''', 'SELECT ''ekstends.detail_tujuan sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'moda');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `moda` VARCHAR(50) NULL DEFAULT NULL COMMENT ''moda transportasi tujuan baru''', 'SELECT ''ekstends.moda sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'perlu_tiket');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `perlu_tiket` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = diproses divisi umum (tiket) sebelum divisi keuangan''', 'SELECT ''ekstends.perlu_tiket sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'butuh_proses');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `butuh_proses` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = pengajuan baru yang menghasilkan uang muka (baris lama bernilai 0)''', 'SELECT ''ekstends.butuh_proses sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'disetujui_at');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `disetujui_at` DATETIME NULL DEFAULT NULL COMMENT ''waktu seluruh persetujuan extend selesai''', 'SELECT ''ekstends.disetujui_at sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'sumber_tiket');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `sumber_tiket` VARCHAR(20) NULL DEFAULT NULL COMMENT ''umum | mandiri, diisi divisi umum''', 'SELECT ''ekstends.sumber_tiket sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'by_umum');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `by_umum` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = sudah diproses divisi umum''', 'SELECT ''ekstends.by_umum sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'ekstends' AND column_name = 'uangmuka');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`ekstends` ADD COLUMN `uangmuka` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = semua termin uang muka sudah dibuktikan divisi keuangan''', 'SELECT ''ekstends.uangmuka sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_tiket' AND column_name = 'id_ekstend');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`proses_tiket` ADD COLUMN `id_ekstend` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0 = tiket SPPD awal; selain 0 = tiket untuk extend (ekstends.id)''', 'SELECT ''proses_tiket.id_ekstend sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ada := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_termin' AND column_name = 'id_ekstend');
SET @sql := IF(@ada = 0, 'ALTER TABLE `ptpemaco_sppdnew`.`proses_termin` ADD COLUMN `id_ekstend` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''0 = termin SPPD awal; selain 0 = termin untuk extend (ekstends.id)''', 'SELECT ''proses_termin.id_ekstend sudah ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- kunci unik proses_termin: tambahkan id_ekstend (hanya bila tabelnya ada dan kunci masih versi lama)
SET @ada_tabel := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_termin');
SET @kunci_baru := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = 'ptpemaco_sppdnew' AND table_name = 'proses_termin' AND index_name = 'proses_termin_unik' AND column_name = 'id_ekstend');
SET @sql := IF(@ada_tabel = 1 AND @kunci_baru = 0,
  'ALTER TABLE `ptpemaco_sppdnew`.`proses_termin` DROP INDEX `proses_termin_unik`, ADD UNIQUE KEY `proses_termin_unik` (`id_sppd`, `id_tujuan`, `id_ekstend`, `termin`)',
  'SELECT ''Kunci proses_termin sudah versi baru atau tabel belum ada, dilewati'' AS info');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
