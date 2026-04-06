<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Step ID required']);
    exit;
}

$db = Database::getInstance();
$stepId = intval($_GET['id']);

$step = $db->fetchOne(
    "SELECT * FROM treatment_steps WHERE id = ?",
    [$stepId],
    "i"
);

if ($step) {
    echo json_encode(['success' => true, 'step' => $step]);
} else {
    echo json_encode(['success' => false, 'message' => 'Step not found']);
}
?>