<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Token Authentication
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

$db = Database::getInstance();
$tokenRow = $db->fetchOne("SELECT user_id FROM api_tokens WHERE token = ?", [$token], 's');

if (!$tokenRow) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');

if ($currentPassword === '' || $newPassword === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Both passwords are required']);
    exit;
}

if (strlen($newPassword) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters']);
    exit;
}

// Get user directly from database
$userId = (int)$tokenRow['user_id'];
$user = $db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$userId], 'i');

if (!$user) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Verify current password
if (!password_verify($currentPassword, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
    exit;
}

// Hash new password
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);

// Update
$db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $userId], 'si');

echo json_encode(['success' => true, 'message' => 'Password changed successfully']);