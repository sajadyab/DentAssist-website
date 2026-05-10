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

$userId = (int)$tokenRow['user_id'];
$user = $db->fetchOne("SELECT id, role FROM users WHERE id = ?", [$userId], 'i');
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

$patientId = getPatientIdFromUserId($userId);
if (!$patientId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

require_once $basePath . '/includes/auth.php';

$referralCode = patient_portal_ensure_referral_code($patientId);
if ($referralCode === null || $referralCode === '') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not load referral code.']);
    exit;
}

// Referred friends (cloud-first, same source as portal)
$referred = patient_portal_list_referred_patients_cloud_first($patientId);

$referralCount = count($referred);
$pointsEarned = $referralCount * 50;

// Format referred friends
$formattedReferred = [];
foreach ($referred as $ref) {
    $formattedReferred[] = [
        'full_name' => $ref['full_name'] ?? '',
        'email' => $ref['email'] ?? '',
        'phone' => $ref['phone'] ?? '',
        'joined_date' => $ref['created_at'] ?? '',
    ];
}

echo json_encode([
    'success' => true,
    'data' => [
        'referral_code' => $referralCode,
        'referral_signup_url' => url('register.php?ref=' . rawurlencode($referralCode)),
        'referral_count' => $referralCount,
        'points_earned' => $pointsEarned,
        'points_per_referral' => 50,
        'referred_friends' => $formattedReferred,
    ]
]);