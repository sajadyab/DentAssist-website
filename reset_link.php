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

$username = trim((string)($_POST['username'] ?? ''));

if (empty($username)) {
    respond(['success' => false, 'message' => 'Username is required']);
}

$patient = getPatientByUsername($username);

if (!$patient) {
    respond(['success' => false, 'message' => 'User not found']);
}

$patientId = getPatientIdFromUserId((int) $patient['id']);
if ($patientId === null) {
    respond(['success' => false, 'message' => 'No patient profile linked to this user.'], 422);
}

/*
|--------------------------------------------------------------------------
| Generate Reset Token
|--------------------------------------------------------------------------
*/

$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

addResetToken((string) $patientId, $token, $expires);
/*
|--------------------------------------------------------------------------
| Build Reset Link
|--------------------------------------------------------------------------
*/

$resetLink = buildPasswordResetLink($token);

/*
|--------------------------------------------------------------------------
| WhatsApp Message
|--------------------------------------------------------------------------
*/

$message = buildPasswordResetWhatsappMessage($resetLink);

/*
|--------------------------------------------------------------------------
| Send WhatsApp
|--------------------------------------------------------------------------
*/

$destinationPhone = getPatientWhatsappPhone($patient);
if ($destinationPhone === '') {
    respond(['success' => false, 'message' => 'No WhatsApp phone number found for this user.'], 422);
}

$sendResult = sendWhatsapp($destinationPhone, $message);
if (!$sendResult['ok']) {
    respond(['success' => false, 'message' => $sendResult['error'] ?? 'Failed to send WhatsApp message.'], 500);
}

respond([
    'success' => true,
    'message' => 'Password reset link sent to WhatsApp'
]);
