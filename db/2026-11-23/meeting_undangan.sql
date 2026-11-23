-- Tujuan : menyimpan daftar peserta yang diundang ke rapat (booking ruang rapat maupun Zoom tanpa ruangan),
--          supaya detail rapat menampilkan peserta dan pembuat rapat bisa menambah undangan kemudian.
-- Database: ptpemaco_apam (database utama, sama dengan tabel meetings dan zooms).
-- Urutan : jalankan sekali. Aman diulang (CREATE TABLE IF NOT EXISTS).
-- Rollback: meeting_undangan_rollback.sql
--
-- tipe 'ruang' : rapat_id = meetings.id  (undangan rapat di ruangan, bisa sekaligus memakai Zoom)
-- tipe 'zoom'  : rapat_id = zooms.id     (Zoom tanpa booking ruangan)

USE ptpemaco_apam;

CREATE TABLE IF NOT EXISTS meeting_undangan (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipe ENUM('ruang', 'zoom') NOT NULL,
    rapat_id BIGINT UNSIGNED NOT NULL,
    employe_id VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'employe_id peserta yang diundang',
    diundang_oleh VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'employe_id yang mengundang',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_undangan (tipe, rapat_id, employe_id),
    KEY idx_peserta (employe_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
