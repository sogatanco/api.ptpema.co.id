-- Fitur layar (/abs dan /insert-image) dihapus. Database ptpemaco_layar tidak dipakai lagi dan dihapus seluruhnya.
-- Tidak bisa dibatalkan. Cadangan dulu, dari terminal server (bukan dari klien SQL):
--   mysqldump -u ptpemaco -p ptpemaco_layar > ptpemaco_layar_20261111.sql
-- Pulihkan bila perlu:
--   mysql -u ptpemaco -p -e "CREATE DATABASE ptpemaco_layar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--   mysql -u ptpemaco -p ptpemaco_layar < ptpemaco_layar_20261111.sql

DROP DATABASE IF EXISTS ptpemaco_layar;

-- File gambar di server: hapus folder public/layar bila ada (rm -r public/layar)
