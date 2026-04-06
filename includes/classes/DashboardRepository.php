<?php
declare(strict_types=1);

class DashboardRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listTodayAppointments(string $ymd, ?int $doctorId = null): array
    {
        $db = Database::getInstance();

        $sql = "SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name
                FROM appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN users u ON a.doctor_id = u.id
                WHERE a.appointment_date = ?";
        $params = [$ymd];
        $types = 's';

        if ($doctorId !== null && $doctorId > 0) {
            $sql .= ' AND a.doctor_id = ?';
            $params[] = $doctorId;
            $types .= 'i';
        }

        $sql .= ' ORDER BY a.appointment_time';

        return $db->fetchAll($sql, $params, $types);
    }

    /**
     * @return array{upcoming:int,completed_today:int}
     */
    public static function getAppointmentCounts(string $todayYmd, ?int $doctorId = null): array
    {
        $db = Database::getInstance();

        if ($doctorId !== null && $doctorId > 0) {
            $upcoming = (int) (($db->fetchOne(
                "SELECT COUNT(*) as count
                 FROM appointments
                 WHERE appointment_date >= ?
                   AND status NOT IN ('cancelled', 'completed')
                   AND doctor_id = ?",
                [$todayYmd, $doctorId],
                'si'
            )['count'] ?? 0));

            $completed = (int) (($db->fetchOne(
                "SELECT COUNT(*) as count
                 FROM appointments
                 WHERE appointment_date = ?
                   AND status = 'completed'
                   AND doctor_id = ?",
                [$todayYmd, $doctorId],
                'si'
            )['count'] ?? 0));

            return ['upcoming' => $upcoming, 'completed_today' => $completed];
        }

        $upcoming = (int) (($db->fetchOne(
            "SELECT COUNT(*) as count
             FROM appointments
             WHERE appointment_date >= ?
               AND status NOT IN ('cancelled', 'completed')",
            [$todayYmd],
            's'
        )['count'] ?? 0));

        $completed = (int) (($db->fetchOne(
            "SELECT COUNT(*) as count
             FROM appointments
             WHERE appointment_date = ?
               AND status = 'completed'",
            [$todayYmd],
            's'
        )['count'] ?? 0));

        return ['upcoming' => $upcoming, 'completed_today' => $completed];
    }

    /**
     * Subscription counts for the dashboard, compatible with older schemas.
     *
     * @return array{pending:int,active:int,expiring:int}
     */
    public static function getSubscriptionCounts(): array
    {
        $db = Database::getInstance();

        if (dbColumnExists('patients', 'subscription_status')) {
            $pending = (int) (($db->fetchOne(
                "SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'pending'"
            )['count'] ?? 0));

            $active = (int) (($db->fetchOne(
                "SELECT COUNT(*) as count
                 FROM patients
                 WHERE subscription_status = 'active'
                   AND subscription_end_date IS NOT NULL
                   AND subscription_end_date >= CURDATE()"
            )['count'] ?? 0));

            $expiring = (int) (($db->fetchOne(
                "SELECT COUNT(*) as count
                 FROM patients
                 WHERE subscription_status = 'active'
                   AND subscription_end_date IS NOT NULL
                   AND subscription_end_date >= CURDATE()
                   AND subscription_end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            )['count'] ?? 0));

            return ['pending' => $pending, 'active' => $active, 'expiring' => $expiring];
        }

        $pending = 0;
        if (dbTableExists('subscription_payments')) {
            $pending = (int) (($db->fetchOne(
                "SELECT COUNT(DISTINCT patient_id) as count
                 FROM subscription_payments
                 WHERE status = 'pending'"
            )['count'] ?? 0));
        }

        $noPendingSub = dbTableExists('subscription_payments')
            ? "AND NOT EXISTS (
                SELECT 1 FROM subscription_payments sp
                WHERE sp.patient_id = p.id AND sp.status = 'pending'
            )"
            : '';

        $active = (int) (($db->fetchOne(
            "SELECT COUNT(*) as count
             FROM patients p
             WHERE p.subscription_type != 'none'
               AND p.subscription_end_date IS NOT NULL
               AND p.subscription_end_date >= CURDATE()
               {$noPendingSub}"
        )['count'] ?? 0));

        $expiring = (int) (($db->fetchOne(
            "SELECT COUNT(*) as count
             FROM patients p
             WHERE p.subscription_type != 'none'
               AND p.subscription_end_date IS NOT NULL
               AND p.subscription_end_date >= CURDATE()
               AND p.subscription_end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
               {$noPendingSub}"
        )['count'] ?? 0));

        return ['pending' => $pending, 'active' => $active, 'expiring' => $expiring];
    }

    public static function getSubscriptionRevenueTotal(): float
    {
        $db = Database::getInstance();

        if (dbTableExists('subscription_payments')) {
            return (float) (($db->fetchOne(
                "SELECT SUM(amount) as total FROM subscription_payments WHERE status = 'completed'"
            )['total'] ?? 0));
        }

        return (float) (($db->fetchOne(
            "SELECT COALESCE(SUM(paid_amount), 0) AS total
             FROM invoices
             WHERE payment_status = 'paid'
               AND notes IS NOT NULL
               AND notes LIKE '%Subscription%'"
        )['total'] ?? 0));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listOnlineAppointmentRequests(?int $doctorId = null): array
    {
        if (!dbTableExists('appointment_requests')) {
            return [];
        }

        $db = Database::getInstance();
        $sql = "SELECT ar.*, p.full_name AS patient_name, p.phone AS patient_phone, u.full_name AS doctor_name
                FROM appointment_requests ar
                INNER JOIN patients p ON p.id = ar.patient_id
                INNER JOIN users u ON u.id = ar.doctor_id";
        $params = [];
        $types = '';

        if ($doctorId !== null && $doctorId > 0) {
            $sql .= ' WHERE ar.doctor_id = ?';
            $params[] = $doctorId;
            $types = 'i';
        }

        $sql .= ' ORDER BY ar.requested_date ASC, ar.requested_time ASC, ar.id ASC';

        return $db->fetchAll($sql, $params, $types);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listWeeklyWaitingQueue(int $doctorId): array
    {
        if (!dbTableExists('waiting_queue') || $doctorId <= 0) {
            return [];
        }

        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT wq.*, COALESCE(p.full_name, wq.patient_name) AS patient_name,
                    p.phone AS patient_phone, u.full_name AS doctor_name
             FROM waiting_queue wq
             LEFT JOIN patients p ON wq.patient_id = p.id
             LEFT JOIN users u ON u.id = wq.doctor_id
             WHERE wq.queue_type = 'weekly'
               AND wq.status = 'waiting'
               AND wq.doctor_id = ?
             ORDER BY wq.preferred_date IS NULL, wq.preferred_date ASC, wq.joined_at ASC",
            [$doctorId],
            'i'
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listInventoryNoticeCandidates(): array
    {
        if (!dbTableExists('inventory')) {
            return [];
        }

        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT item_name, quantity, reorder_level, expiry_date
             FROM inventory
             WHERE (expiry_date IS NOT NULL AND expiry_date <> '' AND expiry_date <> '0000-00-00' AND expiry_date < CURDATE())
                OR (expiry_date IS NOT NULL AND expiry_date <> '' AND expiry_date <> '0000-00-00'
                    AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))
                OR (quantity <= reorder_level AND quantity > 0)"
        );
    }

    /**
     * Used by the staff dashboard calendar include to validate assistant-selected doctors.
     *
     * @return array{id:mixed,full_name:mixed}|null
     */
    public static function findActiveDoctorRow(int $doctorId): ?array
    {
        if ($doctorId <= 0) {
            return null;
        }

        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT id, full_name
             FROM users
             WHERE id = ?
               AND role = 'doctor'
               AND COALESCE(is_active, 1) = 1",
            [$doctorId],
            'i'
        );
    }

    public static function findUserFullName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $db = Database::getInstance();
        $row = $db->fetchOne('SELECT full_name FROM users WHERE id = ?', [$userId], 'i');
        return $row ? (string) ($row['full_name'] ?? '') : '';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listCalendarAppointments(int $doctorId, string $rangeStartYmd, string $rangeEndYmd): array
    {
        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT a.*, p.full_name AS patient_name, p.phone AS patient_phone, u.full_name AS doctor_name
             FROM appointments a
             INNER JOIN patients p ON p.id = a.patient_id
             INNER JOIN users u ON u.id = a.doctor_id
             WHERE a.doctor_id = ?
               AND a.appointment_date BETWEEN ? AND ?
               AND a.status NOT IN ('cancelled', 'no-show')
             ORDER BY a.appointment_date, a.appointment_time",
            [$doctorId, $rangeStartYmd, $rangeEndYmd],
            'iss'
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listCalendarRequests(int $doctorId, string $rangeStartYmd, string $rangeEndYmd): array
    {
        if (!dbTableExists('appointment_requests')) {
            return [];
        }

        $db = Database::getInstance();
        return $db->fetchAll(
            "SELECT ar.*, p.full_name AS patient_name, p.phone AS patient_phone, u.full_name AS doctor_name
             FROM appointment_requests ar
             INNER JOIN patients p ON p.id = ar.patient_id
             INNER JOIN users u ON u.id = ar.doctor_id
             WHERE ar.doctor_id = ?
               AND ar.requested_date BETWEEN ? AND ?
             ORDER BY ar.requested_date, ar.requested_time, ar.id",
            [$doctorId, $rangeStartYmd, $rangeEndYmd],
            'iss'
        );
    }
}

