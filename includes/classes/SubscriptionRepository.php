<?php
declare(strict_types=1);

class SubscriptionRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listPendingSubscriptions(): array
    {
        $db = Database::getInstance();

        return $db->fetchAll(
            "SELECT p.id, p.full_name, p.phone, p.email, p.subscription_type, p.subscription_start_date, p.subscription_end_date, p.subscription_status,
                    sp.amount, sp.created_at, sp.payment_method, sp.id as payment_id
             FROM patients p
             LEFT JOIN subscription_payments sp ON p.id = sp.patient_id AND sp.status = 'pending'
             WHERE p.subscription_status = 'pending'
             ORDER BY sp.created_at DESC"
        );
    }

    public static function getPatientSubscriptionPlan(int $patientId): ?string
    {
        $db = Database::getInstance();

        $row = $db->fetchOne('SELECT subscription_type FROM patients WHERE id = ?', [$patientId], 'i');
        $plan = trim((string) ($row['subscription_type'] ?? ''));
        return $plan !== '' ? $plan : null;
    }

    public static function activateSubscription(int $patientId, string $startDateYmd, string $endDateYmd): bool
    {
        $db = Database::getInstance();

        return $db->execute(
            "UPDATE patients SET subscription_status = 'active', subscription_start_date = ?, subscription_end_date = ? WHERE id = ?",
            [$startDateYmd, $endDateYmd, $patientId],
            'ssi'
        ) !== false;
    }

    public static function completePendingPayment(int $patientId, string $reference, int $processedBy): bool
    {
        $db = Database::getInstance();

        return $db->execute(
            "UPDATE subscription_payments SET status = 'completed', payment_reference = ?, payment_date = NOW(), processed_by = ?
             WHERE patient_id = ? AND status = 'pending'",
            [$reference, $processedBy, $patientId],
            'sii'
        ) !== false;
    }

    public static function countActive(): int
    {
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active'");
        return (int) ($row['count'] ?? 0);
    }

    public static function countExpiringSoon30Days(): int
    {
        $db = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active' AND subscription_end_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)"
        );
        return (int) ($row['count'] ?? 0);
    }

    public static function resetSubscriptionToNone(int $patientId): bool
    {
        $db = Database::getInstance();

        return $db->execute(
            "UPDATE patients
             SET subscription_status = 'none', subscription_type = 'none',
                 subscription_start_date = NULL, subscription_end_date = NULL
             WHERE id = ?",
            [$patientId],
            'i'
        ) !== false;
    }

    public static function failPendingPayment(int $patientId, string $notes): bool
    {
        $db = Database::getInstance();

        return $db->execute(
            "UPDATE subscription_payments SET status = 'failed', notes = ? WHERE patient_id = ? AND status = 'pending'",
            [$notes, $patientId],
            'si'
        ) !== false;
    }
}
