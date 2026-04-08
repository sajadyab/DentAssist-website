<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$planId = (int) ($data['id'] ?? 0);
if ($planId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid plan id']);
    exit;
}

$db = Database::getInstance();
$plan = $db->fetchOne('SELECT * FROM treatment_plans WHERE id = ?', [$planId], 'i');
if (!$plan) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Treatment plan not found']);
    exit;
}

if (!empty($plan['patient_approved'])) {
    echo json_encode(['success' => true, 'message' => 'Already approved']);
    exit;
}

$aff = $db->execute(
    'UPDATE treatment_plans SET patient_approved = 1, approval_date = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND COALESCE(patient_approved, 0) = 0',
    [$planId],
    'i'
);

if ($aff < 1) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not update treatment plan.']);
    exit;
}

logAction('UPDATE', 'treatment_plans', $planId, $plan, ['patient_approved' => 1, 'approval_date' => 'now']);

echo json_encode(['success' => true, 'message' => 'Plan marked as approved.']);
