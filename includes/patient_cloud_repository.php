<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../supabase_client.php';

function patient_portal_supabase_client(): ?SupabaseAPI
{
    static $client = false;

    if ($client !== false) {
        return $client;
    }

    if (!defined('SUPABASE_URL') || !defined('SUPABASE_KEY')) {
        $client = null;

        return null;
    }

    $url = trim((string) SUPABASE_URL);
    $key = trim((string) SUPABASE_KEY);
    if ($url === '' || $key === '') {
        $client = null;

        return null;
    }

    try {
        $client = new SupabaseAPI($url, $key);
    } catch (Throwable $e) {
        error_log('Supabase init failed: ' . $e->getMessage());
        $client = null;
    }

    return $client;
}

function patient_portal_fetch_patient_cloud_first(int $patientId): ?array
{
    $db = Database::getInstance();
    $supabase = patient_portal_supabase_client();

    if ($supabase !== null) {
        try {
            $rows = $supabase->select('patients', [
                'select' => '*',
                'local_id' => 'eq.' . $patientId,
                'limit' => 1,
            ]);
            if (is_array($rows) && !empty($rows[0])) {
                $patient = $rows[0];
                if (!isset($patient['id']) && isset($patient['local_id'])) {
                    $patient['id'] = (int) $patient['local_id'];
                }

                return $patient;
            }
            $rowsById = $supabase->select('patients', [
                'select' => '*',
                'id' => 'eq.' . $patientId,
                'limit' => 1,
            ]);
            if (is_array($rowsById) && !empty($rowsById[0])) {
                return $rowsById[0];
            }
        } catch (Throwable $e) {
            error_log('Supabase patient fetch failed: ' . $e->getMessage());
        }
    }

    return $db->fetchOne('SELECT * FROM patients WHERE id = ?', [$patientId], 'i');
}

function patient_portal_fetch_appointments_cloud_first(int $patientId, ?string $startDate = null, ?string $endDate = null): array
{
    $db = Database::getInstance();
    $supabase = patient_portal_supabase_client();

    if ($supabase !== null) {
        try {
            $baseQuery = [
                'select' => '*',
                'order' => 'appointment_date.desc,appointment_time.desc',
            ];
            $rows = $supabase->select('appointments', $baseQuery + [
                'patient_id' => 'eq.' . $patientId,
            ]);
            if (is_array($rows) && empty($rows)) {
                $rows = $supabase->select('appointments', $baseQuery + [
                    'patient_local_id' => 'eq.' . $patientId,
                ]);
            }
            if (is_array($rows) && empty($rows)) {
                $rows = $supabase->select('appointments', $baseQuery + [
                    'local_patient_id' => 'eq.' . $patientId,
                ]);
            }
            if (is_array($rows)) {
                $normalized = patient_portal_normalize_appointments($rows);
                if ($startDate !== null && $endDate !== null) {
                    $normalized = array_values(array_filter($normalized, static function (array $apt) use ($startDate, $endDate): bool {
                        $d = (string) ($apt['appointment_date'] ?? '');

                        return $d >= $startDate && $d <= $endDate;
                    }));
                }

                return $normalized;
            }
        } catch (Throwable $e) {
            error_log('Supabase appointments fetch failed: ' . $e->getMessage());
        }
    }

    if ($startDate !== null && $endDate !== null) {
        $rows = $db->fetchAll(
            "SELECT a.*, u.full_name AS doctor_name
             FROM appointments a
             LEFT JOIN users u ON u.id = a.doctor_id
             WHERE a.patient_id = ?
               AND a.appointment_date BETWEEN ? AND ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            [$patientId, $startDate, $endDate],
            'iss'
        );
    } else {
        $rows = $db->fetchAll(
            "SELECT a.*, u.full_name AS doctor_name
             FROM appointments a
             LEFT JOIN users u ON u.id = a.doctor_id
             WHERE a.patient_id = ?
             ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            [$patientId],
            'i'
        );
    }

    return patient_portal_normalize_appointments($rows);
}

