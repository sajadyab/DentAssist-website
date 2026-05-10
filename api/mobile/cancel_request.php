<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$token = '';
if (function_exists('getallheaders')) {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
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
$patient = $db->fetchOne("SELECT id FROM patients WHERE user_id = ?", [$userId], 'i');
if (!$patient) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}
$patientId = (int)$patient['id'];

$input = json_decode(file_get_contents('php://input'), true);
$requestId = (int)($input['request_id'] ?? 0);

if ($requestId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
    exit;
}

// Delete only if belongs to this patient
$deleted = $db->execute(
    "DELETE FROM appointment_requests WHERE id = ? AND patient_id = ?",
    [$requestId, $patientId],
    'ii'
);

if ($deleted > 0) {
    echo json_encode(['success' => true, 'message' => 'Request cancelled']);
} else {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Request not found']);
}