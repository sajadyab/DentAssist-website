<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';
require_once $basePath . '/includes/patient_cloud_repository.php';

header('Content-Type: application/json');

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

$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$tokenRow['user_id']], 'i');
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

$patientId = getPatientIdFromUserId($user['id']);
if (!$patientId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

$patient = patient_portal_fetch_patient_cloud_first((int) $patientId);

echo json_encode([
    'success' => true,
    'data' => [
        'full_name' => $patient['full_name'] ?? '',
        'username' => $user['username'] ?? '',
        'date_of_birth' => $patient['date_of_birth'] ?? '',
        'gender' => $patient['gender'] ?? '',
        'phone' => $patient['phone'] ?? '',
        'email' => $patient['email'] ?? '',
        'address' => $patient['address_line1'] ?? $patient['address'] ?? '',
        'emergency_contact_name' => $patient['emergency_contact_name'] ?? '',
        'emergency_contact_phone' => $patient['emergency_contact_phone'] ?? '',
        'emergency_contact_relation' => $patient['emergency_contact_relation'] ?? '',
        'points' => (int)($patient['points'] ?? 0),
        'referral_code' => $patient['referral_code'] ?? '',
    ]
]);