function patient_portal_normalize_appointments(array $appointments): array
{
    $doctorIds = [];
    foreach ($appointments as &$apt) {
        if (!isset($apt['id']) && isset($apt['local_id'])) {
            $apt['id'] = (int) $apt['local_id'];
        }
        if (!empty($apt['doctor_id'])) {
            $doctorIds[] = (int) $apt['doctor_id'];
        }
        if (empty($apt['end_time']) && !empty($apt['appointment_date']) && !empty($apt['appointment_time'])) {
            $duration = isset($apt['duration']) ? (int) $apt['duration'] : 30;
            $startTs = strtotime((string) $apt['appointment_date'] . ' ' . (string) $apt['appointment_time']);
            if ($startTs !== false) {
                $apt['end_time'] = date('H:i:s', $startTs + ($duration * 60));
            }
        }
    }
    unset($apt);

    if (!empty($doctorIds)) {
        $doctorIds = array_values(array_unique($doctorIds));
        $db = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
        $doctorRows = $db->fetchAll(
            "SELECT id, full_name FROM users WHERE id IN ($placeholders)",
            $doctorIds,
            str_repeat('i', count($doctorIds))
        );
        $doctorMap = [];
        foreach ($doctorRows as $d) {
            $doctorMap[(int) $d['id']] = (string) ($d['full_name'] ?? '');
        }
        foreach ($appointments as &$apt) {
            $doctorId = (int) ($apt['doctor_id'] ?? 0);
            if ($doctorId > 0 && empty($apt['doctor_name']) && isset($doctorMap[$doctorId])) {
                $apt['doctor_name'] = $doctorMap[$doctorId];
            }
        }
        unset($apt);
    }

    usort($appointments, static function (array $a, array $b): int {
        $aTs = strtotime((string) ($a['appointment_date'] ?? '') . ' ' . (string) ($a['appointment_time'] ?? '00:00:00')) ?: 0;
        $bTs = strtotime((string) ($b['appointment_date'] ?? '') . ' ' . (string) ($b['appointment_time'] ?? '00:00:00')) ?: 0;

        return $bTs <=> $aTs;
    });

    return $appointments;
}

function patient_portal_pick_next_appointment(array $appointments): ?array
{
    $today = date('Y-m-d');
    $eligible = [];
    foreach ($appointments as $apt) {
        $status = (string) ($apt['status'] ?? '');
        $date = (string) ($apt['appointment_date'] ?? '');
        if (in_array($status, ['scheduled', 'checked-in'], true) && $date >= $today) {
            $eligible[] = $apt;
        }
    }
    if (empty($eligible)) {
        return null;
    }

    usort($eligible, static function (array $a, array $b): int {
        $aTs = strtotime((string) ($a['appointment_date'] ?? '') . ' ' . (string) ($a['appointment_time'] ?? '00:00:00')) ?: PHP_INT_MAX;
        $bTs = strtotime((string) ($b['appointment_date'] ?? '') . ' ' . (string) ($b['appointment_time'] ?? '00:00:00')) ?: PHP_INT_MAX;

        return $aTs <=> $bTs;
    });

    return $eligible[0];
}

function patient_portal_count_completed_visits(array $appointments): int
{
    $count = 0;
    foreach ($appointments as $apt) {
        if (($apt['status'] ?? '') === 'completed') {
            $count++;
        }
    }

    return $count;
}

function patient_portal_count_referred_patients_cloud_first(int $patientId): int
{
    $db = Database::getInstance();
    $supabase = patient_portal_supabase_client();

    if ($supabase !== null) {
        try {
            $rows = $supabase->select('patients', [
                'select' => 'id',
                'referred_by' => 'eq.' . $patientId,
            ]);
            if (is_array($rows)) {
                return count($rows);
            }
        } catch (Throwable $e) {
            error_log('Supabase referred patients count failed: ' . $e->getMessage());
        }
    }

    return (int) (($db->fetchOne(
        'SELECT COUNT(*) as count FROM patients WHERE referred_by = ?',
        [$patientId],
        'i'
    )['count'] ?? 0));
}

