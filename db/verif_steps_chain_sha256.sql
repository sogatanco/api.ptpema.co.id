-- Jalankan di database ptpemaco_esign (alternatif dari php artisan migrate)
ALTER TABLE `verif_steps` ADD COLUMN `chain_sha256` CHAR(64) NULL DEFAULT NULL AFTER `signature`;
