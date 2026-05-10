<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sync_runtime.php';
// Global helper functions


function hasPermission($userId, $permissionKey) {
    global $db;
    static $cache = [];

    if (!is_object($db) && class_exists('Database')) {
        $db = Database::getInstance();
    }
    
    // Super admin bypass (users with is_admin = 1 have all permissions)
    if (class_exists('Auth') && Auth::isLoggedIn() && (int) $userId === (int) Auth::userId() && Auth::isAdmin()) {
        return true;
    }
    if (is_object($db)) {
        $user = $db->fetchOne("SELECT is_admin FROM users WHERE id = ?", [$userId], "i");
        if ($user && (int) ($user['is_admin'] ?? 0) === 1) {
            return true;
        }
    }

    if (!is_object($db)) {
        return false;
    }
    
    if (!isset($cache[$userId])) {
        $rows = $db->fetchAll("SELECT permission_key, value FROM user_permissions WHERE user_id = ?", [$userId], "i");
        $cache[$userId] = [];
        foreach ($rows as $row) {
            $cache[$userId][$row['permission_key']] = (int) ($row['value'] ?? 0) === 1;
        }
    }
    return isset($cache[$userId][$permissionKey]) && $cache[$userId][$permissionKey] === true;
}
function getAllPermissions() {
    return [
        // Settings tabs
        'access_settings_profile' => 'View/Edit Own Profile',
        'access_settings_users' => 'Manage Users (Add/Edit/Delete)',
        'access_settings_clinic' => 'Manage Clinic Information',
        'access_settings_points_management' => 'Manage Points Rules & Rewards',
        'access_settings_subscription_plans' => 'Manage Subscription Plans',
        
        // Main features
        'view_patients' => 'View Patients List',
        'edit_patients' => 'Add/Edit Patients',
        'view_appointments' => 'View Appointments',
        'manage_appointments' => 'Create/Edit/Cancel Appointments',
        'view_treatment_plans' => 'View Treatment Plans',
        'manage_treatment_plans' => 'Create/Edit Treatment Plans',
        'view_billing' => 'View Invoices/Billing',
        'manage_billing' => 'Create/Edit Invoices, Record Payments',
        'view_inventory' => 'View Inventory',
        'manage_inventory' => 'Add/Edit Inventory Items',
        'view_reports' => 'View Reports',
        'manage_queue' => 'Manage Waiting Queue',
        'manage_points' => 'Adjust Patient Points',
        'view_financial_dashboard' => 'View Financial Dashboard',
    ];
}


// Helper: call Supabase API
if (!function_exists('callSupabaseAPI')) {
    function callSupabaseAPI($endpoint, $data, $method = 'POST') {
        $ch = curl_init(SUPABASE_URL . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation'
        ]);
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_NOPROXY, '*');
        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method == 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code == 201 || $http_code == 200) {
            $result = json_decode($response, true);
            return $result[0]['id'] ?? null;
        }
        return null;
    }
}























// Generate absolute URL for internal paths (keeps views/pages consistent)
function url($path = '')
{
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    if ($path === '') {
        return $base ?: '/';
    }
    return $base . '/' . ltrim($path, '/');
}

// Convert a datetime string into a human readable "time ago" value
function timeAgo($datetime)
{
    if (empty($datetime)) {
        return 'Never';
    }

    $time = strtotime((string) $datetime);
    if ($time === false) {
        return 'Never';
    }

    $now = time();
    $diff = $now - $time;

    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }

    return date('M d, Y', $time);
}

/**
 * Generate a random password.
 * Used when creating patient login credentials.
 */
function generateRandomPassword($length = 8)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

// Optional DATE/DATETIME from forms: empty or MySQL zero-date → null for DB binding
function normalizePatientOptionalDate($value): ?string
{
    if ($value === null) {
        return null;
    }
    $v = trim((string) $value);
    if ($v === '' || $v === '0000-00-00' || $v === '0000-00-00 00:00:00' || preg_match('/^0000-\d{2}-\d{2}/', $v)) {
        return null;
    }
    return $v;
}

/** True when last_visit_date should be shown (not empty / zero-date). */
function patientHasLastVisitDate($value): bool
{
    return normalizePatientOptionalDate($value) !== null;
}

// Format date
function formatDate($date, $format = 'M d, Y')
{
    if (empty($date) || is_null($date)) {
        return 'Not set';
    }
    $s = trim((string) $date);
    if ($s === '' || $s === '0000-00-00' || $s === '0000-00-00 00:00:00' || preg_match('/^0000-\d{2}-\d{2}/', $s)) {
        return 'Not set';
    }
    try {
        $dateObj = new DateTime($date);
        $y = (int) $dateObj->format('Y');
        if ($y < 1) {
            return 'Not set';
        }
        return $dateObj->format($format);
    } catch (Exception $e) {
        return 'Not set';
    }
}

// Format time
function formatTime($time, $format = 'g:i A')
{
    if (empty($time) || is_null($time)) {
        return 'Not set';
    }
    try {
        $timeObj = new DateTime($time);
        return $timeObj->format($format);
    } catch (Exception $e) {
        return 'Not set';
    }
}

/**
 * Preferred date range label for weekly queue (patient portal flexibility days).
 */
function formatWeeklyPreferredRange(array $row): string
{
    $pref = $row['preferred_date'] ?? null;
    $flex = max(0, (int) ($row['date_flexibility_days'] ?? 0));
    if (!empty($pref)) {
        if ($flex > 0) {
            try {
                $c = new DateTimeImmutable($pref);
                $from = $c->modify('-' . $flex . ' days');
                $to = $c->modify('+' . $flex . ' days');

                return sprintf('from %s to %s', formatDate($from->format('Y-m-d')), formatDate($to->format('Y-m-d')));
            } catch (Exception $e) {
                // Fall back to single preferred date if range formatting fails.
            }
        }

        return formatDate($pref);
    }

    return (string) ($row['preferred_day'] ?? '—');
}

