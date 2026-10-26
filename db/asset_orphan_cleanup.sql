-- Opsional. Jalankan di database ptpemaco_apam (koneksi default / DB_DATABASE).
-- Menghapus nomor turunan asset (asset_children) yang induknya sudah dihapus.
-- Per 2026-10-26: 15 baris, dari induk id 397, 400, 401, 402.
-- 8 riwayat servis (asset_servis) merujuk ke baris ini; riwayatnya tetap disimpan
-- dan akan tampil sebagai "(asset sudah dihapus)" di menu Service Application.

-- Cek dulu apa yang akan dihapus:
SELECT c.id, c.asset_number, c.id_parent
FROM asset_children c
LEFT JOIN assets a ON a.id = c.id_parent
WHERE a.id IS NULL;

-- Hapus:
DELETE c FROM asset_children c
LEFT JOIN assets a ON a.id = c.id_parent
WHERE a.id IS NULL;
