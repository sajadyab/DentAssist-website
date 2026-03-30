<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Step ID and status required']);
    exit;
}

$db = Database::getInstance();

$result = $db->execute(
    "UPDATE treatment_steps SET status = ?, completed_date = IF(? = 'completed', CURDATE(), completed_date) WHERE id = ?",
    [$input['status'], $input['status'], $input['id']],
    "ssi"
);

if ($result) {
    logAction('UPDATE', 'treatment_steps', $input['id'], null, ['status' => $input['status']]);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}
?>