-- Jalankan di database koneksi "adm" (DB_DATABASE_6), sama dengan tabel list_surat

CREATE TABLE `docusign_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `sign_mode` ENUM('parallel','sequential') NOT NULL DEFAULT 'parallel',
  `original_path` VARCHAR(255) NOT NULL,
  `signed_path` VARCHAR(255) NULL,
  `original_hash` VARCHAR(64) NOT NULL,
  `status` ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_by` VARCHAR(255) NULL,
  `completed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `docusign_documents_uuid_unique` (`uuid`),
  KEY `docusign_documents_created_by_index` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `docusign_signers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `position` VARCHAR(255) NULL,
  `sign_order` INT UNSIGNED NOT NULL DEFAULT 1,
  `token` VARCHAR(64) NOT NULL,
  `status` ENUM('pending','signed') NOT NULL DEFAULT 'pending',
  `signed_at` TIMESTAMP NULL DEFAULT NULL,
  `signed_ip` VARCHAR(45) NULL,
  `notified_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `docusign_signers_token_unique` (`token`),
  KEY `docusign_signers_document_id_index` (`document_id`),
  CONSTRAINT `docusign_signers_document_id_fk` FOREIGN KEY (`document_id`)
    REFERENCES `docusign_documents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `docusign_fields` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `signer_id` BIGINT UNSIGNED NOT NULL,
  `page` INT UNSIGNED NOT NULL,
  `x` DECIMAL(8,6) NOT NULL,
  `y` DECIMAL(8,6) NOT NULL,
  `w` DECIMAL(8,6) NOT NULL,
  `h` DECIMAL(8,6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `docusign_fields_document_id_index` (`document_id`),
  CONSTRAINT `docusign_fields_document_id_fk` FOREIGN KEY (`document_id`)
    REFERENCES `docusign_documents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `docusign_fields_signer_id_fk` FOREIGN KEY (`signer_id`)
    REFERENCES `docusign_signers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
