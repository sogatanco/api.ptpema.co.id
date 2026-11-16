-- Grup chat per divisi (terenkripsi end-to-end). Anggota grup = pegawai aktif di divisi itu (organization_id), dihitung dari struktur;
-- golongan yang dikecualikan per divisi diatur di config/chat.php (Divisi Umum: Tenaga Pendukung tidak masuk grup).
-- Server hanya menyimpan pesan terenkripsi dan kunci grup yang sudah dibungkus untuk tiap anggota.
-- Jalankan sekali di phpMyAdmin (tabel baru, tidak mengubah tabel yang ada). Rollback: chat_grup_rollback.sql

CREATE TABLE IF NOT EXISTS `ptpemaco_apam`.`chat_group_keys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `org_id` INT UNSIGNED NOT NULL,
  `versi` INT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'penerima kunci',
  `pembungkus_user_id` BIGINT UNSIGNED NOT NULL COMMENT 'anggota yang membungkus kunci ini',
  `wrapped_key` VARCHAR(255) NOT NULL,
  `iv` VARCHAR(32) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_group_keys_unik` (`org_id`, `versi`, `user_id`),
  KEY `chat_group_keys_user_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ptpemaco_apam`.`chat_group_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `org_id` INT UNSIGNED NOT NULL,
  `from_user_id` BIGINT UNSIGNED NOT NULL,
  `versi` INT UNSIGNED NOT NULL,
  `ciphertext` TEXT NOT NULL,
  `iv` VARCHAR(32) NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_group_messages_org_idx` (`org_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ptpemaco_apam`.`chat_group_reads` (
  `org_id` INT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `last_read_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`org_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
