-- Tujuan : Hotel 30% bisa memilih beberapa rentang tanggal yang terpisah (mis. tanggal 1-2 dan 5-6) pada satu tujuan.
--          Satu baris tigapuluhs = satu klaim per tujuan; rentang tanggalnya disimpan di tigapuluh_rentang.
--          Kolom tigapuluhs.mulai / selesai tetap berisi tanggal paling awal dan paling akhir (kompatibel dengan data lama).
-- Database: ptpemaco_sppdnew.
-- Urutan : jalankan sekali. Aman diulang (tabel IF NOT EXISTS; isi awal hanya untuk klaim yang belum punya rentang).
-- Rollback: tigapuluh_rentang_rollback.sql

USE ptpemaco_sppdnew;

CREATE TABLE IF NOT EXISTS tigapuluh_rentang (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_tigapuluh BIGINT UNSIGNED NOT NULL COMMENT 'tigapuluhs.id',
    mulai DATE NOT NULL,
    selesai DATE NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tigapuluh (id_tigapuluh)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- klaim yang sudah ada menjadi satu rentang (mulai s.d. selesai); baris lama bertanggal '0000-00-00' dilewati
INSERT INTO tigapuluh_rentang (id_tigapuluh, mulai, selesai)
SELECT t.id, t.mulai, t.selesai
FROM tigapuluhs t
WHERE t.mulai >= '2000-01-01' AND t.selesai >= '2000-01-01'
  AND NOT EXISTS (SELECT 1 FROM tigapuluh_rentang r WHERE r.id_tigapuluh = t.id);