// Format currency
function formatCurrency($amount)
{
    return '$' . number_format($amount, 2);
}

// Get patient name by ID
function getPatientName($patientId)
{
    $db = Database::getInstance();
    $patient = $db->fetchOne(
        "SELECT full_name FROM patients WHERE id = ?",
        [$patientId],
        "i"
    );
    return $patient ? $patient['full_name'] : 'Unknown';
}

// Get patient name by username
function getPatientByUsername($username)
{
    $db = Database::getInstance();
    $patient = $db->fetchOne(
        "SELECT * FROM users WHERE username = ?",
        [$username],
        "s"
    );
    return $patient;
}

// Get doctor name by ID
function getDoctorName($doctorId)
{
    $db = Database::getInstance();
    $doctor = $db->fetchOne(
        "SELECT full_name FROM users WHERE id = ? AND role = 'doctor'",
        [$doctorId],
        "i"
    );
    return $doctor ? $doctor['full_name'] : 'Unknown';
}

// Get appointment status badge
function getStatusBadge($status)
{
    $colors = [
        'scheduled' => 'primary',
        'checked-in' => 'info',
        'in-treatment' => 'warning',
        'completed' => 'success',
        'cancelled' => 'danger',
        'no-show' => 'secondary',
        'follow-up' => 'dark'
    ];

    $color = $colors[$status] ?? 'secondary';
    return "<span class='badge bg-{$color}'>{$status}</span>";
}

// Log audit action
function logAction($action, $table, $recordId, $oldValues = null, $newValues = null)
{
    $db = Database::getInstance();
    $auditId = $db->insert(
        "INSERT INTO audit_log (user_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            Auth::userId(),
            $action,
            $table,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ],
        "ississss"
    );

    if ($auditId) {
        sync_push_row_now('audit_log', (int) $auditId);
    }
}

/**
 * Queue a local hard-delete so sync_to_supabase.php can delete the matching cloud row.
 */
function queueCloudDeletion(string $tableName, int $localId, string $matchColumn = 'local_id'): bool
{
    if ($localId <= 0 || $tableName === '' || $matchColumn === '') {
        return false;
    }

    $db = Database::getInstance();
    $conn = $db->getConnection();
    static $queueTableEnsured = false;

    if (!$queueTableEnsured) {
        $conn->query(
            "CREATE TABLE IF NOT EXISTS sync_delete_queue (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                table_name VARCHAR(64) NOT NULL,
                local_id BIGINT NOT NULL,
                match_column VARCHAR(64) NOT NULL DEFAULT 'local_id',
                status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
                last_attempt DATETIME NULL,
                error_text TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_sync_delete_queue_status (status),
                KEY idx_sync_delete_queue_table (table_name, local_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        );
        $queueTableEnsured = true;
    }

    try {
        $db->insert(
            'INSERT INTO sync_delete_queue (table_name, local_id, match_column, status) VALUES (?, ?, ?, \'pending\')',
            [$tableName, $localId, $matchColumn],
            'sis'
        );
        if (function_exists('sync_record_runtime_status')) {
            sync_record_runtime_status($db, $tableName, $localId, 'local_to_cloud_delete', 'delete', 'pending', 'Delete queued', null, false, false);
        }
        sync_process_delete_queue_now(1);

        return true;
    } catch (Throwable $e) {
        error_log('queueCloudDeletion failed: ' . $e->getMessage());

        return false;
    }
}

function syncAppointmentRequestCloudDeletion(array $requestRow, int $localId): void
{
    $cloudId = (int) ($requestRow['cloud_id'] ?? 0);
    if ($cloudId > 0) {
        queueCloudDeletion('appointment_requests', $cloudId, 'id');
        return;
    }

    queueCloudDeletion('appointment_requests', $localId, 'local_id');

    $supabase = function_exists('sync_supabase_client') ? sync_supabase_client() : null;
    if ($supabase === null) {
        return;
    }

    $patientId = (int) ($requestRow['patient_id'] ?? 0);
    $doctorId = (int) ($requestRow['doctor_id'] ?? 0);
    $date = (string) ($requestRow['requested_date'] ?? '');
    $time = (string) ($requestRow['requested_time'] ?? '');
    if ($patientId <= 0 || $doctorId <= 0 || $date === '' || $time === '') {
        return;
    }

    if (strlen($time) === 5) {
        $time .= ':00';
    }

    try {
        $rows = $supabase->select('appointment_requests', [
            'select' => 'id',
            'patient_id' => 'eq.' . $patientId,
            'doctor_id' => 'eq.' . $doctorId,
            'requested_date' => 'eq.' . $date,
            'requested_time' => 'eq.' . $time,
            'limit' => 10,
        ]);

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $supabase->delete('appointment_requests', ['id' => $id]);
            }
        }
    } catch (Throwable $e) {
        error_log('syncAppointmentRequestCloudDeletion fallback failed: ' . $e->getMessage());
    }
}

// Upload file
function uploadFile($file, $targetDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'])
{
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB max
        return ['success' => false, 'message' => 'File too large'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $filepath];
    }

    return ['success' => false, 'message' => 'Upload failed'];
}

