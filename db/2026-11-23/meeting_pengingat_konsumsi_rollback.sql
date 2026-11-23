-- Rollback meeting_pengingat_konsumsi.sql: menghapus kolom penanda pengingat konsumsi beserta isinya.
USE ptpemaco_apam;

ALTER TABLE meetings
    DROP COLUMN pengingat_konsumsi_at;
