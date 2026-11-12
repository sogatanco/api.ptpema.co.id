-- Nomor agenda surat masuk permanen (urut per tahun, reset tiap tahun). Jalankan di database ADM (koneksi 'adm').
-- BACKUP dulu: mysqldump <database_adm> surat_masuks > backup_surat_masuks.sql
-- Bisa dijalankan sebagai pengganti `php artisan migrate` untuk migrasi 2026_11_12_000001 (jangan jalankan keduanya).

-- 1. kolom baru
ALTER TABLE `surat_masuks`
  ADD COLUMN `agenda_tahun` SMALLINT UNSIGNED NULL,
  ADD COLUMN `agenda_urut` INT UNSIGNED NULL;

-- 2. surat lama: urut per tahun berdasarkan id (sama persis dengan nomor yang tampil sebelumnya, jadi nomor tercetak tidak berubah)
UPDATE `surat_masuks` s
JOIN (
  SELECT a.id,
         YEAR(COALESCE(a.created_at, NOW())) AS tahun,
         (SELECT COUNT(*) FROM `surat_masuks` b
           WHERE YEAR(COALESCE(b.created_at, NOW())) = YEAR(COALESCE(a.created_at, NOW()))
             AND b.id <= a.id) AS urut
  FROM `surat_masuks` a
) h ON h.id = s.id
SET s.agenda_tahun = h.tahun, s.agenda_urut = h.urut;

-- 3. kunci agar tidak ada nomor kembar
ALTER TABLE `surat_masuks` ADD UNIQUE KEY `surat_masuks_agenda_unik` (`agenda_tahun`, `agenda_urut`);

-- cek hasil: seharusnya tidak ada baris
-- SELECT id FROM surat_masuks WHERE agenda_urut IS NULL;
-- SELECT agenda_tahun, COUNT(*), MAX(agenda_urut) FROM surat_masuks GROUP BY agenda_tahun;

-- ROLLBACK:
-- ALTER TABLE `surat_masuks` DROP INDEX `surat_masuks_agenda_unik`, DROP COLUMN `agenda_tahun`, DROP COLUMN `agenda_urut`;