// Send notification
function sendNotification($userId, $type, $title, $message, $via = 'in-app', $relatedAppointmentId = null, $relatedInvoiceId = null)
{
    $db = Database::getInstance();
    $hasSyncStatus = function_exists('dbColumnExists') && dbColumnExists('notifications', 'sync_status');
    $sql = "INSERT INTO notifications (user_id, type, title, message, sent_via, related_appointment_id, related_invoice_id"
        . ($hasSyncStatus ? ", sync_status" : "")
        . ") VALUES (?, ?, ?, ?, ?, ?, ?"
        . ($hasSyncStatus ? ", 'pending'" : "")
        . ")";
    $notificationId = (int) $db->insert(
        $sql,
        [$userId, $type, $title, $message, $via, $relatedAppointmentId, $relatedInvoiceId],
        "issssii"
    );

    if ($notificationId > 0 && function_exists('sync_push_row_now')) {
        sync_push_row_now('notifications', $notificationId);
    }

    return $notificationId;
}

// Generate invoice number
function generateInvoiceNumber()
{
    return 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
}
// Translation function
function __($key, $default = '')
{
    static $translations = null;

    if ($translations === null) {
        $lang = $_SESSION['lang'] ?? 'en'; // default to English
        $langFile = __DIR__ . "/../languages/{$lang}.php";
        if (file_exists($langFile)) {
            $translations = include $langFile;
        } else {
            $translations = [];
        }
    }

    return $translations[$key] ?? ($default ?: $key);
}

// Set language
function setLanguage($lang)
{
    if (in_array($lang, ['en', 'ar', 'fr'])) {
        $_SESSION['lang'] = $lang;
        return true;
    }
    return false;
}

// Get current language
function getLanguage()
{
    return $_SESSION['lang'] ?? 'en';
}

/** True if the current database has the given table (information_schema; cached per request). */
function dbTableExists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $db = Database::getInstance();
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table],
        's'
    );
    $cache[$table] = !empty($row['c']);

    return $cache[$table];
}

/**
 * Parse patients.medical_history (structured JSON from add.php / safety check).
 *
 * @return array{conditions: string[], notes: string}
 */
function parsePatientMedicalHistoryStructured($medicalHistory): array
{
    $raw = $medicalHistory === null ? '' : (string) $medicalHistory;
    $raw = trim($raw);
    if ($raw === '') {
        return ['conditions' => [], 'notes' => ''];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return ['conditions' => [], 'notes' => $raw];
    }

    $conds = $decoded['conditions'] ?? [];
    if (!is_array($conds)) {
        $conds = [];
    }
    $conds = array_values(array_unique(array_filter(array_map('strval', $conds))));
    $notes = trim((string) ($decoded['notes'] ?? ''));

    return ['conditions' => $conds, 'notes' => $notes];
}

/**
 * Plain-text medical_history for staff views: conditions joined with ", "; notes on a following block if present.
 */
function formatPatientMedicalHistoryDisplay($medicalHistory): string
{
    $mh = parsePatientMedicalHistoryStructured($medicalHistory);
    $lines = [];

    if ($mh['conditions'] !== []) {
        $lines[] = implode(', ', $mh['conditions']);
    }
    if ($mh['notes'] !== '') {
        $lines[] = $mh['notes'];
    }

    return implode("\n\n", $lines);
}

/**
 * Parse patients.current_medications: new format is JSON array, legacy is free text.
 *
 * @return string[]
 */
function parsePatientMedicationsList($currentMedications): array
{
    $raw = $currentMedications === null ? '' : (string) $currentMedications;
    $raw = trim($raw);
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        return array_values(array_unique(array_filter(array_map('strval', $decoded))));
    }

    // Legacy free text: split by newlines / commas
    $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
    $parts = array_map('trim', $parts);
    return array_values(array_unique(array_filter($parts, fn ($p) => $p !== '')));
}

/** True if the patient has allergies recorded (yes/no/legacy). */
function normalizePatientAllergiesFlag($allergies): bool
{
    $raw = strtolower(trim((string) ($allergies ?? '')));
    if ($raw === '' || $raw === 'no' || $raw === 'none' || $raw === '0') {
        return false;
    }
    if ($raw === 'yes' || $raw === '1' || $raw === 'true') {
        return true;
    }
    // Legacy text entry -> treat as "has allergies"
    return true;
}

/**
 * Build caution summary lines:
 * - Patient on [MedicationName]
 * - Patient has [DiseaseName].
 * - Patient has Allergies
 */
function buildPatientCautionSummary(array $patientRow): string
{
    $mh = parsePatientMedicalHistoryStructured($patientRow['medical_history'] ?? null);
    $meds = parsePatientMedicationsList($patientRow['current_medications'] ?? null);
    $hasAllergies = normalizePatientAllergiesFlag($patientRow['allergies'] ?? null);

    $parts = [];
    foreach ($meds as $m) {
        $parts[] = 'Patient on ' . $m;
    }
    foreach ($mh['conditions'] as $c) {
        $parts[] = 'Patient has ' . $c . '.';
    }
    if ($hasAllergies) {
        $parts[] = 'Patient has Allergies';
    }

    return implode(' ', $parts);
}

/** High-risk disease labels → red badge (matches patients/add + safety check wording variants). */
function cautionDiseaseIsHighRisk(string $label): bool
{
    $n = mb_strtolower(trim($label), 'UTF-8');
    $high = [
        'cardiovascular diseases',
        'cardiovascular disease',
        'immunosuppression',
        'autoimmune diseases',
        'autoimmune disease',
    ];

    return in_array($n, $high, true);
}

