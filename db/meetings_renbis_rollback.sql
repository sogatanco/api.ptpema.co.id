-- Kembalikan db/meetings_renbis.sql (menghapus kolom beserta isinya)

ALTER TABLE `meetings`
  DROP COLUMN `renbis_name`,
  DROP COLUMN `renbis_id`;
