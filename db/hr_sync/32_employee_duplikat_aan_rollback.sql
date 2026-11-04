-- Membatalkan 31: aktifkan kembali 202605052K di apam.
UPDATE ptpemaco_apam.employees SET employe_active = 1 WHERE employe_id = '202605052K';
SELECT employe_id, first_name, employe_active FROM ptpemaco_apam.employees WHERE employe_id = '202605052K';