/** Medication badge: Anticoagulants & Chemotherapy → danger; Steroids → warning. */
function cautionMedicationBadgeVariant(string $med): string
{
    $n = mb_strtolower(trim($med), 'UTF-8');
    if (in_array($n, ['anticoagulants', 'chemotherapy'], true)) {
        return 'danger';
    }

    return 'warning';
}

/** Disease badge: high-risk → danger; other known conditions → warning. */
function cautionDiseaseBadgeVariant(string $disease): string
{
    return cautionDiseaseIsHighRisk($disease) ? 'danger' : 'warning';
}

/**
 * Renders multiple caution badges (medications, conditions, allergies). Empty → green "none".
 *
 * @param array{medical_history?: mixed, current_medications?: mixed, allergies?: mixed} $patientRow
 */
function renderCautionBadgesHtml(array $patientRow): string
{
    $mh = parsePatientMedicalHistoryStructured($patientRow['medical_history'] ?? null);
    $meds = parsePatientMedicationsList($patientRow['current_medications'] ?? null);
    $hasAllergies = normalizePatientAllergiesFlag($patientRow['allergies'] ?? null);

    $items = [];
    foreach ($meds as $m) {
        $items[] = [
            'text' => 'Patient on ' . $m,
            'variant' => cautionMedicationBadgeVariant($m),
        ];
    }
    foreach ($mh['conditions'] as $c) {
        $items[] = [
            'text' => 'Patient has ' . $c . '.',
            'variant' => cautionDiseaseBadgeVariant($c),
        ];
    }
    if ($hasAllergies) {
        $items[] = ['text' => 'Patient has Allergies', 'variant' => 'warning'];
    }

    if ($items === []) {
        return '<span class="badge bg-success">none</span>';
    }

    $html = '<div class="d-flex flex-wrap gap-1 align-items-start caution-badges-wrap">';
    foreach ($items as $it) {
        $safe = htmlspecialchars($it['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $v = $it['variant'];
        $cls = $v === 'danger' ? 'bg-danger' : 'bg-warning text-dark';
        $html .= "<span class=\"badge {$cls} text-wrap\" style=\"max-width:100%;white-space:normal;font-weight:500;\">{$safe}</span>";
    }
    $html .= '</div>';

    return $html;
}

/** @deprecated Use renderCautionBadgesHtml with a patient row; kept for one-off plain-text lines. */
function renderCautionBadgeHtml(string $caution): string
{
    $t = trim($caution);
    if ($t === '') {
        return '<span class="badge bg-success">none</span>';
    }
    $safe = htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return '<span class="badge bg-warning text-dark text-wrap" style="max-width: 260px; white-space: normal;">' . $safe . '</span>';
}

/** True if the current database has the given column (cached per request). */
function dbColumnExists(string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $db = Database::getInstance();
    $row = $db->fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column],
        'ss'
    );
    $cache[$key] = !empty($row['c']);

    return $cache[$key];
}

function getPatientIdFromUserId($userId)
{
    $db = Database::getInstance();
    $patient = $db->fetchOne("SELECT id FROM patients WHERE user_id = ?", [$userId], "i");
    return $patient ? $patient['id'] : null;
}

/**
 * Single clinic setting value (global helper for patient booking, etc.).
 */
function getClinicSettingValue(Database $db, string $key, string $default = ''): string
{
    $row = $db->fetchOne('SELECT setting_value FROM clinic_settings WHERE setting_key = ?', [$key]);
    if ($row && isset($row['setting_value']) && $row['setting_value'] !== null && $row['setting_value'] !== '') {
        return (string) $row['setting_value'];
    }

    return $default;
}

/**
 * Default per-day booking config: Mon–Fri 09:00–17:00, Sat 09:00–13:00, Sun closed.
 *
 * @return array{by_dow: array<int, array{open: string, close: string}|null>}
 */
function defaultBookingCalendarHours(): array
{
    $by = [];
    for ($i = 1; $i <= 7; $i++) {
        $by[$i] = null;
    }
    for ($i = 1; $i <= 5; $i++) {
        $by[$i] = ['open' => '09:00', 'close' => '17:00'];
    }
    $by[6] = ['open' => '09:00', 'close' => '13:00'];

    return ['by_dow' => $by];
}

/**
 * @param array<string, mixed> $legacy weekday / saturday / sunday shape
 * @return array{by_dow: array<int, array{open: string, close: string}|null>}
 */
function bookingHoursLegacyShapeToByDow(array $legacy): array
{
    $by = [];
    for ($i = 1; $i <= 7; $i++) {
        $by[$i] = null;
    }
    $wk = $legacy['weekday'] ?? null;
    if (is_array($wk) && isset($wk['open'], $wk['close'])) {
        $o = normalizeBookingTimeInput((string) $wk['open']);
        $c = normalizeBookingTimeInput((string) $wk['close']);
        if ($o !== '' && $c !== '' && bookingOpenCloseValid($o, $c)) {
            for ($i = 1; $i <= 5; $i++) {
                $by[$i] = ['open' => $o, 'close' => $c];
            }
        }
    }
    $sat = $legacy['saturday'] ?? null;
    if (is_array($sat) && isset($sat['open'], $sat['close'])) {
        $o = normalizeBookingTimeInput((string) $sat['open']);
        $c = normalizeBookingTimeInput((string) $sat['close']);
        if ($o !== '' && $c !== '' && bookingOpenCloseValid($o, $c)) {
            $by[6] = ['open' => $o, 'close' => $c];
        }
    }
    $sun = $legacy['sunday'] ?? null;
    if (is_array($sun) && isset($sun['open'], $sun['close'])) {
        $o = normalizeBookingTimeInput((string) $sun['open']);
        $c = normalizeBookingTimeInput((string) $sun['close']);
        if ($o !== '' && $c !== '' && bookingOpenCloseValid($o, $c)) {
            $by[7] = ['open' => $o, 'close' => $c];
        }
    }

    return ['by_dow' => $by];
}

