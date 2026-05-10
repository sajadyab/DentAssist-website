<?php
/**
 * API: send_subscription_reminder.php
 * WhatsApp reminder about patient subscription (expired vs active / upcoming end).
 * POST JSON: { "patient_id": 123 }
 * Same delivery path as api/send_reminder.php (local Node send.js).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['doctor', 'assistant'], true)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$patientId = isset($input['patient_id']) ? (int) $input['patient_id'] : 0;

if ($patientId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing or invalid patient ID']);
    exit;
}

$db = Database::getInstance();

$patient = $db->fetchOne(
    'SELECT id, full_name, phone, subscription_type, subscription_status, subscription_end_date
     FROM patients WHERE id = ?',
    [$patientId],
    'i'
);

if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

$subType = trim((string) ($patient['subscription_type'] ?? ''));
if ($subType === '' || $subType === 'none') {
    echo json_encode(['success' => false, 'message' => 'Patient has no paid subscription on file']);
    exit;
}

$endRaw = $patient['subscription_end_date'] ?? '';
if ($endRaw === '' || $endRaw === '0000-00-00') {
    echo json_encode(['success' => false, 'message' => 'No valid subscription end date']);
    exit;
}

$patientPhone = trim((string) ($patient['phone'] ?? ''));
if ($patientPhone === '') {
    echo json_encode(['success' => false, 'message' => 'Patient has no phone number on file']);
    exit;
}

$endTs = strtotime((string) $endRaw);
if ($endTs === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid subscription end date']);
    exit;
}

$endFormatted = date('Y-m-d', $endTs);
$planLabel = ucfirst($subType);
$statusLabel = ucfirst(trim((string) ($patient['subscription_status'] ?? '')));

$now = new DateTime();
$end = new DateTime((string) $endRaw);
$isExpired = $now > $end;

if ($isExpired) {
    $daysPast = (int) $now->diff($end)->days;
    $daysPast = max(1, $daysPast);
    $messageBody = "Dear {$patient['full_name']},\n\n"
        . "Your {$planLabel} subscription expired on {$endFormatted} ({$daysPast} day(s) ago).\n\n"
        . "Please contact us to renew your plan and restore your benefits.\n\n"
        . "Thank you,\nDental Clinic Team";
    $logSubject = 'Subscription reminder — expired';
} else {
    $daysLeft = (int) $now->diff($end)->days;
    $statusLine = $statusLabel !== ''
        ? "Your subscription is currently {$statusLabel}.\n"
        : '';
    if ($daysLeft === 0) {
        $urgent = "Your subscription period ends today ({$endFormatted}). Please renew soon to avoid interruption.\n\n";
    } elseif ($daysLeft <= 3) {
        $urgent = "Your subscription ends in {$daysLeft} day(s), on {$endFormatted}. Please renew before that date to avoid interruption.\n\n";
    } else {
        $urgent = "Your current plan ends on {$endFormatted}. We recommend reviewing renewal options before that date.\n\n";
    }
    $messageBody = "Dear {$patient['full_name']},\n\n"
        . $statusLine
        . $urgent
        . "If you have questions, reply here or contact the clinic.\n\n"
        . "Thank you,\nDental Clinic Team";
    $logSubject = 'Subscription reminder — active / upcoming expiry';
}

$sent = sendWhatsapp($patientPhone, $messageBody);
if (!$sent['ok']) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send reminder: ' . ($sent['error'] ?? 'Unknown error'),
    ]);
    exit;
}

$externalId = 'node';
try {
    $db->execute(
        "INSERT INTO messages
         (patient_id, message_type, subject, message, delivery_method, status, sent_at, created_by, external_id)
         VALUES (?, 'subscription_reminder', ?, ?, 'whatsapp', 'sent', NOW(), ?, ?)",
        [
            $patient['id'],
            $logSubject,
            $messageBody,
            Auth::userId(),
            $externalId,
        ],
        'issis'
    );
} catch (Exception $e) {
    error_log('Failed to log subscription reminder message: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'message' => 'Subscription reminder sent successfully (WhatsApp via local Node server).',
]);
