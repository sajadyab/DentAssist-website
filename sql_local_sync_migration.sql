-- Local MySQL sync migration (XAMPP)
-- Run against dental_clinic_local / your active DB

-- 1) Add sync tracking columns + soft delete flag to syncable tables
ALTER TABLE patients
    ADD COLUMN sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN last_modified DATETIME NULL,
    ADD COLUMN last_sync_attempt DATETIME NULL,
    ADD COLUMN sync_error TEXT NULL,
    ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE appointments
    ADD COLUMN sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN last_modified DATETIME NULL,
    ADD COLUMN last_sync_attempt DATETIME NULL,
    ADD COLUMN sync_error TEXT NULL,
    ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE medical_records
    ADD COLUMN sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN last_modified DATETIME NULL,
    ADD COLUMN last_sync_attempt DATETIME NULL,
    ADD COLUMN sync_error TEXT NULL,
    ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE prescriptions
    ADD COLUMN sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN last_modified DATETIME NULL,
    ADD COLUMN last_sync_attempt DATETIME NULL,
    ADD COLUMN sync_error TEXT NULL,
    ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE treatments
    ADD COLUMN sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN last_modified DATETIME NULL,
    ADD COLUMN last_sync_attempt DATETIME NULL,
    ADD COLUMN sync_error TEXT NULL,
    ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE tooth_chart
    ADD COLUMN sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN last_modified DATETIME NULL,
    ADD COLUMN last_sync_attempt DATETIME NULL,
    ADD COLUMN sync_error TEXT NULL,
    ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE invoices
    ADD COLUMN sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN last_modified DATETIME NULL,
    ADD COLUMN last_sync_attempt DATETIME NULL,
    ADD COLUMN sync_error TEXT NULL,
    ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE payments
    ADD COLUMN sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN last_modified DATETIME NULL,
    ADD COLUMN last_sync_attempt DATETIME NULL,
    ADD COLUMN sync_error TEXT NULL,
    ADD COLUMN deleted TINYINT(1) NOT NULL DEFAULT 0;

-- 2) Metadata and delete queue
CREATE TABLE IF NOT EXISTS sync_metadata (
    table_name VARCHAR(64) NOT NULL,
    direction VARCHAR(32) NOT NULL,
    last_sync_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (table_name, direction)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sync_delete_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    table_name VARCHAR(64) NOT NULL,
    local_id BIGINT NOT NULL,
    match_column VARCHAR(64) NOT NULL DEFAULT 'local_id',
    status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    last_attempt DATETIME NULL,
    error_text TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sync_delete_queue_status (status),
    KEY idx_sync_delete_queue_table (table_name, local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