function patient_portal_list_referred_patients_cloud_first(int $patientId): array
{
    $db = Database::getInstance();
    $supabase = patient_portal_supabase_client();

    if ($supabase !== null) {
        try {
            $rows = $supabase->select('patients', [
                'select' => 'full_name,created_at,email,phone',
                'referred_by' => 'eq.' . $patientId,
                'order' => 'created_at.desc',
            ]);
            if (is_array($rows)) {
                return $rows;
            }
        } catch (Throwable $e) {
            error_log('Supabase referred patients list failed: ' . $e->getMessage());
        }
    }

    return $db->fetchAll(
        'SELECT full_name, created_at, email, phone FROM patients WHERE referred_by = ? ORDER BY created_at DESC',
        [$patientId],
        'i'
    );
}

function patient_portal_list_completed_appointments_cloud_first(int $patientId, int $limit = 10): array
{
    $appointments = patient_portal_fetch_appointments_cloud_first($patientId);
    $completed = array_values(array_filter($appointments, static function (array $apt): bool {
        return ((string) ($apt['status'] ?? '')) === 'completed';
    }));

    return array_slice($completed, 0, max(1, $limit));
}

function patient_portal_list_invoices_cloud_first(int $patientId): array
{
    $db = Database::getInstance();
    $supabase = patient_portal_supabase_client();

    if ($supabase !== null) {
        try {
            $rows = $supabase->select('invoices', [
                'select' => '*',
                'patient_id' => 'eq.' . $patientId,
                'order' => 'invoice_date.desc',
            ]);
            if (is_array($rows)) {
                return $rows;
            }
        } catch (Throwable $e) {
            error_log('Supabase invoices fetch failed: ' . $e->getMessage());
        }
    }

    return $db->fetchAll(
        'SELECT * FROM invoices WHERE patient_id = ? ORDER BY invoice_date DESC',
        [$patientId],
        'i'
    );
}

function patient_portal_list_subscription_payments_cloud_first(int $patientId): array
{
    $db = Database::getInstance();
    $supabase = patient_portal_supabase_client();

    if ($supabase !== null) {
        try {
            $rows = $supabase->select('subscription_payments', [
                'select' => '*',
                'patient_id' => 'eq.' . $patientId,
                'order' => 'payment_date.desc',
            ]);
            if (is_array($rows)) {
                return $rows;
            }
        } catch (Throwable $e) {
            error_log('Supabase subscription payments fetch failed: ' . $e->getMessage());
        }
    }

    return $db->fetchAll(
        'SELECT * FROM subscription_payments WHERE patient_id = ? ORDER BY payment_date DESC',
        [$patientId],
        'i'
    );
}

function patient_portal_find_invoice_for_patient_cloud_first(int $invoiceId, int $patientId): ?array
{
    $db = Database::getInstance();
    $supabase = patient_portal_supabase_client();

    if ($supabase !== null) {
        try {
            $rows = $supabase->select('invoices', [
                'select' => '*',
                'id' => 'eq.' . $invoiceId,
                'patient_id' => 'eq.' . $patientId,
                'limit' => 1,
            ]);
            if (is_array($rows) && !empty($rows[0])) {
                $invoice = $rows[0];
                $patient = patient_portal_fetch_patient_cloud_first($patientId);
                if ($patient) {
                    $invoice['patient_name'] = $patient['full_name'] ?? '';
                    $invoice['phone'] = $patient['phone'] ?? null;
                    $invoice['email'] = $patient['email'] ?? null;
                    $invoice['address'] = $patient['address'] ?? $patient['address_line1'] ?? null;
                    $invoice['country'] = $patient['country'] ?? null;
                }
                if (!empty($invoice['appointment_id'])) {
                    $appointments = patient_portal_fetch_appointments_cloud_first($patientId);
                    foreach ($appointments as $apt) {
                        if ((int) ($apt['id'] ?? 0) === (int) $invoice['appointment_id']) {
                            $invoice['appointment_date'] = $apt['appointment_date'] ?? null;
                            $invoice['treatment_type'] = $apt['treatment_type'] ?? null;
                            break;
                        }
                    }
                }

                return $invoice;
            }
        } catch (Throwable $e) {
            error_log('Supabase invoice fetch failed: ' . $e->getMessage());
        }
    }

    $patientAddressColumn = dbColumnExists('patients', 'address') ? 'p.address' : (dbColumnExists('patients', 'address_line1') ? 'p.address_line1' : 'NULL');

    return $db->fetchOne(
        "SELECT i.*, p.full_name as patient_name, p.phone, p.email, {$patientAddressColumn} AS address, p.country,
                a.appointment_date, a.treatment_type
         FROM invoices i
         JOIN patients p ON i.patient_id = p.id
         LEFT JOIN appointments a ON i.appointment_id = a.id
         WHERE i.id = ? AND i.patient_id = ?",
        [$invoiceId, $patientId],
        'ii'
    );
}

