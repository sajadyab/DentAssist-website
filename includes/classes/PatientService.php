<?php
declare(strict_types=1);

class PatientService
{
    /**
     * Deletes a patient and common related records (appointments, plans, xrays, invoices).
     * Keeps the same order/behavior as the previous inline implementation.
     */
    public static function deletePatientCascade(int $patientId): bool
    {
        AppointmentRepository::deleteForPatient($patientId);
        TreatmentPlanRepository::deleteForPatient($patientId);
        XrayRepository::deleteForPatient($patientId);
        InvoiceRepository::deleteForPatient($patientId);

        return PatientRepository::deleteById($patientId);
    }
}

