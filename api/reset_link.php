<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

function respond(array $payload, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$username = trim((string) ($_POST['username'] ?? ''));

if ($username === '') {
    respond(['success' => false, 'message' => 'Username is required']);
}

$db = Database::getInstance();

$row = $db->fetchOne(
    "SELECT u.id AS user_id, u.username, u.role, u.phone AS user_phone,
            p.id AS patient_id, p.phone AS patient_phone
     FROM users u
     INNER JOIN patients p ON p.user_id = u.id
     WHERE u.username = ? AND u.role = 'patient' AND u.is_active = 1
     LIMIT 1",
    [$username],
    's'
);

if (!$row) {
    respond(['success' => false, 'message' => 'User not found']);
}

$patientId = (int) $row['patient_id'];
$userPhone = trim((string) ($row['user_phone'] ?? ''));
$patientPhone = trim((string) ($row['patient_phone'] ?? ''));
$destinationPhone = $patientPhone !== '' ? $patientPhone : $userPhone;

if ($destinationPhone === '') {
    respond(['success' => false, 'message' => 'No phone number on file for this account. Add a phone in your profile or contact the clinic.'], 422);
}

$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

try {
    ensurePasswordResetsTableExists();
    $db->execute('DELETE FROM password_resets WHERE patient_id = ?', [$patientId], 'i');
    addResetToken($patientId, $token, $expires);
} catch (Throwable $e) {
    error_log('password reset token failed: ' . $e->getMessage());
    $hint = 'Could not create reset link.';
    if (stripos($e->getMessage(), 'password_resets') !== false) {
        $hint .= ' Check that the database user can CREATE TABLE, or import the password_resets definition from database.sql.';
    }
    respond(['success' => false, 'message' => $hint], 500);
}

$resetLink = buildPasswordResetLink($token);
$message = buildPasswordResetWhatsappMessage($resetLink);

$sendResult = sendWhatsapp($destinationPhone, $message);
if (!$sendResult['ok']) {
    respond(['success' => false, 'message' => $sendResult['error'] ?? 'Failed to send WhatsApp message.'], 500);
}

respond([
    'success' => true,
    'message' => 'Password reset link sent to WhatsApp.',
]);
