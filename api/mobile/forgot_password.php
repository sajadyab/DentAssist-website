<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

$username = trim((string) ($input['username'] ?? ''));

if ($username === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Username is required']);
    exit;
}

$db = Database::getInstance();

// Find user by username (patient only)
$user = $db->fetchOne(
    'SELECT u.id, u.username, p.id AS patient_id, p.phone 
     FROM users u 
     JOIN patients p ON p.user_id = u.id 
     WHERE u.username = ? AND u.role = ?',
    [$username, 'patient'],
    'ss'
);

// If user not found, return error
if (!$user) {
    http_response_code(404);
    echo json_encode([
        'success' => false, 
        'message' => 'No account found with that username.'
    ]);
    exit;
}

// Generate reset token
$token = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Ensure table exists
if (function_exists('ensurePasswordResetsTableExists')) {
    ensurePasswordResetsTableExists();
}

// Delete old tokens
$db->execute('DELETE FROM password_resets WHERE patient_id = ?', [$user['patient_id']], 'i');

// Insert new token
if (function_exists('addResetToken')) {
    addResetToken($user['patient_id'], $token, $expiresAt);
} else {
    $db->execute(
        'INSERT INTO password_resets (patient_id, token, expires_at) VALUES (?, ?, ?)',
        [$user['patient_id'], $token, $expiresAt],
        'iss'
    );
}

// Build reset link
$resetLink = '';
if (function_exists('buildPasswordResetLink')) {
    $resetLink = buildPasswordResetLink($token);
} else {
    $baseUrl = defined('PUBLIC_SITE_URL') ? trim((string) PUBLIC_SITE_URL) : '';
    if ($baseUrl === '') {
        $baseUrl = defined('SITE_URL') ? trim((string) SITE_URL) : 'http://localhost/Dental_test';
    }
    $resetLink = rtrim($baseUrl, '/') . '/reset_pass.php?token=' . urlencode($token);
}

// Try WhatsApp
$whatsappSent = false;
$phone = trim((string) ($user['phone'] ?? ''));

if ($phone !== '' && function_exists('sendWhatsapp')) {
    if (function_exists('buildPasswordResetWhatsappMessage')) {
        $message = buildPasswordResetWhatsappMessage($resetLink);
    } else {
        $message = "DentAssist Password Reset\n\nClick this link to reset your password:\n" . $resetLink . "\n\nThis link expires in 1 hour.";
    }
    $result = sendWhatsapp($phone, $message);
    $whatsappSent = $result['ok'] ?? false;
}

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Reset link has been sent to your WhatsApp.',
    'whatsapp_sent' => $whatsappSent
]);