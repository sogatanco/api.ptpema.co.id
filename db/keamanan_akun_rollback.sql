-- Rollback keamanan_akun.sql: menghapus catatan pengingat keamanan (pengingat berhenti tampil; tidak mengubah password siapa pun).
DROP TABLE IF EXISTS `ptpemaco_apam`.`keamanan_akun`;
