<?php
require_once __DIR__ . '/_helpers.php';

header('Content-Type: application/json');

// Check if user is logged in and has permission
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'assistant', 'doctor'])) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$patientId = $data['patient_id'] ?? 0;
$reason = $data['reason'] ?? '';

if (!$patientId) {
    echo json_encode(['success' => false, 'error' => 'Invalid patient ID']);
    exit;
}

$res = repo_subscription_reject_pending((int) $patientId, (string) $reason);
if (!empty($res['ok'])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => (string) ($res['error'] ?? 'Unknown error')]);
}
?>
