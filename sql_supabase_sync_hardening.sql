-- Supabase hardening for sync (idempotent)
-- Run in Supabase SQL Editor

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- 1) Ensure sync metadata tables exist locally-only concept (cloud optional) skipped.

-- 2) Ensure local_id exists and is unique for key synced tables
ALTER TABLE IF EXISTS public.audit_log
  ADD COLUMN IF NOT EXISTS local_id BIGINT;

ALTER TABLE IF EXISTS public.clinic_arrivals
  ADD COLUMN IF NOT EXISTS local_id BIGINT;

-- Add local_id on other synced tables as needed
ALTER TABLE IF EXISTS public.patients ADD COLUMN IF NOT EXISTS local_id BIGINT;
ALTER TABLE IF EXISTS public.appointments ADD COLUMN IF NOT EXISTS local_id BIGINT;
ALTER TABLE IF EXISTS public.invoices ADD COLUMN IF NOT EXISTS local_id BIGINT;
ALTER TABLE IF EXISTS public.payments ADD COLUMN IF NOT EXISTS local_id BIGINT;

-- Unique constraints/indexes (safe pattern with IF NOT EXISTS index)
CREATE UNIQUE INDEX IF NOT EXISTS uq_audit_log_local_id ON public.audit_log(local_id) WHERE local_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_clinic_arrivals_local_id ON public.clinic_arrivals(local_id) WHERE local_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_patients_local_id ON public.patients(local_id) WHERE local_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_appointments_local_id ON public.appointments(local_id) WHERE local_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_invoices_local_id ON public.invoices(local_id) WHERE local_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_payments_local_id ON public.payments(local_id) WHERE local_id IS NOT NULL;

-- 3) clinic_arrivals: add likely-missing columns noted from local app usage
ALTER TABLE IF EXISTS public.clinic_arrivals
  ADD COLUMN IF NOT EXISTS appointment_time TIME,
  ADD COLUMN IF NOT EXISTS arrived_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS appointment_date DATE,
  ADD COLUMN IF NOT EXISTS appointment_id BIGINT,
  ADD COLUMN IF NOT EXISTS doctor_id BIGINT,
  ADD COLUMN IF NOT EXISTS patient_id BIGINT,
  ADD COLUMN IF NOT EXISTS patient_display_name TEXT,
  ADD COLUMN IF NOT EXISTS treatment_type TEXT,
  ADD COLUMN IF NOT EXISTS kind TEXT,
  ADD COLUMN IF NOT EXISTS priority TEXT,
  ADD COLUMN IF NOT EXISTS reason TEXT,
  ADD COLUMN IF NOT EXISTS created_by BIGINT,
  ADD COLUMN IF NOT EXISTS joined_at TIMESTAMPTZ,
  ADD COLUMN IF NOT EXISTS status TEXT,
  ADD COLUMN IF NOT EXISTS deleted BOOLEAN NOT NULL DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMPTZ NOT NULL DEFAULT now();

-- 4) Optional generic trigger to keep updated_at fresh
CREATE OR REPLACE FUNCTION public.set_updated_at()
RETURNS trigger AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DO $$
BEGIN
  IF to_regclass('public.clinic_arrivals') IS NOT NULL THEN
    IF NOT EXISTS (
      SELECT 1 FROM pg_trigger WHERE tgname = 'trg_clinic_arrivals_updated_at'
    ) THEN
      CREATE TRIGGER trg_clinic_arrivals_updated_at
      BEFORE UPDATE ON public.clinic_arrivals
      FOR EACH ROW EXECUTE FUNCTION public.set_updated_at();
    END IF;
  END IF;
END $$;
