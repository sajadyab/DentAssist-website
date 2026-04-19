-- Add cloud_id columns to local tables for cloud-first architecture
-- This allows storing the Supabase primary key in local tables

ALTER TABLE invoices ADD COLUMN cloud_id BIGINT NULL AFTER id;
ALTER TABLE subscription_payments ADD COLUMN cloud_id BIGINT NULL AFTER id;
ALTER TABLE patients ADD COLUMN cloud_id BIGINT NULL AFTER id;

-- Add indexes for performance
ALTER TABLE invoices ADD INDEX idx_invoices_cloud_id (cloud_id);
ALTER TABLE subscription_payments ADD INDEX idx_subscription_payments_cloud_id (cloud_id);
ALTER TABLE patients ADD INDEX idx_patients_cloud_id (cloud_id);