/**
 * Normalize one day's band or null.
 *
 * @param mixed $cell
 * @return array{open: string, close: string}|null
 */
function bookingHoursNormalizeDayCell($cell): ?array
{
    if ($cell === null || $cell === false) {
        return null;
    }
    if (!is_array($cell) || !isset($cell['open'], $cell['close'])) {
        return null;
    }
    $o = normalizeBookingTimeInput((string) $cell['open']);
    $c = normalizeBookingTimeInput((string) $cell['close']);
    if ($o === '' || $c === '' || !bookingOpenCloseValid($o, $c)) {
        return null;
    }

    return ['open' => $o, 'close' => $c];
}

/**
 * Merge decoded JSON into per-day config (supports by_dow + legacy weekday/sat/sun).
 *
 * @param array<string,mixed>|null $decoded
 * @return array{by_dow: array<int, array{open: string, close: string}|null>}
 */
function mergeBookingHoursFromDecoded(?array $decoded): array
{
    $defaults = defaultBookingCalendarHours();
    $by = $defaults['by_dow'];
    if (!is_array($decoded)) {
        return ['by_dow' => $by];
    }

    if (isset($decoded['by_dow']) && is_array($decoded['by_dow'])) {
        foreach ([1, 2, 3, 4, 5, 6, 7] as $d) {
            $raw = $decoded['by_dow'][$d] ?? $decoded['by_dow'][(string) $d] ?? null;
            $norm = bookingHoursNormalizeDayCell($raw);
            $by[$d] = $norm;
        }

        return ['by_dow' => $by];
    }

    $legacy = [
        'weekday' => ['open' => '09:00', 'close' => '17:00'],
        'saturday' => ['open' => '09:00', 'close' => '13:00'],
        'sunday' => null,
    ];
    foreach (['weekday', 'saturday', 'sunday'] as $k) {
        if (!array_key_exists($k, $decoded)) {
            continue;
        }
        if ($decoded[$k] === null || $decoded[$k] === false) {
            $legacy[$k] = null;
            continue;
        }
        if (is_array($decoded[$k]) && isset($decoded[$k]['open'], $decoded[$k]['close'])) {
            $legacy[$k] = [
                'open' => (string) $decoded[$k]['open'],
                'close' => (string) $decoded[$k]['close'],
            ];
        }
    }

    return bookingHoursLegacyShapeToByDow($legacy);
}

function decodeBookingHoursJsonString(string $json): array
{
    if ($json === '') {
        return defaultBookingCalendarHours();
    }
    $decoded = json_decode($json, true);

    return mergeBookingHoursFromDecoded(is_array($decoded) ? $decoded : null);
}

/**
 * Global slot length for all booking grids (clinic setting patient_slot_minutes).
 */
function getClinicSlotMinutes(Database $db): int
{
    $slot = (int) getClinicSettingValue($db, 'patient_slot_minutes', '45');
    if ($slot < 10 || $slot > 120) {
        $slot = 30;
    }

    return $slot;
}

/**
 * Weekly open/close bands for a doctor. Falls back to legacy clinic_hours_json when the doctor
 * has no booking_hours_json yet (or column missing). Used by dashboard, patient queue, and APIs.
 */
function getDoctorBookingHours(Database $db, int $doctorId): array
{
    if ($doctorId <= 0) {
        return decodeBookingHoursJsonString(getClinicSettingValue($db, 'clinic_hours_json', ''));
    }
    if (dbColumnExists('users', 'booking_hours_json')) {
        $row = $db->fetchOne(
            "SELECT booking_hours_json FROM users WHERE id = ? AND role = 'doctor' LIMIT 1",
            [$doctorId],
            'i'
        );
        $jh = trim((string) ($row['booking_hours_json'] ?? ''));
        if ($jh !== '') {
            return decodeBookingHoursJsonString($jh);
        }
    }

    return decodeBookingHoursJsonString(getClinicSettingValue($db, 'clinic_hours_json', ''));
}

/**
 * Normalize H:i or H:i:s to H:i for comparison.
 */
function normalizeBookingTimeInput(string $t): string
{
    $t = trim($t);
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $t, $m)) {
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }

    return '';
}

/**
 * @return array{0: int, 1: int}|null minutes from midnight
 */
function bookingTimeToMinutesPair(string $t): ?array
{
    $norm = normalizeBookingTimeInput($t);
    if ($norm === '' || !preg_match('/^(\d{2}):(\d{2})$/', $norm, $m)) {
        return null;
    }

    return [(int) $m[1], (int) $m[2]];
}

function bookingOpenCloseValid(string $open, string $close): bool
{
    $o = bookingTimeToMinutesPair($open);
    $c = bookingTimeToMinutesPair($close);
    if ($o === null || $c === null) {
        return false;
    }
    $oMin = $o[0] * 60 + $o[1];
    $cMin = $c[0] * 60 + $c[1];

    return $cMin > $oMin;
}

/**
 * Build JSON for users.booking_hours_json from POST (per-day: {$prefix}day_{1..7}_closed/open/close).
 *
 * @throws InvalidArgumentException
 */
