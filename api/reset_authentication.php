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

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed.'], 405);
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../hash.php';

$token = trim((string)($_POST['token'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');

if ($token === '' || $password === '' || $confirm === '') {
    respond(['success' => false, 'message' => 'Token and passwords are required.'], 422);
}

if ($password !== $confirm) {
    respond(['success' => false, 'message' => 'Passwords do not match.'], 422);
}

if (strlen($password) < 6) {
    respond(['success' => false, 'message' => 'Password must be at least 6 characters.'], 422);
}

$resetRow = getPasswordResetByToken($token);
if ($resetRow === null) {
    respond(['success' => false, 'message' => 'Invalid or expired token.'], 400);
}

$expiresAt = (string)($resetRow['expires_at'] ?? '');
if ($expiresAt === '' || strtotime($expiresAt) === false || strtotime($expiresAt) < time()) {
    consumePasswordResetToken($token);
    respond(['success' => false, 'message' => 'This reset link has expired.'], 400);
}

$patientId = (int)($resetRow['patient_id'] ?? 0);
if ($patientId <= 0) {
    respond(['success' => false, 'message' => 'Invalid reset record.'], 500);
}

$userId = getUserIdFromPatientId($patientId);
if ($userId === null) {
    respond(['success' => false, 'message' => 'No user account linked to this patient.'], 422);
}

$hashed = hashPassword($password);

$db = Database::getInstance();
$setParts = ['password_hash = ?'];
$values = [$hashed];
$types = 's';
if (function_exists('dbColumnExists') && dbColumnExists('users', 'sync_status')) {
    $setParts[] = "sync_status = 'pending'";
}
$values[] = $userId;
$types .= 'i';
$affected = $db->execute(
    'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?',
    $values,
    $types
);
if ($affected > 0 && function_exists('sync_push_row_now')) {
    sync_push_row_now('users', (int) $userId);
}

consumePasswordResetToken($token);

if ($affected <= 0) {
    respond(['success' => false, 'message' => 'Password was not updated.'], 500);
}

respond(['success' => true, 'message' => 'Password updated successfully.']);

