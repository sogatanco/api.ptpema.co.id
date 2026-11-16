-- Pesan antar akun (widget pesan di pojok kanan bawah).
-- Jalankan sekali di phpMyAdmin (tabel baru, tidak mengubah tabel yang ada).
-- Rollback: chat_messages_rollback.sql

CREATE TABLE IF NOT EXISTS `ptpemaco_apam`.`chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_user_id` BIGINT UNSIGNED NOT NULL,
  `to_user_id` BIGINT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `read_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_inbox_idx` (`to_user_id`, `read_at`),
  KEY `chat_pair_idx` (`from_user_id`, `to_user_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
