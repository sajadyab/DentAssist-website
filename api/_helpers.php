<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

// =============================================================================
// Repository layer (replaces former includes/classes/* — shared by api/*.php
// and staff pages that require this file after bootstrap).
// =============================================================================

function repo_user_list_doctors(bool $onlyActive = false): array
{
    $db = Database::getInstance();
    $sql = "SELECT id, full_name FROM users WHERE role = 'doctor'";
    if ($onlyActive) {
        $sql .= ' AND COALESCE(is_active, 1) = 1';
    }
    $sql .= ' ORDER BY full_name';

    return $db->fetchAll($sql);
}

function repo_user_is_doctor(int $userId, bool $onlyActive = false): bool
{
    $db = Database::getInstance();
    $sql = "SELECT id FROM users WHERE id = ? AND role = 'doctor'";
    if ($onlyActive) {
        $sql .= ' AND COALESCE(is_active, 1) = 1';
    }

    return (bool) $db->fetchOne($sql, [$userId], 'i');
}

/**
 * @return array<int,array{id:mixed,full_name:mixed}>
 */
function repo_patient_list_for_select(int $limit = 0): array
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

function repo_patient_count_by_search(string $search = ''): int
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
function repo_patient_search(string $search, int $limit, int $offset): array
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
function repo_patient_find_by_id(int $patientId): ?array
{
    $db = Database::getInstance();

    return $db->fetchOne('SELECT * FROM patients WHERE id = ?', [$patientId], 'i');
}

/**
 * @return array<string,mixed>|null
 */
function repo_patient_find_with_account_username(int $patientId): ?array
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

function repo_patient_update_from_edit_payload(int $patientId, array $payload): bool
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
    $types = str_repeat('s', count($values));

    return $db->execute($sql, $values, $types) !== false;
}

/**
 * @return array<string,mixed>|null
 */
function repo_patient_find_for_api(int $patientId): ?array
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

function repo_patient_delete_by_id(int $patientId): bool
{
    $db = Database::getInstance();

    return $db->execute('DELETE FROM patients WHERE id = ?', [$patientId], 'i') !== false;
}

function repo_patient_delete_cascade(int $patientId): bool
{
    repo_appointment_delete_for_patient($patientId);
    repo_treatment_plan_delete_for_patient($patientId);
    repo_xray_delete_for_patient($patientId);
    repo_invoice_delete_for_patient($patientId);

    return repo_patient_delete_by_id($patientId);
}

/**
 * @return array<int,array<string,mixed>>
 */
function repo_appointment_list_for_patient(int $patientId): array
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

function repo_appointment_delete_for_patient(int $patientId): bool
{
    $db = Database::getInstance();

    return $db->execute('DELETE FROM appointments WHERE patient_id = ?', [$patientId], 'i') !== false;
}

/**
 * @return array<string,mixed>|null
 */
function repo_appointment_find_active_chair_conflict(
    string $appointmentDate,
    string $appointmentTime,
    ?int $chairNumber,
    ?int $excludeAppointmentId = null
): ?array {
    $db = Database::getInstance();
    if ($excludeAppointmentId === null) {
        return $db->fetchOne(
            "SELECT id FROM appointments
             WHERE appointment_date = ? AND appointment_time = ? AND chair_number = ? AND status != 'cancelled'",
            [$appointmentDate, $appointmentTime, $chairNumber],
            'ssi'
        );
    }

    return $db->fetchOne(
        "SELECT id FROM appointments
         WHERE appointment_date = ? AND appointment_time = ? AND chair_number = ?
         AND status != 'cancelled' AND id != ?",
        [$appointmentDate, $appointmentTime, $chairNumber, $excludeAppointmentId],
        'ssii'
    );
}

/**
 * @return array<string,mixed>|null
 */
function repo_appointment_find_by_id_with_patient_name(int $appointmentId): ?array
{
    $db = Database::getInstance();

    return $db->fetchOne(
        "SELECT a.*, p.full_name as patient_name
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         WHERE a.id = ?",
        [$appointmentId],
        'i'
    );
}

/**
 * @param array<string,mixed> $data
 */
function repo_appointment_insert_staff_scheduled(array $data): int|false
{
    $db = Database::getInstance();

    return $db->insert(
        "INSERT INTO appointments (
            patient_id, doctor_id, appointment_date, appointment_time, duration,
            treatment_type, description, chair_number, status, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $data['patient_id'],
            $data['doctor_id'],
            $data['appointment_date'],
            $data['appointment_time'],
            $data['duration'],
            $data['treatment_type'],
            $data['description'],
            $data['chair_number'],
            'scheduled',
            $data['notes'],
            $data['created_by'],
        ],
        'iississsssi'
    );
}

/**
 * @param array<string,mixed> $data
 */
