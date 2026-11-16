-- Untuk yang sudah menjalankan chat_messages.sql versi teks biasa: ubah ke terenkripsi.
-- PERHATIAN: pesan lama berupa teks biasa dan tidak bisa dienkripsi dari server, jadi dihapus.
-- Jalankan sekali di phpMyAdmin.

DELETE FROM `ptpemaco_apam`.`chat_messages`;

ALTER TABLE `ptpemaco_apam`.`chat_messages`
  DROP COLUMN `body`,
  ADD COLUMN `ciphertext` TEXT NOT NULL AFTER `to_user_id`,
  ADD COLUMN `iv` VARCHAR(32) NOT NULL AFTER `ciphertext`;

CREATE TABLE IF NOT EXISTS `ptpemaco_apam`.`chat_keys` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `public_key` VARCHAR(255) NOT NULL,
  `wrapped_private` TEXT NOT NULL,
  `salt` VARCHAR(64) NOT NULL,
  `iv` VARCHAR(32) NOT NULL,
  `iterasi` INT UNSIGNED NOT NULL DEFAULT 310000,
  `bawaan` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
