<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

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

$db = Database::getInstance();

try {
    // Get patient details
    $patient = $db->fetchOne("SELECT full_name, subscription_type FROM patients WHERE id = ?", [$patientId], "i");
    
    // Update patient subscription status to none
    $db->execute(
        "UPDATE patients SET subscription_status = 'none', subscription_type = 'none', subscription_start_date = NULL, subscription_end_date = NULL WHERE id = ?",
        [$patientId],
        "i"
    );
    
    // Update subscription payment to failed
    $db->execute(
        "UPDATE subscription_payments SET status = 'failed', notes = ? WHERE patient_id = ? AND status = 'pending'",
        ["Rejected: " . $reason, $patientId],
        "si"
    );
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>