-- Dashboard TV: lama tayang (detik) per bagian dan bagian baru "Lalu Lintas Banda Aceh dan Sekitarnya".
-- Jalankan sesudah dashboard_tv_slides.sql. Kolom durasi kosong (NULL) = memakai lama tayang bawaan.
-- Bila kolom sudah ada, MySQL menolak dengan "Duplicate column name"; itu aman.

ALTER TABLE `dashboard_tv_slides`
  ADD COLUMN `durasi` INT UNSIGNED NULL AFTER `tampil`;

INSERT IGNORE INTO `dashboard_tv_slides` (`slide_key`, `urutan`, `tampil`) VALUES
  ('lalulintas', 8, 1);
