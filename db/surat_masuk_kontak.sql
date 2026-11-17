-- Surat masuk: kontak pengirim menurut cara surat diterima (via).
--   via Whatapps    -> nomor WhatsApp pengirim (wajib)
--   via Email       -> alamat email pengirim (wajib)
--   via Surat Fisik -> nomor yang bisa dihubungi (opsional)
-- Kolom baru, boleh kosong; surat lama tidak berubah. Jalankan sebelum memasang versi aplikasi yang menulis kolom ini.
-- Rollback: ALTER TABLE ptpemaco_admnew.surat_masuks DROP COLUMN kontak;
ALTER TABLE `ptpemaco_admnew`.`surat_masuks` ADD COLUMN `kontak` VARCHAR(100) NULL DEFAULT NULL AFTER `via`;
