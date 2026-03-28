<?php
require_once 'db.php';
require_once 'auth.php';
require_once __DIR__ . '/whatsapp_cloud.php';

// Global helper functions

// Generate absolute URL for internal paths (keeps views/pages consistent)
function url($path = '')
{
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    if ($path === '') {
        return $base ?: '/';
    }
    return $base . '/' . ltrim($path, '/');
}

// Format date
function formatDate($date, $format = 'M d, Y')
{
    if (empty($date) || is_null($date)) {
        return 'Not set';
    }
    try {
        $dateObj = new DateTime($date);
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

    $db->execute(
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

    return $db->insert(
        "INSERT INTO notifications (user_id, type, title, message, sent_via, related_appointment_id, related_invoice_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$userId, $type, $title, $message, $via, $relatedAppointmentId, $relatedInvoiceId],
        "issssii"
    );
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
function getPatientIdFromUserId($userId)
{
    $db = Database::getInstance();
    $patient = $db->fetchOne("SELECT id FROM patients WHERE user_id = ?", [$userId], "i");
    return $patient ? $patient['id'] : null;
}
function addResetToken($patientId, $token, $expiresAt)
{
    $db = Database::getInstance();

    $db->execute(
        "INSERT INTO `password_resets`(`patient_id`, `token`, `created_at`, `expires_at`) VALUES (?,?,?,?)",
        [
            $patientId,
            $token,
            date('Y-m-d H:i:s'),
            $expiresAt
        ],
        "ssss"
    );
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
    return $baseUrl . '/rese_pass.php?token=' . urlencode($token);
}

function getPatientWhatsappPhone(array $user): string
{
    return (string) ($user['phone'] ?? $user['mobile'] ?? $user['whatsapp_phone'] ?? '');
}

function buildPasswordResetWhatsappMessage(string $resetLink): string
{
    $message = "DentAssist Password Reset\n\n";
    $message .= "Click this link to reset your password:\n";
    $message .= trim($resetLink) . "\n\n";
    $message .= "This link will expire in 1 hour.";
    return $message;
}

function sendWhatsapp(string $phone, string $message): array
{
    $cloudResult = whatsapp_cloud_send_text($phone, $message);
    if ($cloudResult['ok']) {
        return ['ok' => true, 'error' => null];
    }

    // Fallback to local Node sender if Cloud API is not configured.
    $cloudError = (string) ($cloudResult['error'] ?? '');
    $isConfigError = stripos($cloudError, 'not configured') !== false;
    if (!$isConfigError) {
        return ['ok' => false, 'error' => $cloudError !== '' ? $cloudError : 'WhatsApp Cloud send failed.'];
    }

    $endpoint = 'http://127.0.0.1:3210/send';
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
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false || $raw === null || $raw === '') {
        return [
            'ok' => false,
            'error' => 'Local WhatsApp server is not reachable. Start: npm start',
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
        return ['ok' => false, 'error' => 'Local WhatsApp server returned invalid response.'];
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

function sendWhatsapp2(string $phoneNumber, string $message): array
{
    // Encode message for URL
    $encodedMessage = urlencode($message);
    $encodedPhone = urlencode($phoneNumber);
    $encodedApiKey = urlencode(8533808);

    $url = "https://api.callmebot.com/whatsapp.php?phone={$encodedPhone}&text={$encodedMessage}&apikey={$encodedApiKey}";

    // Suppress warnings with @, handle errors manually
    $response = @file_get_contents($url);

    if ($response === false) {
        $error = error_get_last();
        return [
            'ok' => false,
            'error' => isset($error['message']) ? $error['message'] : 'Unknown error'
        ];
    }

    // Check if API returned an error in response body
    if (stripos($response, 'error') !== false) {
        return [
            'ok' => false,
            'error' => $response
        ];
    }

    // Success
    return [
        'ok' => true,
    ];
}
