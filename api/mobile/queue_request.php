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
$token = '';
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';
    $token = str_replace('Bearer ', '', $authHeader);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
}

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

// Get patient info
$patient = $db->fetchOne("SELECT id, full_name FROM patients WHERE user_id = ?", [$userId], 'i');
if (!$patient) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}
$patientId = (int)$patient['id'];
$patientName = $patient['full_name'] ?? 'Patient';

// Get JSON input
$rawInput = file_get_contents('php://input');
error_log("Queue request raw input: " . $rawInput);
$input = json_decode($rawInput, true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON: ' . json_last_error_msg()]);
    exit;
}

$doctorId = (int)($input['doctor_id'] ?? 0);
$preferredDate = trim((string)($input['preferred_date'] ?? ''));
$treatmentType = trim((string)($input['treatment_type'] ?? ''));
$priority = trim((string)($input['priority'] ?? 'medium'));
$notes = trim((string)($input['notes'] ?? ''));

error_log("Queue request: doctor=$doctorId, date=$preferredDate, type=$treatmentType, priority=$priority");

// Validate
if ($doctorId <= 0 || $preferredDate === '' || $treatmentType === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Doctor, date, and treatment type are required']);
    exit;
}

// Get day name
try {
    $dt = new DateTimeImmutable($preferredDate);
    $preferredDayName = $dt->format('l');
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Insert into waiting_queue
try {
    $queueId = $db->insert(
        "INSERT INTO waiting_queue (patient_id, patient_name, doctor_id, queue_type, priority, reason, preferred_day, preferred_date, notes, status) 
         VALUES (?, ?, ?, 'weekly', ?, ?, ?, ?, ?, 'waiting')",
        [$patientId, $patientName, $doctorId, $priority, $treatmentType, $preferredDayName, $preferredDate, $notes ?: ''],
        'isisssss'
    );

    error_log("Queue request inserted with ID: " . $queueId);

    echo json_encode([
        'success' => true,
        'message' => 'Queue request submitted successfully!',
        'queue_id' => (int)$queueId,
    ]);
} catch (Exception $e) {
    error_log("Queue request error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}