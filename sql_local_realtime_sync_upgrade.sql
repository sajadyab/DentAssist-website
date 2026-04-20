-- Local realtime sync upgrade (safe to rerun)
-- Adds tracking for treatment plans/steps plus richer sync diagnostics.

ALTER TABLE treatment_plans
    ADD COLUMN IF NOT EXISTS sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS last_sync_attempt DATETIME NULL,
    ADD COLUMN IF NOT EXISTS sync_error TEXT NULL,
    ADD COLUMN IF NOT EXISTS deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE treatment_steps
    ADD COLUMN IF NOT EXISTS sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS last_sync_attempt DATETIME NULL,
    ADD COLUMN IF NOT EXISTS sync_error TEXT NULL,
    ADD COLUMN IF NOT EXISTS deleted TINYINT(1) NOT NULL DEFAULT 0;

ALTER TABLE appointments
    ADD COLUMN IF NOT EXISTS last_sync_attempt DATETIME NULL,
    ADD COLUMN IF NOT EXISTS sync_error TEXT NULL;

ALTER TABLE patients
    ADD COLUMN IF NOT EXISTS last_sync_attempt DATETIME NULL,
    ADD COLUMN IF NOT EXISTS sync_error TEXT NULL;

ALTER TABLE audit_log
    ADD COLUMN IF NOT EXISTS last_sync_attempt DATETIME NULL,
    ADD COLUMN IF NOT EXISTS sync_error TEXT NULL;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS last_sync_attempt DATETIME NULL,
    ADD COLUMN IF NOT EXISTS sync_error TEXT NULL;

ALTER TABLE subscription_plans
    ADD COLUMN IF NOT EXISTS sync_status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS last_sync_attempt DATETIME NULL,
    ADD COLUMN IF NOT EXISTS sync_error TEXT NULL;

UPDATE treatment_plans SET sync_status = 'pending' WHERE sync_status IS NULL OR sync_status = '';
UPDATE treatment_steps SET sync_status = 'pending' WHERE sync_status IS NULL OR sync_status = '';
UPDATE users SET sync_status = 'pending' WHERE sync_status IS NULL OR sync_status = '';
UPDATE subscription_plans SET sync_status = 'pending' WHERE sync_status IS NULL OR sync_status = '';

CREATE TABLE IF NOT EXISTS sync_runtime_status (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    table_name VARCHAR(64) NOT NULL,
    local_id BIGINT NOT NULL,
    direction ENUM('local_to_cloud','local_to_cloud_delete','cloud_to_local') NOT NULL DEFAULT 'local_to_cloud',
    action VARCHAR(32) NOT NULL DEFAULT 'upsert',
    status ENUM('pending','in_progress','synced','failed') NOT NULL DEFAULT 'pending',
    message TEXT NULL,
    payload_json LONGTEXT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_started DATETIME NULL,
    last_finished DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sync_runtime_status (table_name, local_id, direction, action),
    KEY idx_sync_runtime_status_status (status),
    KEY idx_sync_runtime_status_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sync_operation_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    table_name VARCHAR(64) NOT NULL,
    local_id BIGINT NOT NULL,
    direction ENUM('local_to_cloud','local_to_cloud_delete','cloud_to_local') NOT NULL DEFAULT 'local_to_cloud',
    action VARCHAR(32) NOT NULL DEFAULT 'upsert',
    status ENUM('pending','in_progress','synced','failed') NOT NULL,
    message TEXT NULL,
    payload_json LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sync_operation_log_table (table_name, local_id),
    KEY idx_sync_operation_log_status (status),
    KEY idx_sync_operation_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
