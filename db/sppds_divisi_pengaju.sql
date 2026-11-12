-- Divisi pengaju SPPD saat SPPD dibuat (tab "Pengajuan Divisi" untuk AdminSPPD pengganti).
-- Jalankan pada database SPPD (mysql4). SPPD lama (NULL) memakai divisi pengaju saat ini.
ALTER TABLE `sppds` ADD COLUMN `divisi_pengaju` INT NULL AFTER `submitted_by`;
-- Rollback: ALTER TABLE `sppds` DROP COLUMN `divisi_pengaju`;
