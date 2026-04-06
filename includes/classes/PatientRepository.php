<?php
declare(strict_types=1);

class PatientRepository
{
    /**
     * @return array<int,array{id:mixed,full_name:mixed}>
     */
    public static function listForSelect(int $limit = 0): array
    {
        $db = Database::getInstance();

        if ($limit > 0) {
            return $db->fetchAll(
                'SELECT id, full_name FROM patients ORDER BY full_name LIMIT ?',
                [$limit],
                'i'
            );
        }

        return $db->fetchAll('SELECT id, full_name FROM patients ORDER BY full_name');
    }

    public static function countBySearch(string $search = ''): int
    {
        $db = Database::getInstance();

        $search = trim($search);
        if ($search === '') {
            $row = $db->fetchOne('SELECT COUNT(*) AS count FROM patients');
            return (int) ($row['count'] ?? 0);
        }

        $like = '%' . $search . '%';
        $row = $db->fetchOne(
            'SELECT COUNT(*) AS count FROM patients WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ?',
            [$like, $like, $like],
            'sss'
        );

        return (int) ($row['count'] ?? 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function search(string $search, int $limit, int $offset): array
    {
        $db = Database::getInstance();

        $search = trim($search);
        if ($search === '') {
            return $db->fetchAll(
                'SELECT * FROM patients ORDER BY created_at DESC LIMIT ? OFFSET ?',
                [$limit, $offset],
                'ii'
            );
        }

        $like = '%' . $search . '%';
        return $db->fetchAll(
            'SELECT * FROM patients WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$like, $like, $like, $limit, $offset],
            'sssii'
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findById(int $patientId): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne('SELECT * FROM patients WHERE id = ?', [$patientId], 'i');
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findWithAccountUsername(int $patientId): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT p.*, u.username AS account_username
             FROM patients p
             LEFT JOIN users u ON p.user_id = u.id
             WHERE p.id = ?",
            [$patientId],
            'i'
        );
    }

    public static function updateFromEditPayload(int $patientId, array $payload): bool
    {
        $db = Database::getInstance();

        $sql = "UPDATE patients SET
                    full_name = ?, date_of_birth = ?, gender = ?, phone = ?, email = ?,
                    emergency_contact_name = ?, emergency_contact_phone = ?, emergency_contact_relation = ?,
                    insurance_provider = ?, insurance_id = ?, insurance_type = ?, insurance_coverage = ?,
                    medical_history = ?, allergies = ?, current_medications = ?,
                    dental_history = ?, last_visit_date = ?,
                    address = ?, country = ?
                WHERE id = ?";

        $values = [
            $payload['full_name'] ?? null,
            $payload['date_of_birth'] ?? null,
            $payload['gender'] ?? null,
            $payload['phone'] ?? null,
            $payload['email'] ?? null,
            $payload['emergency_contact_name'] ?? null,
            $payload['emergency_contact_phone'] ?? null,
            $payload['emergency_contact_relation'] ?? null,
            $payload['insurance_provider'] ?? null,
            $payload['insurance_id'] ?? null,
            $payload['insurance_type'] ?? null,
            $payload['insurance_coverage'] ?? null,
            $payload['medical_history'] ?? null,
            $payload['allergies'] ?? null,
            $payload['current_medications'] ?? null,
            $payload['dental_history'] ?? null,
            $payload['last_visit_date'] ?? null,
            $payload['address'] ?? null,
            $payload['country'] ?? null,
            $patientId,
        ];

        // Keep the existing "all s" strategy from patients/edit.php to avoid bind errors.
        $types = str_repeat('s', count($values));
        return $db->execute($sql, $values, $types) !== false;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findForApi(int $patientId): ?array
    {
        $db = Database::getInstance();

        return $db->fetchOne(
            "SELECT id, full_name, date_of_birth, phone, email,
                    insurance_provider, insurance_id, insurance_type, insurance_coverage,
                    allergies, medical_history
             FROM patients WHERE id = ?",
            [$patientId],
            'i'
        );
    }

    public static function deleteById(int $patientId): bool
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM patients WHERE id = ?', [$patientId], 'i') !== false;
    }
}
