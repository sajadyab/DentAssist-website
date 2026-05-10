<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Token Auth
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

$db = Database::getInstance();
$tokenRow = $db->fetchOne("SELECT user_id FROM api_tokens WHERE token = ?", [$token], 's');

if (!$tokenRow) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$userId = (int)$tokenRow['user_id'];

// Get patient ID
$patient = $db->fetchOne("SELECT id FROM patients WHERE user_id = ?", [$userId], 'i');
if (!$patient) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}
$patientId = (int)$patient['id'];

// Get input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$doctorId = (int)($input['doctor_id'] ?? 0);
$appointmentDate = trim((string)($input['appointment_date'] ?? ''));
$appointmentTime = trim((string)($input['appointment_time'] ?? ''));
$treatmentType = trim((string)($input['treatment_type'] ?? ''));
$notes = trim((string)($input['notes'] ?? ''));

if ($doctorId <= 0 || $appointmentDate === '' || $appointmentTime === '' || $treatmentType === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Format time with seconds
if (strlen($appointmentTime) === 5) {
    $appointmentTime .= ':00';
}

// Insert appointment request
try {
    $columns = ['patient_id', 'doctor_id', 'requested_date', 'requested_time', 'duration_minutes', 'treatment_type', 'description'];
    $values = [$patientId, $doctorId, $appointmentDate, $appointmentTime, 45, $treatmentType, $notes ?: null];
    $types = 'iississ';
    if (dbColumnExists('appointment_requests', 'sync_status')) {
        $columns[] = 'sync_status';
        $values[] = 'pending';
        $types .= 's';
    }

    $requestId = $db->insert(
        'INSERT INTO appointment_requests (' . implode(', ', $columns) . ')
         VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
        $values,
        $types
    );
    if ($requestId > 0 && function_exists('sync_push_row_now')) {
        sync_push_row_now('appointment_requests', (int) $requestId);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Appointment request submitted. You will be notified when the dentist responds.',
        'request_id' => (int)$requestId,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