function repo_appointment_update_staff(int $appointmentId, array $data): bool
{
    $db = Database::getInstance();
    $result = $db->execute(
        "UPDATE appointments SET
            patient_id = ?, doctor_id = ?, appointment_date = ?, appointment_time = ?,
            duration = ?, treatment_type = ?, description = ?, chair_number = ?, status = ?, notes = ?
         WHERE id = ?",
        [
            $data['patient_id'],
            $data['doctor_id'],
            $data['appointment_date'],
            $data['appointment_time'],
            $data['duration'],
            $data['treatment_type'],
            $data['description'],
            $data['chair_number'],
            $data['status'],
            $data['notes'],
            $appointmentId,
        ],
        'iississsssi'
    );

    return $result !== false;
}

/**
 * @return array<int,array<string,mixed>>
 */
function repo_dashboard_list_today_appointments(string $ymd, ?int $doctorId = null): array
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
function repo_dashboard_get_appointment_counts(string $todayYmd, ?int $doctorId = null): array
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
 * @return array{pending:int,active:int,expiring:int}
 */
function repo_dashboard_get_subscription_counts(): array
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

function repo_dashboard_get_subscription_revenue_total(): float
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
function repo_dashboard_list_online_appointment_requests(?int $doctorId = null): array
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
function repo_dashboard_list_weekly_waiting_queue(int $doctorId): array
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
function repo_dashboard_list_inventory_notice_candidates(): array
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
 * @return array{id:mixed,full_name:mixed}|null
 */
function repo_dashboard_find_active_doctor_row(int $doctorId): ?array
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

function repo_dashboard_find_user_full_name(int $userId): string
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
function repo_dashboard_list_calendar_appointments(int $doctorId, string $rangeStartYmd, string $rangeEndYmd): array
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
function repo_dashboard_list_calendar_requests(int $doctorId, string $rangeStartYmd, string $rangeEndYmd): array
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

/**
 * @return array<int,array<string,mixed>>
 */
function repo_invoice_list_for_patient(int $patientId): array
{
    $db = Database::getInstance();

    return $db->fetchAll(
        "SELECT * FROM invoices
         WHERE patient_id = ?
         ORDER BY invoice_date DESC",
        [$patientId],
        'i'
    );
}

function repo_invoice_find_pending_subscription_invoice_id(int $patientId): ?int
{
    $db = Database::getInstance();
    $row = $db->fetchOne(
        "SELECT id FROM invoices WHERE patient_id = ? AND notes LIKE '%Subscription%' AND payment_status = 'pending'",
        [$patientId],
        'i'
    );
    $id = (int) ($row['id'] ?? 0);

    return $id > 0 ? $id : null;
}

function repo_invoice_mark_paid(int $invoiceId): bool
{
    $db = Database::getInstance();

    return $db->execute(
        'UPDATE invoices SET payment_status = \'paid\', paid_amount = total_amount, paid_at = NOW() WHERE id = ?',
        [$invoiceId],
        'i'
    ) !== false;
}

function repo_invoice_create_paid_subscription_invoice(
    int $patientId,
    string $invoiceNumber,
    float $amount,
    string $invoiceDate,
    string $dueDate,
    string $notes,
    int $createdBy
): int {
    $db = Database::getInstance();

    return (int) $db->insert(
        "INSERT INTO invoices (patient_id, invoice_number, subtotal, total_amount, payment_status, invoice_date, due_date, notes, created_by, paid_at)
         VALUES (?, ?, ?, ?, 'paid', ?, ?, ?, ?, NOW())",
        [$patientId, $invoiceNumber, $amount, $amount, $invoiceDate, $dueDate, $notes, $createdBy],
        'isddsssi'
    );
}

function repo_invoice_delete_for_patient(int $patientId): bool
{
    $db = Database::getInstance();

    return $db->execute('DELETE FROM invoices WHERE patient_id = ?', [$patientId], 'i') !== false;
}

/**
 * @return array<string,mixed>|null
 */
function repo_xray_find_dental_history_handwritten_by_id(int $xrayId, int $patientId): ?array
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

function repo_xray_delete_by_id_for_patient(int $xrayId, int $patientId): bool
{
    $db = Database::getInstance();

    return $db->execute('DELETE FROM xrays WHERE id = ? AND patient_id = ? LIMIT 1', [$xrayId, $patientId], 'ii') !== false;
}

/**
 * @return array<string,mixed>|null
 */
function repo_xray_find_latest_dental_history_handwritten(int $patientId): ?array
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
function repo_xray_list_dental_history_handwritten_images(int $patientId): array
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

