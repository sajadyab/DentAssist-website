<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/auth.php';
require_once $basePath . '/includes/functions.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$username = trim((string) ($input['username'] ?? ''));
$password = (string) ($input['password'] ?? '');

if ($username === '' || $password === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

// Authenticate using existing Auth class
if (!Auth::login($username, $password)) {
    $errorType = Auth::getLastError();
    if ($errorType === 'inactive') {
        $db = Database::getInstance();
        $phoneRow = $db->fetchOne(
            "SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_phone' LIMIT 1"
        );
        $phone = $phoneRow ? ($phoneRow['setting_value'] ?? 'the clinic') : 'the clinic';
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => "Account inactive. Contact {$phone}"]);
        exit;
    }
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
    exit;
}

// Success - get user data
$user = Auth::user();
$userId = (int) $user['id'];
$role = $user['role'];

$patientId = null;
if ($role === 'patient') {
    $patientId = getPatientIdFromUserId($userId);
}

// Generate token
$token = bin2hex(random_bytes(32));
$db = Database::getInstance();
$db->execute("INSERT INTO api_tokens (user_id, token) VALUES (?, ?)", [$userId, $token], 'is');

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Login successful',
    'token' => $token,
    'user_id' => $userId,
    'username' => $user['username'],
    'full_name' => $user['full_name'],
    'role' => $role,
    'patient_id' => $patientId
]);