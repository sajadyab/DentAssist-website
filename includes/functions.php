<?php
require_once 'db.php';
require_once 'auth.php';

// Global helper functions

// Generate absolute URL for internal paths (keeps views/pages consistent)
function url($path = '') {
    $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
    if ($path === '') {
        return $base ?: '/';
    }
    return $base . '/' . ltrim($path, '/');
}

// Format date
function formatDate($date, $format = 'M d, Y') {
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
function formatTime($time, $format = 'g:i A') {
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
function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

// Get patient name by ID
function getPatientName($patientId) {
    $db = Database::getInstance();
    $patient = $db->fetchOne(
        "SELECT full_name FROM patients WHERE id = ?",
        [$patientId],
        "i"
    );
    return $patient ? $patient['full_name'] : 'Unknown';
}

// Get doctor name by ID
function getDoctorName($doctorId) {
    $db = Database::getInstance();
    $doctor = $db->fetchOne(
        "SELECT full_name FROM users WHERE id = ? AND role = 'doctor'",
        [$doctorId],
        "i"
    );
    return $doctor ? $doctor['full_name'] : 'Unknown';
}

// Get appointment status badge
function getStatusBadge($status) {
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
function logAction($action, $table, $recordId, $oldValues = null, $newValues = null) {
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
function uploadFile($file, $targetDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
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
function sendNotification($userId, $type, $title, $message, $via = 'in-app', $relatedAppointmentId = null, $relatedInvoiceId = null) {
    $db = Database::getInstance();
    
    return $db->insert(
        "INSERT INTO notifications (user_id, type, title, message, sent_via, related_appointment_id, related_invoice_id) 
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$userId, $type, $title, $message, $via, $relatedAppointmentId, $relatedInvoiceId],
        "issssii"
    );
}

// Generate invoice number
function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
}
// Translation function
function __($key, $default = '') {
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
function setLanguage($lang) {
    if (in_array($lang, ['en', 'ar', 'fr'])) {
        $_SESSION['lang'] = $lang;
        return true;
    }
    return false;
}

// Get current language
function getLanguage() {
    return $_SESSION['lang'] ?? 'en';
}
function getPatientIdFromUserId($userId) {
    $db = Database::getInstance();
    $patient = $db->fetchOne("SELECT id FROM patients WHERE user_id = ?", [$userId], "i");
    return $patient ? $patient['id'] : null;
}
?>