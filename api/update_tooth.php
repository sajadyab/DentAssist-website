<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$patientId = intval($input['patient_id'] ?? 0);
$toothNumber = intval($input['tooth_number'] ?? 0);
$status = $input['status'] ?? 'healthy';
$diagnosis = $input['diagnosis'] ?? null;
$treatment = $input['treatment'] ?? null;
$notes = $input['notes'] ?? null;

if (!$patientId || !$toothNumber) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Check authorization: patients can only update their own data
if (Auth::hasRole('patient')) {
    $userPatientId = getPatientIdFromUserId(Auth::userId());
    if ($patientId !== $userPatientId) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
}

$db = Database::getInstance();
$existing = $db->fetchOne(
    "SELECT id FROM tooth_chart WHERE patient_id = ? AND tooth_number = ?",
    [$patientId, $toothNumber],
    "ii"
);

if ($existing) {
    $db->execute(
        "UPDATE tooth_chart SET status = ?, diagnosis = ?, treatment = ?, notes = ?, updated_by = ? WHERE patient_id = ? AND tooth_number = ?",
        [$status, $diagnosis, $treatment, $notes, Auth::userId(), $patientId, $toothNumber],
        "ssssiii"
    );
    logAction('UPDATE', 'tooth_chart', $existing['id'], null, $input);
} else {
    $id = $db->insert(
        "INSERT INTO tooth_chart (patient_id, tooth_number, status, diagnosis, treatment, notes, updated_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$patientId, $toothNumber, $status, $diagnosis, $treatment, $notes, Auth::userId()],
        "iissssi"
    );
    logAction('CREATE', 'tooth_chart', $id, null, $input);
}

echo json_encode(['success' => true, 'message' => 'Tooth updated']);
?>