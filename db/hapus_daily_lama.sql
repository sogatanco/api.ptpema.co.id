-- Menghapus modul Daily lama (aktivitas harian versi lama). BACKUP DULU: tabel dihapus permanen.
-- Jalankan di database utama (koneksi 'mysql').
-- mysqldump <database> da_activits da_cat_acts > backup_daily_lama.sql
DROP TABLE IF EXISTS `da_activits`;
DROP TABLE IF EXISTS `da_cat_acts`;
DROP TABLE IF EXISTS `da_act_cats`;
DROP TABLE IF EXISTS `da_acts`;
