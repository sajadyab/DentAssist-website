<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id'])) {
    echo json_encode(['success' => false, 'message' => 'Plan ID required']);
    exit;
}

$db = Database::getInstance();

$result = $db->execute(
    "UPDATE treatment_plans SET patient_approved = 1, approval_date = NOW() WHERE id = ?",
    [$input['id']],
    "i"
);

if ($result) {
    logAction('UPDATE', 'treatment_plans', $input['id'], null, ['patient_approved' => 1]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Approval failed']);
}
?>