-- ============================================================================
-- SUPABASE MIGRATION: Add Missing Columns & Unique Constraints
-- ============================================================================
-- 
-- This migration adds missing columns to synced tables and ensures
-- proper unique constraints on local_id for conflict resolution.
--
-- HOW TO RUN:
-- 1. Go to Supabase Dashboard > SQL Editor
-- 2. Create a new Query
-- 3. Paste the relevant sections below
-- 4. Execute
--
-- ============================================================================

-- ============================================================================
-- 1. ADD MISSING COLUMNS TO clinic_arrivals
-- ============================================================================
-- These columns exist in local MySQL but are missing from Supabase
-- Examine local table: SHOW COLUMNS FROM clinic_arrivals;

ALTER TABLE clinic_arrivals ADD COLUMN IF NOT EXISTS appointment_time TIMESTAMP;
ALTER TABLE clinic_arrivals ADD COLUMN IF NOT EXISTS arrived_at TIMESTAMP;
ALTER TABLE clinic_arrivals ADD COLUMN IF NOT EXISTS checked_in_by BIGINT;
ALTER TABLE clinic_arrivals ADD COLUMN IF NOT EXISTS notes TEXT;
ALTER TABLE clinic_arrivals ADD COLUMN IF NOT EXISTS status VARCHAR(50) DEFAULT 'pending';

-- ============================================================================
-- 2. ADD MISSING COLUMNS TO audit_log
-- ============================================================================
-- Key forensic fields that help track system changes

ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45);
ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS user_agent TEXT;
ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS request_id VARCHAR(255);
ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS affected_row_id BIGINT;

-- ============================================================================
-- 3. CREATE UNIQUE CONSTRAINTS ON local_id
-- ============================================================================
-- This prevents "duplicate key value violates unique constraint" errors
-- Run EACH constraint separately if one fails:

-- For audit_log
CREATE UNIQUE INDEX IF NOT EXISTS idx_audit_log_local_id_unique ON audit_log(local_id);

-- For clinic_arrivals
CREATE UNIQUE INDEX IF NOT EXISTS idx_clinic_arrivals_local_id_unique ON clinic_arrivals(local_id);

-- For appointments (if syncing)
CREATE UNIQUE INDEX IF NOT EXISTS idx_appointments_local_id_unique ON appointments(local_id);

-- For patients (if syncing)
CREATE UNIQUE INDEX IF NOT EXISTS idx_patients_local_id_unique ON patients(local_id);

-- For users (if syncing)
CREATE UNIQUE INDEX IF NOT EXISTS idx_users_local_id_unique ON users(local_id);

-- For other common tables - add as needed:
-- CREATE UNIQUE INDEX IF NOT EXISTS idx_treatments_local_id_unique ON treatments(local_id);
-- CREATE UNIQUE INDEX IF NOT EXISTS idx_invoices_local_id_unique ON invoices(local_id);

-- ============================================================================
-- 4. VERIFY CONSTRAINTS WERE CREATED
-- ============================================================================
-- Run this query to verify:
/*
SELECT 
    tablename,
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
AND indexname LIKE '%local_id%'
ORDER BY tablename;
*/

-- ============================================================================
-- 5. ADD MISSING SYNC COLUMNS TO Supabase TABLES (if not present)
-- ============================================================================
-- These are typically stored locally but good to have in cloud for reconciliation

ALTER TABLE audit_log ADD COLUMN IF NOT EXISTS sync_status VARCHAR(32) DEFAULT 'synced';
ALTER TABLE clinic_arrivals ADD COLUMN IF NOT EXISTS sync_status VARCHAR(32) DEFAULT 'synced';

-- ============================================================================
-- 6. OPTIONAL: SET DEFAULT VALUES FOR NEW COLUMNS
-- ============================================================================
-- Ensure existing records have valid defaults

UPDATE clinic_arrivals 
SET appointment_time = created_at 
WHERE appointment_time IS NULL AND created_at IS NOT NULL;

UPDATE clinic_arrivals 
SET status = 'checked_in' 
WHERE status IS NULL AND arrived_at IS NOT NULL;

UPDATE clinic_arrivals 
SET status = 'pending' 
WHERE status IS NULL;
