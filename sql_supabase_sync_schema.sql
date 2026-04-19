-- Supabase schema (run in Supabase SQL Editor)
-- Requires extension for UUID generation
CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- NOTE: Adjust business columns to match your current local schema.
-- Each table includes cloud UUID primary key + local_id mapping + timestamps.

CREATE TABLE IF NOT EXISTS public.patients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    full_name TEXT,
    date_of_birth DATE,
    gender TEXT,
    phone TEXT,
    email TEXT,
    address TEXT,
    points INTEGER DEFAULT 0,
    subscription_type TEXT,
    subscription_status TEXT,
    referral_code TEXT,
    referred_by BIGINT,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    username TEXT,
    email TEXT,
    full_name TEXT,
    role TEXT,
    phone TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.appointments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    patient_id BIGINT,
    doctor_id BIGINT,
    appointment_date DATE,
    appointment_time TIME,
    duration INTEGER,
    treatment_type TEXT,
    description TEXT,
    chair_number INTEGER,
    status TEXT,
    notes TEXT,
    cancellation_reason TEXT,
    reminder_sent_24h BOOLEAN,
    reminder_sent_at TIMESTAMPTZ,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.medical_records (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    patient_id BIGINT,
    diagnosis TEXT,
    notes TEXT,
    record_date DATE,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.prescriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    patient_id BIGINT,
    appointment_id BIGINT,
    medication_name TEXT,
    dosage TEXT,
    instructions TEXT,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.treatments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    name TEXT,
    cost NUMERIC,
    description TEXT,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.invoices (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    patient_id BIGINT,
    appointment_id BIGINT,
    invoice_number TEXT,
    subtotal NUMERIC,
    total_amount NUMERIC,
    paid_amount NUMERIC,
    balance_due NUMERIC,
    payment_status TEXT,
    invoice_date DATE,
    due_date DATE,
    notes TEXT,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS public.payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    invoice_id BIGINT,
    amount NUMERIC,
    payment_method TEXT,
    reference_number TEXT,
    notes TEXT,
    payment_date TIMESTAMPTZ,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

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

CREATE TABLE IF NOT EXISTS public.tooth_chart (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    local_id BIGINT UNIQUE,
    patient_id BIGINT,
    tooth_number INTEGER,
    status TEXT,
    diagnosis TEXT,
    treatment TEXT,
    notes TEXT,
    updated_by BIGINT,
    deleted BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Helpful index examples
CREATE INDEX IF NOT EXISTS idx_patients_local_id ON public.patients(local_id);
CREATE INDEX IF NOT EXISTS idx_users_local_id ON public.users(local_id);
CREATE INDEX IF NOT EXISTS idx_appointments_local_id ON public.appointments(local_id);
CREATE INDEX IF NOT EXISTS idx_appointments_updated_at ON public.appointments(updated_at);
CREATE INDEX IF NOT EXISTS idx_treatment_plans_local_id ON public.treatment_plans(local_id);
CREATE INDEX IF NOT EXISTS idx_treatment_steps_local_id ON public.treatment_steps(local_id);
CREATE INDEX IF NOT EXISTS idx_tooth_chart_local_id ON public.tooth_chart(local_id);
