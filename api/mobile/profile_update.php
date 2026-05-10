<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';
require_once $basePath . '/includes/patient_cloud_repository.php';

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

$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$tokenRow['user_id']], 'i');
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

$patientId = getPatientIdFromUserId($user['id']);
if (!$patientId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$fullName = trim((string)($input['full_name'] ?? ''));
$username = trim((string)($input['username'] ?? ''));
$email = trim((string)($input['email'] ?? ''));
$phone = trim((string)($input['phone'] ?? ''));
$address = trim((string)($input['address'] ?? ''));
$gender = trim((string)($input['gender'] ?? ''));
$dateOfBirth = trim((string)($input['date_of_birth'] ?? ''));
$emergencyName = trim((string)($input['emergency_contact_name'] ?? ''));
$emergencyPhone = trim((string)($input['emergency_contact_phone'] ?? ''));
$emergencyRelation = trim((string)($input['emergency_contact_relation'] ?? ''));

// Convert date from dd/mm/yyyy to yyyy-mm-dd
if ($dateOfBirth !== '' && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateOfBirth, $m)) {
    $dateOfBirth = $m[3] . '-' . $m[2] . '-' . $m[1];
}

// Check username uniqueness
if ($username !== $user['username']) {
    $existing = $db->fetchOne('SELECT id FROM users WHERE username = ? AND id != ?', [$username, $user['id']], 'si');
    if ($existing) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Username already taken']);
        exit;
    }
}

// Update users table
$db->execute(
    "UPDATE users SET username = ?, full_name = ?, email = ?, phone = ?, sync_status = 'pending' WHERE id = ?",
    [$username, $fullName, $email, $phone, $user['id']],
    'ssssi'
);
sync_push_row_now('users', (int)$user['id']);

// Update patients table
$db->execute(
    "UPDATE patients SET full_name = ?, date_of_birth = ?, gender = ?, phone = ?, email = ?, 
     address_line1 = ?, emergency_contact_name = ?, emergency_contact_phone = ?, 
     emergency_contact_relation = ?, sync_status = 'pending' WHERE id = ?",
    [$fullName, $dateOfBirth ?: null, $gender ?: null, $phone, $email, 
     $address, $emergencyName, $emergencyPhone, $emergencyRelation, $patientId],
    'sssssssssi'
);
sync_push_row_now('patients', (int)$patientId);

echo json_encode([
    'success' => true,
    'message' => 'Profile updated successfully'
]);