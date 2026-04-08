<?php
require_once __DIR__ . '/_helpers.php';

// Only authenticated users can delete
Auth::requireLogin();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$patientId = (int) ($input['id'] ?? 0);

if (!$patientId) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

// Fetch patient for logging
$patient = repo_patient_find_by_id($patientId);
if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

// Optional: Check permissions (e.g., only admin or creator can delete)
// if ($_SESSION['role'] != 'admin' && $patient['created_by'] != $_SESSION['user_id']) {
//     echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this patient']);
//     exit;
// }

$result = repo_patient_delete_cascade($patientId);

if ($result) {
    logAction('DELETE', 'patients', $patientId, $patient, null);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
