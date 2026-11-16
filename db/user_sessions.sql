-- Kelola akses akun: mencatat setiap login (IP, browser, aplikasi) agar pengguna bisa melihat dan mengeluarkan perangkat lain.
-- Jalankan sekali di phpMyAdmin (tabel baru, tidak mengubah tabel yang ada).
-- Rollback: user_sessions_rollback.sql

CREATE TABLE IF NOT EXISTS `ptpemaco_apam`.`user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `ip` VARCHAR(45) NULL DEFAULT NULL,
  `user_agent` VARCHAR(512) NULL DEFAULT NULL,
  `perangkat` VARCHAR(120) NULL DEFAULT NULL,
  `aplikasi` VARCHAR(60) NULL DEFAULT NULL,
  `last_active_at` DATETIME NULL DEFAULT NULL,
  `revoked_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_sessions_user_idx` (`user_id`, `revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
