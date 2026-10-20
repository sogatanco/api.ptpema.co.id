-- Jalankan di database koneksi "adm" (DB_DATABASE_6) HANYA jika tabel docusign sudah dibuat sebelumnya.
-- Jika tabel belum ada, cukup jalankan docusign.sql (sudah memuat kolom ini).

ALTER TABLE `docusign_documents`
  ADD COLUMN `sign_mode` ENUM('parallel','sequential') NOT NULL DEFAULT 'parallel' AFTER `title`;

ALTER TABLE `docusign_signers`
  ADD COLUMN `position` VARCHAR(255) NULL AFTER `email`,
  ADD COLUMN `sign_order` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `position`;
