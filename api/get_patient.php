<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
Auth::requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Patient ID required']);
    exit;
}

$db = Database::getInstance();
$patientId = intval($_GET['id']);

// Check authorization: patients can only view their own data
if (Auth::hasRole('patient')) {
    $userPatientId = getPatientIdFromUserId(Auth::userId());
    if ($patientId !== $userPatientId) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

$patient = $db->fetchOne(
    "SELECT id, full_name, date_of_birth, phone, email, 
            insurance_provider, insurance_id, insurance_type, insurance_coverage,
            allergies, medical_history
     FROM patients WHERE id = ?",
    [$patientId],
    "i"
);

if ($patient) {
    echo json_encode(['success' => true, 'patient' => $patient]);
} else {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
}
?>