-- Pengingat keamanan akun: mencatat kapan password terakhir diubah dan kapan pengingat terakhir ditampilkan per pengguna.
-- Aturan: pengingat tampil seminggu sekali; bila password tidak diubah dalam 30 hari terakhir (atau belum tercatat), tampil sehari sekali.
-- Jalankan sekali:  mysql -u <user> -p < keamanan_akun.sql   (tabel baru, tidak mengubah tabel yang ada)
-- Rollback: keamanan_akun_rollback.sql

CREATE TABLE IF NOT EXISTS `ptpemaco_apam`.`keamanan_akun` (
  `employe_id` VARCHAR(20) NOT NULL,
  `password_diubah_at` DATETIME NULL DEFAULT NULL,
  `pengingat_tampil_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`employe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
