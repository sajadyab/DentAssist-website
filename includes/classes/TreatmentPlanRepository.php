<?php
declare(strict_types=1);

class TreatmentPlanRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listForPatient(int $patientId): array
    {
        $db = Database::getInstance();

        return $db->fetchAll(
            "SELECT * FROM treatment_plans
             WHERE patient_id = ?
             ORDER BY created_at DESC",
            [$patientId],
            'i'
        );
    }

    public static function deleteForPatient(int $patientId): bool
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM treatment_plans WHERE patient_id = ?', [$patientId], 'i') !== false;
    }
}