function buildBookingHoursJsonFromPost(array $post, string $prefix = 'wh_'): string
{
    $by = [];
    $anyOpen = false;
    for ($d = 1; $d <= 7; $d++) {
        $closedKey = $prefix . 'day_' . $d . '_closed';
        if (!empty($post[$closedKey])) {
            $by[$d] = null;
            continue;
        }
        $o = normalizeBookingTimeInput((string) ($post[$prefix . 'day_' . $d . '_open'] ?? ''));
        $c = normalizeBookingTimeInput((string) ($post[$prefix . 'day_' . $d . '_close'] ?? ''));
        if ($o === '' || $c === '') {
            throw new InvalidArgumentException('Open and close are required for each day that is not marked closed.');
        }
        if (!bookingOpenCloseValid($o, $c)) {
            throw new InvalidArgumentException('Invalid hours for day ' . $d . ' (open must be before close).');
        }
        $by[$d] = ['open' => $o, 'close' => $c];
        $anyOpen = true;
    }
    if (!$anyOpen) {
        throw new InvalidArgumentException('Select at least one working day with open hours.');
    }

    $out = ['by_dow' => $by];
    $enc = json_encode($out, JSON_UNESCAPED_UNICODE);
    if ($enc === false) {
        throw new InvalidArgumentException('Could not save working hours.');
    }

    return $enc;
}

/**
 * Config for patient weekly booking: global slot length + hour bands (per doctor when doctorId set).
 * Legacy clinic_hours_json is used only as fallback when a doctor has no booking_hours_json.
 *
 * @param int|null $doctorId When set (>0), hours come from that doctor; otherwise legacy clinic JSON only.
 */
function getClinicBookingCalendarConfig(Database $db, ?int $doctorId = null): array
{
    $slot = getClinicSlotMinutes($db);
    $hours = ($doctorId !== null && $doctorId > 0)
        ? getDoctorBookingHours($db, $doctorId)
        : decodeBookingHoursJsonString(getClinicSettingValue($db, 'clinic_hours_json', ''));

    return [
        'slot_minutes' => $slot,
        'hours' => $hours,
    ];
}

/**
 * Map PHP date('N') 1=Mon..7=Sun to hours band from clinic config (by_dow) or legacy weekday/sat/sun.
 */
function clinicHoursBandForWeekdayN(int $n, array $hours): ?array
{
    if (isset($hours['by_dow']) && is_array($hours['by_dow'])) {
        $raw = $hours['by_dow'][$n] ?? $hours['by_dow'][(string) $n] ?? null;

        return bookingHoursNormalizeDayCell($raw);
    }
    if ($n >= 1 && $n <= 5) {
        return bookingHoursNormalizeDayCell($hours['weekday'] ?? null);
    }
    if ($n === 6) {
        return bookingHoursNormalizeDayCell($hours['saturday'] ?? null);
    }

    return bookingHoursNormalizeDayCell($hours['sunday'] ?? null);
}
/**
 * Create password_resets if missing (common when DB was created before this table was added).
 */
