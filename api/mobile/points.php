<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';
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

// Patient points
$patient = patient_portal_fetch_patient_cloud_first((int) $patientId);
$points = (int) ($patient['points'] ?? 0);

// Referral count
$referralCount = patient_portal_count_referred_patients_cloud_first((int) $patientId);

// Completed appointments
$appointments = patient_portal_list_completed_appointments_cloud_first((int) $patientId, 10);

// Build history
$historyItems = [];
foreach ($appointments as $apt) {
    $historyItems[] = [
        'side' => formatDate($apt['appointment_date']),
        'title' => 'Completed visit',
        'muted' => (string) $apt['treatment_type'],
        'pointsLabel' => '+50',
    ];
}
if ($referralCount > 0) {
    $historyItems[] = [
        'side' => 'Referral',
        'title' => $referralCount . ' friend' . ($referralCount > 1 ? 's' : '') . ' joined',
        'muted' => 'Referral bonus',
        'pointsLabel' => '+' . ($referralCount * 50),
    ];
}
if (empty($historyItems) && $points > 0) {
    $historyItems[] = [
        'side' => '—',
        'title' => 'Points balance',
        'muted' => 'Your current total',
        'pointsLabel' => (string) $points,
    ];
}

$ptsMod = $points % 250;
$toNextReward = $points === 0 ? 250 : ($ptsMod === 0 ? 250 : 250 - $ptsMod);

echo json_encode([
    'success' => true,
    'data' => [
        'total_points' => $points,
        'points_to_next_reward' => $toNextReward,
        'history' => $historyItems,
    ]
]);