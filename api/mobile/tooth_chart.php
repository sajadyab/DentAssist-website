<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/patient_cloud_repository.php';

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

// Get all teeth for this patient
$teeth = patient_portal_list_tooth_chart_cloud_first($patientId);

// Build a map of tooth_number → data
$toothMap = [];
foreach ($teeth as $t) {
    $num = (int)$t['tooth_number'];
    $toothMap[$num] = [
        'status' => $t['status'] ?? 'healthy',
        'diagnosis' => $t['diagnosis'] ?? '',
        'treatment' => $t['treatment'] ?? '',
        'notes' => $t['notes'] ?? '',
        'last_updated' => $t['last_updated'] ?? null,
    ];
}

// Ensure all 32 teeth are present (default healthy)
$fullList = [];
for ($i = 1; $i <= 32; $i++) {
    $fullList[] = [
        'tooth_number' => $i,
        'status' => $toothMap[$i]['status'] ?? 'healthy',
        'diagnosis' => $toothMap[$i]['diagnosis'] ?? '',
        'treatment' => $toothMap[$i]['treatment'] ?? '',
        'notes' => $toothMap[$i]['notes'] ?? '',
        'last_updated' => $toothMap[$i]['last_updated'] ?? null,
    ];
}

echo json_encode(['success' => true, 'data' => ['teeth' => $fullList]]);