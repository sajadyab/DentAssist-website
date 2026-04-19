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
    $setParts = ['status = ?', 'diagnosis = ?', 'treatment = ?', 'notes = ?', 'updated_by = ?'];
    $values = [$status, $diagnosis, $treatment, $notes, Auth::userId()];
    $types = 'sssss';
    if (dbColumnExists('tooth_chart', 'sync_status')) {
        $setParts[] = "sync_status = 'pending'";
    }
    $values[] = $patientId;
    $types .= 'i';
    $values[] = $toothNumber;
    $types .= 'i';
    $db->execute(
        'UPDATE tooth_chart SET ' . implode(', ', $setParts) . ' WHERE patient_id = ? AND tooth_number = ?',
        $values,
        $types
    );
    logAction('UPDATE', 'tooth_chart', $existing['id'], null, $input);
    sync_push_row_now('tooth_chart', $existing['id']);
} else {
    $columns = ['patient_id', 'tooth_number', 'status', 'diagnosis', 'treatment', 'notes', 'updated_by'];
    $values = [$patientId, $toothNumber, $status, $diagnosis, $treatment, $notes, Auth::userId()];
    $types = 'iissssi';
    if (dbColumnExists('tooth_chart', 'sync_status')) {
        $columns[] = 'sync_status';
        $values[] = 'pending';
        $types .= 's';
    }
    $id = $db->insert(
        'INSERT INTO tooth_chart (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
        $values,
        $types
    );
    logAction('CREATE', 'tooth_chart', $id, null, $input);
    sync_push_row_now('tooth_chart', $id);
}

echo json_encode(['success' => true, 'message' => 'Tooth updated']);
?>