function patient_portal_list_invoice_payments_cloud_first(int $invoiceId): array
{
    $db = Database::getInstance();
    $supabase = patient_portal_supabase_client();

    if ($supabase !== null) {
        try {
            $rows = $supabase->select('payments', [
                'select' => '*',
                'invoice_id' => 'eq.' . $invoiceId,
                'order' => 'payment_date.desc',
            ]);
            if (is_array($rows)) {
                return $rows;
            }
        } catch (Throwable $e) {
            error_log('Supabase payments fetch failed: ' . $e->getMessage());
        }
    }

    return $db->fetchAll(
        'SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date DESC',
        [$invoiceId],
        'i'
    );
}

function patient_portal_extract_missing_column_from_error(Throwable $e): ?string
{
    $msg = $e->getMessage();
    if (preg_match("/Could not find the '([A-Za-z0-9_]+)' column/i", $msg, $m)) {
        return strtolower((string) $m[1]);
    }

    return null;
}

function patient_portal_cloud_update_with_stripping(SupabaseAPI $supabase, string $table, array $payload, array $filter, int $maxRetries = 6): array
{
    $current = $payload;
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $supabase->update($table, $current, $filter);

            return $current;
        } catch (Throwable $e) {
            $missing = patient_portal_extract_missing_column_from_error($e);
            if ($missing === null || !array_key_exists($missing, $current)) {
                throw $e;
            }
            unset($current[$missing]);
        }
    }

    return $current;
}

function patient_portal_cloud_insert_with_stripping(SupabaseAPI $supabase, string $table, array $payload, int $maxRetries = 6): array
{
    $current = $payload;
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $response = $supabase->insert($table, $current);

            return is_array($response) ? $response : [];
        } catch (Throwable $e) {
            $missing = patient_portal_extract_missing_column_from_error($e);
            if ($missing === null || !array_key_exists($missing, $current)) {
                throw $e;
            }
            unset($current[$missing]);
        }
    }

    return $current;
}

function patient_portal_cloud_upsert_by_local_id_first(string $table, int $localId, array $payload, array $fallbackUniqueFilters = []): bool
{
    if ($localId <= 0) {
        throw new RuntimeException('Invalid local id for cloud upsert.');
    }

    $supabase = patient_portal_supabase_client();
    if ($supabase === null) {
        throw new RuntimeException('Cloud service unavailable.');
    }

    if ($table === 'patients' && array_key_exists('gender', $payload)) {
        $gender = trim((string) $payload['gender']);
        $validGenders = ['male', 'female', 'other'];
        if ($gender === '' || !in_array($gender, $validGenders, true)) {
            unset($payload['gender']);
        } else {
            $payload['gender'] = $gender;
        }
    }

    $payload['local_id'] = $localId;

    try {
        $rows = $supabase->select($table, [
            'select' => 'id',
            'local_id' => 'eq.' . $localId,
            'limit' => 1,
        ]);
        if (!empty($rows)) {
            patient_portal_cloud_update_with_stripping($supabase, $table, $payload, ['local_id' => $localId]);

            return true;
        }
    } catch (Throwable $e) {
        // local_id path may be unavailable on some cloud schemas, continue
    }

    foreach ($fallbackUniqueFilters as $col => $value) {
        if ((string) $value === '') {
            continue;
        }
        try {
            $rows = $supabase->select($table, [
                'select' => 'id',
                $col => 'eq.' . (string) $value,
                'limit' => 1,
            ]);
            if (!empty($rows)) {
                patient_portal_cloud_update_with_stripping($supabase, $table, $payload, [$col => (string) $value]);

                return true;
            }
        } catch (Throwable $e) {
            // try next fallback
        }
    }

    patient_portal_cloud_insert_with_stripping($supabase, $table, $payload);

    return true;
}

