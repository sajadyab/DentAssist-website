<?php
declare(strict_types=1);

class XrayRepository
{
    /**
     * @return array<string,mixed>|null
     */
    public static function findDentalHistoryHandwrittenById(int $xrayId, int $patientId): ?array
    {
        $db = Database::getInstance();

        return $db->fetchOne(
            "SELECT id, file_path FROM xrays
             WHERE id = ?
               AND patient_id = ?
               AND xray_type = 'Other'
               AND notes LIKE 'Dental history (handwritten)%'
             LIMIT 1",
            [$xrayId, $patientId],
            'ii'
        );
    }

    public static function deleteByIdForPatient(int $xrayId, int $patientId): bool
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM xrays WHERE id = ? AND patient_id = ? LIMIT 1', [$xrayId, $patientId], 'ii') !== false;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findLatestDentalHistoryHandwritten(int $patientId): ?array
    {
        $db = Database::getInstance();

        return $db->fetchOne(
            "SELECT id, file_path FROM xrays
             WHERE patient_id = ? AND xray_type = 'Other' AND notes LIKE 'Dental history (handwritten)%'
             ORDER BY uploaded_at DESC, id DESC LIMIT 1",
            [$patientId],
            'i'
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listDentalHistoryHandwrittenImages(int $patientId): array
    {
        $db = Database::getInstance();

        return $db->fetchAll(
            "SELECT id, file_name, file_path, uploaded_at FROM xrays
             WHERE patient_id = ?
               AND xray_type = 'Other'
               AND notes LIKE 'Dental history (handwritten)%'
             ORDER BY uploaded_at DESC, id DESC",
            [$patientId],
            'i'
        );
    }

    public static function insertDentalHistoryHandwrittenFromEditForm(
        int $patientId,
        string $fileName,
        string $filePath,
        int $fileSize,
        string $mimeType,
        int $uploadedBy
    ): int {
        $db = Database::getInstance();

        return (int) $db->insert(
            "INSERT INTO xrays (patient_id, file_name, file_path, file_size, mime_type, xray_type, tooth_numbers, findings, notes, uploaded_by)
             VALUES (?, ?, ?, ?, ?, 'Other', ?, ?, ?, ?)",
            [
                $patientId,
                $fileName,
                $filePath,
                $fileSize,
                $mimeType,
                null,
                null,
                'Dental history (handwritten) uploaded from patient edit form.',
                $uploadedBy,
            ],
            'ississssi'
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listForPatientExcludingDentalHistory(int $patientId): array
    {
        $db = Database::getInstance();

        return $db->fetchAll(
            "SELECT * FROM xrays
             WHERE patient_id = ?
               AND NOT (xray_type = 'Other' AND COALESCE(notes, '') LIKE 'Dental history (handwritten)%')
             ORDER BY uploaded_at DESC",
            [$patientId],
            'i'
        );
    }

    public static function deleteForPatient(int $patientId): bool
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM xrays WHERE patient_id = ?', [$patientId], 'i') !== false;
    }
}
