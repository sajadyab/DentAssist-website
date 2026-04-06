<?php
declare(strict_types=1);

class AppointmentRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listForPatient(int $patientId): array
    {
        $db = Database::getInstance();

        return $db->fetchAll(
            "SELECT a.*, u.full_name as doctor_name
             FROM appointments a
             JOIN users u ON a.doctor_id = u.id
             WHERE a.patient_id = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            [$patientId],
            'i'
        );
    }

    public static function deleteForPatient(int $patientId): bool
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM appointments WHERE patient_id = ?', [$patientId], 'i') !== false;
    }
}
