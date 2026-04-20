-- Supabase realtime sync upgrade (safe to rerun)
-- Creates treatment plan/step cloud tables and local_id uniqueness.

CREATE TABLE IF NOT EXISTS public.treatment_plans (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    patient_id BIGINT,
    plan_name TEXT,
    description TEXT,
    teeth_affected TEXT,
    estimated_cost NUMERIC,
    discount NUMERIC,
    status TEXT,
    priority TEXT,
    start_date DATE,
    estimated_end_date DATE,
    notes TEXT,
    created_by BIGINT,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.treatment_steps (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    plan_id BIGINT,
    step_number INTEGER,
    procedure_name TEXT,
    description TEXT,
    tooth_numbers TEXT,
    duration_minutes INTEGER,
    cost NUMERIC,
    status TEXT,
    notes TEXT,
    completed_date DATE,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

ALTER TABLE IF EXISTS public.treatment_plans ADD COLUMN IF NOT EXISTS local_id BIGINT;
ALTER TABLE IF EXISTS public.treatment_steps ADD COLUMN IF NOT EXISTS local_id BIGINT;
ALTER TABLE IF EXISTS public.treatment_plans ADD COLUMN IF NOT EXISTS deleted BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE IF EXISTS public.treatment_steps ADD COLUMN IF NOT EXISTS deleted BOOLEAN NOT NULL DEFAULT FALSE;

CREATE UNIQUE INDEX IF NOT EXISTS uq_treatment_plans_local_id ON public.treatment_plans(local_id) WHERE local_id IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS uq_treatment_steps_local_id ON public.treatment_steps(local_id) WHERE local_id IS NOT NULL;

ALTER TABLE IF EXISTS public.users ADD COLUMN IF NOT EXISTS local_id BIGINT;
CREATE UNIQUE INDEX IF NOT EXISTS uq_users_local_id ON public.users(local_id) WHERE local_id IS NOT NULL;

ALTER TABLE IF EXISTS public.subscription_plans ADD COLUMN IF NOT EXISTS local_id BIGINT;
CREATE UNIQUE INDEX IF NOT EXISTS uq_subscription_plans_local_id ON public.subscription_plans(local_id) WHERE local_id IS NOT NULL;
