-- Booking rapat: pilihan project / renbis untuk permintaan konsumsi
-- Jalankan sekali pada database utama aplikasi (yang berisi tabel `meetings`).
-- Bila kolom sudah ada, MySQL menolak dengan error "Duplicate column name"; itu aman, tidak ada yang berubah.

ALTER TABLE `meetings`
  ADD COLUMN `renbis_id` BIGINT UNSIGNED NULL AFTER `consumption_detail`,
  ADD COLUMN `renbis_name` VARCHAR(255) NULL AFTER `renbis_id`;
