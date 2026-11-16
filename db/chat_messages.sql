-- Pesan antar akun terenkripsi end-to-end (widget pesan di pojok kanan bawah).
-- Server hanya menyimpan pesan terenkripsi (ciphertext + iv); kunci privat tidak pernah meninggalkan browser pengguna.
-- Jalankan sekali di phpMyAdmin (tabel baru, tidak mengubah tabel yang ada).
-- Bila tabel chat_messages sudah terlanjur dibuat dari versi sebelumnya (teks biasa), jalankan chat_messages_e2e.sql, bukan file ini.
-- Rollback: chat_messages_rollback.sql

CREATE TABLE IF NOT EXISTS `ptpemaco_apam`.`chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_user_id` BIGINT UNSIGNED NOT NULL,
  `to_user_id` BIGINT UNSIGNED NOT NULL,
  `ciphertext` TEXT NOT NULL,
  `iv` VARCHAR(32) NOT NULL,
  `read_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_inbox_idx` (`to_user_id`, `read_at`),
  KEY `chat_pair_idx` (`from_user_id`, `to_user_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kunci pesan per pengguna: kunci publik (dibaca lawan bicara) dan kunci privat yang sudah dibungkus
-- dengan passphrase pesan milik pengguna (server tidak bisa membukanya).
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
