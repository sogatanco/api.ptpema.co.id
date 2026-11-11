-- Halaman Setting Super Admin: daftar role tambahan (ptpemaco_apam) dan saklar WhatsApp (ptpemaco_notif).
-- Rollback: db/pengaturan_super_admin_rollback.sql

USE ptpemaco_apam;
CREATE TABLE IF NOT EXISTS master_role (
    nama VARCHAR(40) NOT NULL COMMENT 'nama role persis seperti dipakai di users.roles, mis. PICAdm',
    keterangan VARCHAR(255) NULL,
    dibuat_oleh VARCHAR(30) NULL COMMENT 'employe_id',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (nama)
) ENGINE=InnoDB;

USE ptpemaco_notif;
CREATE TABLE IF NOT EXISTS pengaturan (
    kunci VARCHAR(60) NOT NULL COMMENT 'mis. wa_aktif',
    nilai VARCHAR(255) NOT NULL,
    diubah_oleh VARCHAR(30) NULL COMMENT 'employe_id',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (kunci)
) ENGINE=InnoDB;
