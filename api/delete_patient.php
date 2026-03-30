<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Only authenticated users can delete
Auth::requireLogin();

$db = Database::getInstance();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$patientId = $input['id'] ?? 0;

if (!$patientId) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

// Fetch patient for logging
$patient = $db->fetchOne("SELECT * FROM patients WHERE id = ?", [$patientId], "i");
if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

// Optional: Check permissions (e.g., only admin or creator can delete)
// if ($_SESSION['role'] != 'admin' && $patient['created_by'] != $_SESSION['user_id']) {
//     echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this patient']);
//     exit;
// }

// Delete related records to avoid foreign key issues (if not set to CASCADE)
// Adjust based on your database schema
$db->execute("DELETE FROM appointments WHERE patient_id = ?", [$patientId], "i");
$db->execute("DELETE FROM treatment_plans WHERE patient_id = ?", [$patientId], "i");
$db->execute("DELETE FROM xrays WHERE patient_id = ?", [$patientId], "i");
$db->execute("DELETE FROM invoices WHERE patient_id = ?", [$patientId], "i");
// Add any other related tables

// Finally, delete the patient
$result = $db->execute("DELETE FROM patients WHERE id = ?", [$patientId], "i");

if ($result !== false) {
    logAction('DELETE', 'patients', $patientId, $patient, null);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}