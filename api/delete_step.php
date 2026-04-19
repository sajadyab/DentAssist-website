<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

Auth::requireLogin();
$input = json_decode(file_get_contents('php://input'), true);
$stepId = (int)($input['id'] ?? 0);

if (!$stepId) {
    echo json_encode(['success' => false, 'message' => 'Invalid step ID']);
    exit;
}

$db = Database::getInstance();

try {
    $step = $db->fetchOne("SELECT * FROM treatment_steps WHERE id = ?", [$stepId], "i");
    if (!$step) {
        echo json_encode(['success' => false, 'message' => 'Step not found']);
        exit;
    }
    queueCloudDeletion('treatment_steps', $stepId, 'local_id');
    // Optional: check if step belongs to a plan the user has access to
    $result = $db->execute("DELETE FROM treatment_steps WHERE id = ?", [$stepId], "i");
    if ($result !== false) {
        sync_process_delete_queue_now(1);
        logAction('DELETE', 'treatment_steps', $stepId, $step, null);
        echo json_encode(['success' => true]);
    } else {
        // Get the actual MySQL error
        $conn = $db->getConnection();
        $errorMsg = $conn->error;
        echo json_encode(['success' => false, 'message' => 'Database error: ' . ($errorMsg ?: 'Unknown')]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