function patient_portal_cloud_insert_get_id(string $table, array $payload): ?int
{
    $supabase = patient_portal_supabase_client();
    if ($supabase === null) {
        throw new RuntimeException('Cloud service unavailable.');
    }

    if ($table === 'patients' && array_key_exists('gender', $payload)) {
        $gender = trim((string) $payload['gender']);
        $validGenders = ['male', 'female', 'other'];
        if ($gender === '' || !in_array($gender, $validGenders, true)) {
            unset($payload['gender']);
        } else {
            $payload['gender'] = $gender;
        }
    }

    $result = patient_portal_cloud_insert_with_stripping($supabase, $table, $payload);

    if (!is_array($result) || empty($result)) {
        throw new RuntimeException('Cloud insert failed - no result returned');
    }

    // Try to get the inserted record's ID
    $inserted = $result[0] ?? null;
    if (!is_array($inserted) || !isset($inserted['id'])) {
        throw new RuntimeException('Cloud insert failed - no ID in result');
    }

    return (int) $inserted['id'];
}

function patient_portal_delete_appointment_request_cloud_first(array $row, int $localId): bool
{
    $supabase = patient_portal_supabase_client();
    if ($supabase === null) {
        throw new RuntimeException('Cloud service unavailable.');
    }

    $filters = [];
    if ($localId > 0) {
        $filters[] = ['local_id' => $localId];
        $filters[] = ['id' => $localId];
    }
    if (!empty($row)) {
        $pid = (int) ($row['patient_id'] ?? 0);
        $did = (int) ($row['doctor_id'] ?? 0);
        $d = (string) ($row['requested_date'] ?? '');
        $t = (string) ($row['requested_time'] ?? '');
        if ($pid > 0 && $did > 0 && $d !== '' && $t !== '') {
            $filters[] = [
                'patient_id' => $pid,
                'doctor_id' => $did,
                'requested_date' => $d,
                'requested_time' => $t,
            ];
        }
    }

    foreach ($filters as $f) {
        try {
            $supabase->delete('appointment_requests', $f);

            return true;
        } catch (Throwable $e) {
            // try next filter
        }
    }

    throw new RuntimeException('Cloud cancel failed for appointment request.');
}

function patient_portal_set_referral_code_cloud_first(int $patientId, string $code): bool
{
    return patient_portal_cloud_upsert_by_local_id_first('patients', $patientId, ['referral_code' => $code], []);
}

function patient_portal_list_tooth_chart_cloud_first(int $patientId): array
{
    $db = Database::getInstance();
    $supabase = patient_portal_supabase_client();

    if ($supabase !== null) {
        try {
            $rows = $supabase->select('tooth_chart', [
                'select' => '*',
                'patient_id' => 'eq.' . $patientId,
            ]);
            if (is_array($rows)) {
                return $rows;
            }
        } catch (Throwable $e) {
            error_log('Supabase tooth chart fetch failed: ' . $e->getMessage());
        }
    }

    return $db->fetchAll(
        'SELECT * FROM tooth_chart WHERE patient_id = ?',
        [$patientId],
        'i'
    );
}
