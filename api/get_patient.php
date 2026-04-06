<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Require login
Auth::requireLogin();

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Patient ID required']);
    exit;
}

$patientId = (int) ($_GET['id'] ?? 0);

// Check authorization: patients can only view their own data
if (Auth::hasRole('patient')) {
    $userPatientId = getPatientIdFromUserId(Auth::userId());
    if ($patientId !== $userPatientId) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

$patient = PatientRepository::findForApi($patientId);

if ($patient) {
    echo json_encode(['success' => true, 'patient' => $patient]);
} else {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
}
?>
