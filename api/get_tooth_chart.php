<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
header('Content-Type: application/json');

$patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;
if (!$patientId) {
    echo json_encode(['success' => false, 'message' => 'Patient ID required']);
    exit;
}

// Check authorization: patients can only view their own data
if (Auth::hasRole('patient')) {
    $userPatientId = getPatientIdFromUserId(Auth::userId());
    if ($patientId !== $userPatientId) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

$db = Database::getInstance();
$teeth = $db->fetchAll(
    "SELECT tooth_number, status, diagnosis, treatment, notes, last_updated 
     FROM tooth_chart WHERE patient_id = ?",
    [$patientId],
    "i"
);

$result = [];
foreach ($teeth as $t) {
    $result[$t['tooth_number']] = $t;
}
echo json_encode(['success' => true, 'data' => $result]);
?>