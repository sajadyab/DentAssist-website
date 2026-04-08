<?php
/**
 * API endpoint: send_reminder.php
 * Sends a WhatsApp reminder for a given appointment.
 * Expects POST JSON: { "appointment_id": 123 }
 * Returns JSON: { "success": bool, "message": string } (WhatsApp via local Node send.js)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireLogin();

header('Content-Type: application/json');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Read input
$input = json_decode(file_get_contents('php://input'), true);
$appointmentId = $input['appointment_id'] ?? null;

if (!$appointmentId) {
    echo json_encode(['success' => false, 'message' => 'Missing appointment ID']);
    exit;
}

$db = Database::getInstance();

// Fetch appointment details with patient and doctor info
$appointment = $db->fetchOne(
    "SELECT a.*,
            p.full_name AS patient_name,
            p.phone AS patient_phone,
            u.full_name AS doctor_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN users u ON a.doctor_id = u.id
     WHERE a.id = ?",
    [$appointmentId],
    "i"
);

if (!$appointment) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

// Check if the appointment is cancelled
if ($appointment['status'] === 'cancelled') {
    echo json_encode(['success' => false, 'message' => 'Cannot send reminder for cancelled appointment']);
    exit;
}

$patientPhone = $appointment['patient_phone'];
if (empty(trim((string) $patientPhone))) {
    echo json_encode(['success' => false, 'message' => 'Patient has no phone number on file']);
    exit;
}

// Build message text
$date = formatDate($appointment['appointment_date']);
$time = formatTime($appointment['appointment_time']);
$treatment = $appointment['treatment_type'];
$doctor = $appointment['doctor_name'];

$messageBody = "Dear {$appointment['patient_name']},\n\n"
             . "This is a reminder for your appointment on {$date} at {$time}.\n"
             . "Doctor: Dr. {$doctor}\n"
             . "Treatment: {$treatment}\n\n"
             . "Please arrive 15 minutes early. If you need to reschedule, please contact us.\n\n"
             . "Thank you,\nDental Clinic Team";

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
         VALUES (?, 'appointment_reminder', ?, ?, 'whatsapp', 'sent', NOW(), ?, ?)",
        [
            $appointment['patient_id'],
            'Appointment Reminder',
            $messageBody,
            Auth::userId(),
            $externalId,
        ],
        'issis'
    );
} catch (Exception $e) {
    error_log('Failed to log message: ' . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'message' => 'Reminder sent successfully (WhatsApp via local Node server).',
]);