<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';

header('Content-Type: application/json');

// Token Auth
$headers = function_exists('getallheaders') ? getallheaders() : [];
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
$patientId = getPatientIdFromUserId($userId);
if (!$patientId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

// Get clinic phone (OWO number)
$clinicPhone = '';
$phoneRow = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_phone'");
if ($phoneRow && !empty($phoneRow['setting_value'])) {
    $clinicPhone = $phoneRow['setting_value'];
}

// Get plan details from POST body
$input = json_decode(file_get_contents('php://input'), true);
$plan = $input['plan'] ?? '';
$billingCycle = $input['billing_cycle'] ?? 'monthly';
$amount = 0;

if ($plan) {
    $prices = [
        'basic'   => ['monthly' => 29, 'yearly' => 348],
        'premium' => ['monthly' => 49, 'yearly' => 588],
        'family'  => ['monthly' => 79, 'yearly' => 948],
    ];
    $amount = $prices[$plan][$billingCycle] ?? 0;
}

$reference = 'SUB-' . $patientId . '-' . time();

echo json_encode([
    'success' => true,
    'data' => [
        'clinic_owo_number' => $clinicPhone,
        'amount' => $amount,
        'plan' => $plan,
        'billing_cycle' => $billingCycle,
        'reference' => $reference,
        'patient_id' => $patientId,
        'patient_name' => '', // optionally fetch full name
    ]
]);