-- Pengaturan tampilan Dashboard TV: urutan dan status tampil/sembunyi tiap bagian.
-- Diubah oleh karyawan dengan role PICDashboard lewat menu "Atur Dashboard TV".
-- Jalankan sekali pada database utama aplikasi. Bila tabel kosong, Dashboard TV memakai urutan bawaan.

CREATE TABLE IF NOT EXISTS `dashboard_tv_slides` (
  `slide_key` VARCHAR(32) NOT NULL,
  `urutan` INT NOT NULL DEFAULT 0,
  `tampil` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_by` VARCHAR(64) NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`slide_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `dashboard_tv_slides` (`slide_key`, `urutan`, `tampil`) VALUES
  ('hadir', 1, 1),
  ('tidak', 2, 1),
  ('kemarin', 3, 1),
  ('grafikHari', 4, 1),
  ('grafikBulan', 5, 1),
  ('rapat', 6, 1),
  ('struktur', 7, 1);
