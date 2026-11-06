-- Kembalikan db/dashboard_tv_slides_durasi.sql

DELETE FROM `dashboard_tv_slides` WHERE `slide_key` = 'lalulintas';
ALTER TABLE `dashboard_tv_slides` DROP COLUMN `durasi`;