function repo_xray_insert_dental_history_handwritten_from_edit_form(
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
function repo_xray_list_for_patient_excluding_dental_history(int $patientId): array
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

function repo_xray_delete_for_patient(int $patientId): bool
{
    $db = Database::getInstance();

    return $db->execute('DELETE FROM xrays WHERE patient_id = ?', [$patientId], 'i') !== false;
}

/**
 * @return array<int,array<string,mixed>>
 */
function repo_treatment_plan_list_for_patient(int $patientId): array
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

function repo_treatment_plan_delete_for_patient(int $patientId): bool
{
    $db = Database::getInstance();

    return $db->execute('DELETE FROM treatment_plans WHERE patient_id = ?', [$patientId], 'i') !== false;
}

/**
 * @return array<int,array<string,mixed>>
 */
function repo_subscription_list_pending_subscriptions(): array
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

function repo_subscription_get_patient_plan(int $patientId): ?string
{
    $db = Database::getInstance();
    $row = $db->fetchOne('SELECT subscription_type FROM patients WHERE id = ?', [$patientId], 'i');
    $plan = trim((string) ($row['subscription_type'] ?? ''));

    return $plan !== '' ? $plan : null;
}

function repo_subscription_activate(int $patientId, string $startDateYmd, string $endDateYmd): bool
{
    $db = Database::getInstance();

    return $db->execute(
        "UPDATE patients SET subscription_status = 'active', subscription_start_date = ?, subscription_end_date = ? WHERE id = ?",
        [$startDateYmd, $endDateYmd, $patientId],
        'ssi'
    ) !== false;
}

function repo_subscription_complete_pending_payment(int $patientId, string $reference, int $processedBy): bool
{
    $db = Database::getInstance();

    return $db->execute(
        "UPDATE subscription_payments SET status = 'completed', payment_reference = ?, payment_date = NOW(), processed_by = ?
         WHERE patient_id = ? AND status = 'pending'",
        [$reference, $processedBy, $patientId],
        'sii'
    ) !== false;
}

function repo_subscription_count_active(): int
{
    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active'");

    return (int) ($row['count'] ?? 0);
}

function repo_subscription_count_expiring_soon_30_days(): int
{
    $db = Database::getInstance();
    $row = $db->fetchOne(
        "SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active' AND subscription_end_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)"
    );

    return (int) ($row['count'] ?? 0);
}

function repo_subscription_reset_to_none(int $patientId): bool
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

function repo_subscription_fail_pending_payment(int $patientId, string $notes): bool
{
    $db = Database::getInstance();

    return $db->execute(
        "UPDATE subscription_payments SET status = 'failed', notes = ? WHERE patient_id = ? AND status = 'pending'",
        [$notes, $patientId],
        'si'
    ) !== false;
}

/**
 * @return array{ok:bool,error:?string,end_date:?string,plan:?string}
 */
function repo_subscription_confirm_clinic_payment(int $patientId, string $reference, int $processedBy): array
{
    $db = Database::getInstance();
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+1 year'));
    try {
        $plan = repo_subscription_get_patient_plan($patientId) ?? 'basic';
        $db->beginTransaction();
        repo_subscription_activate($patientId, $startDate, $endDate);
        repo_subscription_complete_pending_payment($patientId, $reference, $processedBy);
        $invoiceId = repo_invoice_find_pending_subscription_invoice_id($patientId);
        if ($invoiceId !== null) {
            repo_invoice_mark_paid($invoiceId);
        } else {
            $prices = ['basic' => 29, 'premium' => 49, 'family' => 79];
            $annualAmount = (float) (($prices[$plan] ?? 29) * 12);
            $invoiceNumber = generateInvoiceNumber();
            $notes = "Subscription: {$plan} plan (Annual) - Paid at Clinic";
            repo_invoice_create_paid_subscription_invoice(
                $patientId,
                $invoiceNumber,
                $annualAmount,
                $startDate,
                $startDate,
                $notes,
                $processedBy
            );
        }
        $db->commit();

        return ['ok' => true, 'error' => null, 'end_date' => $endDate, 'plan' => $plan];
    } catch (Exception $e) {
        $db->rollback();

        return ['ok' => false, 'error' => $e->getMessage(), 'end_date' => null, 'plan' => null];
    }
}

/**
 * @return array{ok:bool,error:?string}
 */
function repo_subscription_reject_pending(int $patientId, string $reason): array
{
    try {
        repo_subscription_reset_to_none($patientId);
        repo_subscription_fail_pending_payment($patientId, 'Rejected: ' . $reason);

        return ['ok' => true, 'error' => null];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// =============================================================================
// HTTP API helpers (output buffer only when this file is the entry script under /api/)
// =============================================================================

$__helpersScript = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$apiHelpersIsDirectApi = str_contains($__helpersScript, '/api/');

if ($apiHelpersIsDirectApi) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
    ob_start();
}

function api_wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    if (stripos($accept, 'application/json') !== false) {
        return true;
    }
    $xhr = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    if (strcasecmp($xhr, 'XMLHttpRequest') === 0) {
        return true;
    }

    return true;
}

function api_respond(array $payload, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function api_ok(array $payload = [], string $message = ''): void
{
    if ($message !== '') {
        $payload['message'] = $message;
    }
    $payload['success'] = true;
    api_respond($payload, 200);
}

function api_error(string $message, int $statusCode = 400, array $payload = []): void
{
    $payload['success'] = false;
    $payload['message'] = $message;
    api_respond($payload, $statusCode);
}

function api_require_method(string $method): void
{
    $actual = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
    if (strcasecmp($actual, $method) !== 0) {
        api_error('Method not allowed.', 405);
    }
}

function api_require_login(): void
{
    if (!Auth::isLoggedIn()) {
        api_error('Unauthorized.', 401);
    }
}

function api_require_admin(): void
{
    api_require_login();
    if (!Auth::isAdmin()) {
        api_error('Forbidden.', 403);
    }
}