function ensurePasswordResetsTableExists(): void
{
    $conn = Database::getInstance()->getConnection();
    $sql = 'CREATE TABLE IF NOT EXISTS password_resets (
        id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        token VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        UNIQUE KEY uk_password_resets_token (token),
        KEY idx_password_resets_patient (patient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    if (!$conn->query($sql)) {
        throw new RuntimeException('password_resets table setup failed: ' . $conn->error);
    }
}

/**
 * Store a reset token. Uses mysqli bind with real variables (reliable with PHP 8 + db helper spread).
 */
function addResetToken($patientId, $token, $expiresAt): void
{
    $conn = Database::getInstance()->getConnection();
    $stmt = $conn->prepare('INSERT INTO password_resets (patient_id, token, expires_at) VALUES (?, ?, ?)');
    if ($stmt === false) {
        throw new RuntimeException('password_resets prepare failed: ' . $conn->error);
    }
    $pid = (int) $patientId;
    $tok = (string) $token;
    $exp = (string) $expiresAt;
    $stmt->bind_param('iss', $pid, $tok, $exp);
    if (!$stmt->execute()) {
        throw new RuntimeException('password_resets insert failed: ' . $stmt->error);
    }
    $stmt->close();
}

function buildPasswordResetLink(string $token): string
{
    $baseUrl = defined('PUBLIC_SITE_URL') ? trim((string) PUBLIC_SITE_URL) : '';
    if ($baseUrl === '') {
        // Prefer configured SITE_URL so the link includes /Dental
        $baseUrl = defined('SITE_URL') ? trim((string) SITE_URL) : '';
        if ($baseUrl === '') {
            $baseUrl = url('');
        }
    }
    $baseUrl = rtrim($baseUrl, '/');
    return $baseUrl . '/reset_pass.php?token=' . urlencode($token);
}

function getPatientWhatsappPhone(array $user): string
{
    return (string) ($user['phone'] ?? $user['mobile'] ?? $user['whatsapp_phone'] ?? '');
}

function normalizeWhatsappPhoneDigits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function getPatientWhatsappDigitsByPatientId(Database $db, int $patientId): string
{
    $row = $db->fetchOne('SELECT phone FROM patients WHERE id = ?', [$patientId], 'i');
    if (!$row || $row['phone'] === null || $row['phone'] === '') {
        return '';
    }

    return normalizeWhatsappPhoneDigits((string) $row['phone']);
}

function buildAppointmentRequestAcceptedWhatsappMessage(
    string $patientFirstName,
    string $doctorName,
    string $dateDisplay,
    string $timeDisplay,
    int $durationMin,
    string $treatment
): string {
    $msg = 'Hello ' . $patientFirstName . ",\n\n";
    $msg .= 'Great news — Dr. ' . $doctorName . " has *accepted* your appointment request.\n\n";
    $msg .= "*Details*\n";
    $msg .= 'Date: ' . $dateDisplay . "\n";
    $msg .= 'Time: ' . $timeDisplay . "\n";
    $msg .= 'Length: ' . $durationMin . " minutes\n";
    $msg .= 'Visit type: ' . $treatment . "\n\n";
    $msg .= 'We look forward to seeing you. If you need to change this visit, please contact the clinic.';
    return $msg;
}

function buildAppointmentRequestDeclinedWhatsappMessage(
    string $patientFirstName,
    string $doctorName,
    string $dateDisplay,
    string $timeDisplay
): string {
    $msg = 'Hello ' . $patientFirstName . ",\n\n";
    $msg .= 'Thank you for your booking request with Dr. ' . $doctorName . ".\n\n";
    $msg .= "Unfortunately we are unable to confirm an appointment for *" . $dateDisplay . '* at *' . $timeDisplay . "* at this time.\n\n";
    $msg .= 'Please try another time in the patient portal or call the clinic — we would be happy to help you find a suitable visit.';
    return $msg;
}

function buildPasswordResetWhatsappMessage(string $resetLink): string
{
    $message = "DentAssist Password Reset\n\n";
    $message .= "Click this link to reset your password:\n";
    $message .= trim($resetLink) . "\n\n";
    $message .= "This link will expire in 1 hour.";
    return $message;
}

/**
 * Send WhatsApp via the local Node service (assets/js/whatsapp/send.js — port 3210).
 * Run: npm start  (from project root)
 */
function sendWhatsapp(string $phone, string $message): array
{
    $endpoint = defined('WHATSAPP_NODE_SEND_URL')
        ? (string) WHATSAPP_NODE_SEND_URL
        : 'http://127.0.0.1:3210/send';
    $payload = json_encode([
        'phone' => $phone,
        'message' => $message,
    ]);

    if ($payload === false) {
        return ['ok' => false, 'error' => 'Failed to build local WhatsApp payload.'];
    }

    $ch = curl_init($endpoint);
    if ($ch === false) {
        return ['ok' => false, 'error' => 'Failed to initialize local WhatsApp request.'];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $raw === null || $raw === '') {
        $hint = $curlError !== '' ? (' ' . $curlError) : '';
        return [
            'ok' => false,
            'error' => 'WhatsApp Node server is not reachable (http://127.0.0.1:3210/send).' . $hint
                . ' Run: npm start (from the Dental project folder).',
        ];
    }

    $decoded = json_decode($raw, true);
    if ($httpCode >= 400) {
        $err = is_array($decoded) ? (string) ($decoded['error'] ?? '') : '';
        if ($err === '') {
            $err = $curlError !== '' ? $curlError : ('HTTP ' . $httpCode);
        }
        return ['ok' => false, 'error' => 'Local WhatsApp server error: ' . $err];
    }

    if (!is_array($decoded) || !($decoded['ok'] ?? false)) {
        return ['ok' => false, 'error' => 'WhatsApp Node server returned an invalid response. Is send.js running?'];
    }

    return ['ok' => true, 'error' => null];
}

function getPasswordResetByToken(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $db = Database::getInstance();
    $row = $db->fetchOne(
        "SELECT patient_id, token, created_at, expires_at FROM password_resets WHERE token = ? LIMIT 1",
        [$token],
        "s"
    );

    return $row ?: null;
}

function consumePasswordResetToken(string $token): void
{
    $db = Database::getInstance();
    $db->execute("DELETE FROM password_resets WHERE token = ? LIMIT 1", [$token], "s");
}

function getUserIdFromPatientId(int $patientId): ?int
{
    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT user_id FROM patients WHERE id = ? LIMIT 1", [$patientId], "i");
    if (!$row || !isset($row['user_id']) || $row['user_id'] === null) {
        return null;
    }
    return (int) $row['user_id'];
}

/**
 * Resolve post-treatment instruction text from treatment_instructions for this appointment's treatment.
 * Matches treatment_type (case-insensitive); if no exact row, tries longest treatment_type contained in $treatmentType.
 */
function getTreatmentInstructionsForAppointment(string $treatmentType): ?string
{
    $t = trim($treatmentType);
    if ($t === '') {
        return null;
    }

    $db = Database::getInstance();
    $row = $db->fetchOne(
        'SELECT instructions FROM treatment_instructions
         WHERE LOWER(TRIM(treatment_type)) = LOWER(?)
         LIMIT 1',
        [$t],
        's'
    );
    if ($row && !empty($row['instructions'])) {
        return (string) $row['instructions'];
    }

    $row = $db->fetchOne(
        'SELECT instructions FROM treatment_instructions
         WHERE ? LIKE CONCAT(\'%\', treatment_type, \'%\')
         ORDER BY CHAR_LENGTH(treatment_type) DESC
         LIMIT 1',
        [$t],
        's'
    );
    if ($row && !empty($row['instructions'])) {
        return (string) $row['instructions'];
    }

    $row = $db->fetchOne(
        'SELECT instructions FROM treatment_instructions ORDER BY id ASC LIMIT 1',
        [],
        ''
    );
    if ($row && !empty($row['instructions'])) {
        return (string) $row['instructions'];
    }

    return null;
}

/**
 * Instructions text for post-treatment WhatsApp only when the appointment's treatment matches
 * a row in treatment_instructions (exact case-insensitive match, then longest substring match).
 * No generic or default-row fallback — used so we do not send WhatsApp without a real template.
 */
function getTreatmentInstructionsForPostTreatmentWhatsapp(string $treatmentType): ?string
{
    $t = trim($treatmentType);
    if ($t === '') {
        return null;
    }

    $db = Database::getInstance();
    $row = $db->fetchOne(
        'SELECT instructions FROM treatment_instructions
         WHERE LOWER(TRIM(treatment_type)) = LOWER(?)
         LIMIT 1',
        [$t],
        's'
    );
    if ($row && trim((string) ($row['instructions'] ?? '')) !== '') {
        return (string) $row['instructions'];
    }

    $row = $db->fetchOne(
        'SELECT instructions FROM treatment_instructions
         WHERE ? LIKE CONCAT(\'%\', treatment_type, \'%\')
         ORDER BY CHAR_LENGTH(treatment_type) DESC
         LIMIT 1',
        [$t],
        's'
    );
    if ($row && trim((string) ($row['instructions'] ?? '')) !== '') {
        return (string) $row['instructions'];
    }

    return null;
}

/**
 * Send WhatsApp with post-treatment instructions when an appointment is marked completed.
 * Uses the local Node WhatsApp server only (send.js on port 3210).
 *
 * @return array{ok:bool,reason:string,message?:string,error?:string,channel?:string,skipped_whatsapp?:bool}
 */
function notifyPatientPostTreatmentInstructionsOnCompleted(int $appointmentId): array
{
    $db = Database::getInstance();
    $apt = $db->fetchOne(
        'SELECT a.treatment_type, p.full_name, p.phone
         FROM appointments a
         JOIN patients p ON a.patient_id = p.id
         WHERE a.id = ? AND a.status = \'completed\'',
        [$appointmentId],
        'i'
    );

    if (!$apt) {
        return [
            'ok' => false,
            'reason' => 'appointment_not_found_or_not_completed',
            'message' => 'Could not load completed appointment for WhatsApp.',
            'error' => 'Appointment not found or not marked completed.',
        ];
    }

    $phone = trim((string) ($apt['phone'] ?? ''));
    if ($phone === '') {
        error_log('Post-treatment WhatsApp skipped (no phone) for appointment id ' . $appointmentId);

        return [
            'ok' => false,
            'reason' => 'no_phone',
            'message' => 'Patient has no phone number on file — WhatsApp not sent.',
            'error' => 'Patient has no phone number on file.',
        ];
    }

    $treatmentType = (string) ($apt['treatment_type'] ?? '');
    $instructions = getTreatmentInstructionsForPostTreatmentWhatsapp($treatmentType);
    if ($instructions === null || trim($instructions) === '') {
        error_log('Post-treatment WhatsApp skipped: no treatment_instructions row for "' . $treatmentType . '" (appointment ' . $appointmentId . ').');

        return [
            'ok' => true,
            'reason' => 'no_matching_treatment_instructions',
            'skipped_whatsapp' => true,
            'message' => 'No matching treatment instructions in the database — WhatsApp not sent.',
        ];
    }

    $patientName = (string) ($apt['full_name'] ?? 'Patient');
    $clinicName = defined('SITE_NAME') ? trim(strip_tags((string) SITE_NAME)) : 'Dental Clinic';
    if ($clinicName === '') {
        $clinicName = 'Dental Clinic';
    }

    $message = "Dear {$patientName},\n\n"
        . "Your visit for {$treatmentType} is complete.\n\n"
        . "Post-treatment instructions:\n\n"
        . $instructions . "\n\n"
        . 'If you have concerns, please contact us.' . "\n\n"
        . "— {$clinicName}";

    $sent = sendWhatsapp($phone, $message);
    if ($sent['ok']) {
        return [
            'ok' => true,
            'reason' => 'sent',
            'channel' => 'node',
            'message' => 'Post-treatment instructions sent via WhatsApp (local Node server / send.js).',
            'error' => null,
        ];
    }

    $err = (string) ($sent['error'] ?? 'Unknown error');
    error_log('Post-treatment WhatsApp failed for appointment ' . $appointmentId . ': ' . $err);

    return [
        'ok' => false,
        'reason' => 'send_failed',
        'channel' => 'node',
        'message' => 'Could not send post-treatment WhatsApp.',
        'error' => $err,
    ];

    /**
 * Get all active subscription plans
 * @return array
 */
function getSubscriptionPlans() {
    global $db;
    return $db->fetchAll(
        "SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY display_order, monthly_price"
    );
}

/**
 * Get a single subscription plan by key
 * @param string $planKey
 * @return array|null
 */
function getSubscriptionPlan($planKey) {
    global $db;
    return $db->fetchOne("SELECT * FROM subscription_plans WHERE plan_key = ? AND is_active = 1", [$planKey]);
}

/**
 * Update subscription plan (admin only)
 */
function updateSubscriptionPlan($planKey, $data) {
    global $db;
    $sql = "UPDATE subscription_plans SET plan_name = ?, monthly_price = ?, annual_price = ?, features = ?, is_active = ?, display_order = ?"
        . (dbColumnExists('subscription_plans', 'sync_status') ? ", sync_status = 'pending'" : '')
        . " WHERE plan_key = ?";
    $ok = $db->execute(
        $sql,
        [
            $data['plan_name'],
            $data['monthly_price'],
            $data['annual_price'],
            $data['features'],
            $data['is_active'],
            $data['display_order'],
            $planKey
        ],
        "sddssis"
    );
    if ($ok !== false) {
        $row = $db->fetchOne('SELECT id FROM subscription_plans WHERE plan_key = ? LIMIT 1', [$planKey], 's');
        $pid = (int) ($row['id'] ?? 0);
        if ($pid > 0) {
            sync_push_row_now('subscription_plans', $pid);
        }
    }

    return $ok;
}

}
