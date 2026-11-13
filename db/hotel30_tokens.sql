-- Tabel tautan persetujuan klaim hotel 30% untuk pelaksana SPPD yang bukan karyawan.
-- Padanan migrasi 2026_11_13_000001_create_hotel30_tokens_table.php (tidak perlu menjalankan artisan migrate).
-- Aman dijalankan ulang (IF NOT EXISTS). Jalankan di phpMyAdmin pada database ptpemaco_esign.
-- Rollback: DROP TABLE ptpemaco_esign.hotel30_tokens;

CREATE TABLE IF NOT EXISTS ptpemaco_esign.hotel30_tokens (
  id bigint unsigned NOT NULL AUTO_INCREMENT,
  id_doc varchar(255) NOT NULL,
  id_step bigint unsigned NOT NULL,
  id_tujuan bigint unsigned NOT NULL,
  nama varchar(255) NOT NULL,
  jabatan varchar(255) DEFAULT NULL,
  email varchar(255) NOT NULL,
  token_hash char(64) NOT NULL,
  expires_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  dikirim_at timestamp NULL DEFAULT NULL,
  dipakai_at timestamp NULL DEFAULT NULL,
  hasil varchar(20) DEFAULT NULL,
  ip varchar(45) DEFAULT NULL,
  dibuat_oleh varchar(50) DEFAULT NULL,
  created_at timestamp NULL DEFAULT NULL,
  updated_at timestamp NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY hotel30_tokens_token_hash_unique (token_hash),
  KEY hotel30_tokens_id_doc_id_tujuan_index (id_doc, id_tujuan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hak akses: aplikasi memakai koneksi esign yang sama dengan tabel verif_steps, jadi biasanya tidak perlu GRANT tambahan.
SELECT COUNT(*) AS tabel_hotel30_tokens_ada FROM information_schema.tables
WHERE table_schema = 'ptpemaco_esign' AND table_name = 'hotel30_tokens';
