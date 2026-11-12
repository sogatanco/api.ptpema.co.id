-- Draft surat. Draft disimpan terpisah dari tabel surat: belum mengambil nomor, belum masuk alur paraf dan belum ada notifikasi.
-- Nomor surat baru didapat ketika draft di-submit (lewat adm/insert); draft dihapus setelah itu.
-- Jalankan sekali:  mysql -u <user> -p < surat_draft.sql   (tabel baru, tidak mengubah tabel yang ada)

CREATE TABLE IF NOT EXISTS `ptpemaco_admnew`.`surat_draft` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_by` VARCHAR(255) NOT NULL,
  `payload` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
