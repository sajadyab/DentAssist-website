<?php
/**
 * API endpoint: send_reminder.php
 * Sends a WhatsApp reminder for a given appointment.
 * Expects POST JSON: { "appointment_id": 123 }
 * Returns JSON: { "success": bool, "message": string, "sid": string|null }
 */

require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Twilio SDK (must be installed via Composer)
require_once '../vendor/autoload.php';

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

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

// Validate patient phone number
$patientPhone = $appointment['patient_phone'];
if (empty($patientPhone)) {
    echo json_encode(['success' => false, 'message' => 'Patient has no phone number on file']);
    exit;
}

// Clean phone number: remove all non‑numeric characters except '+'
$cleanPhone = preg_replace('/[^0-9+]/', '', $patientPhone);
if (substr($cleanPhone, 0, 1) !== '+') {
    // If no leading '+', assume it's missing country code – prepend default (adjust as needed)
    // For Lebanon, country code is +961. You may want to detect based on your clinic's location.
    $cleanPhone = '+961' . ltrim($cleanPhone, '0'); // Example: remove leading zero if present
}
$toNumber = 'whatsapp:' . $cleanPhone;

// Twilio credentials (defined in config.php)
if (!defined('TWILIO_SID') || !defined('TWILIO_AUTH_TOKEN') || !defined('TWILIO_WHATSAPP_NUMBER')) {
    echo json_encode(['success' => false, 'message' => 'Twilio credentials not configured']);
    exit;
}

$twilioSid = TWILIO_SID;
$twilioToken = TWILIO_AUTH_TOKEN;
$fromNumber = 'whatsapp:' . TWILIO_WHATSAPP_NUMBER; // e.g., 'whatsapp:+14155238886'

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

// Initialize Twilio client
$client = new Client($twilioSid, $twilioToken);

try {
    // Send WhatsApp message
    $message = $client->messages->create(
        $toNumber,
        [
            'from' => $fromNumber,
            'body' => $messageBody
        ]
    );

    // Log the message in the database (if messages table exists)
    // If the table does not exist, we skip logging but still return success.
    // You can create the table using the SQL below.
    $messageId = $message->sid;
    $logged = false;
    try {
        $logged = $db->execute(
            "INSERT INTO messages 
             (patient_id, message_type, subject, message, delivery_method, status, sent_at, created_by, external_id)
             VALUES (?, 'appointment_reminder', ?, ?, 'whatsapp', 'sent', NOW(), ?, ?)",
            [
                $appointment['patient_id'],
                "Appointment Reminder",
                $messageBody,
                Auth::userId(),
                $messageId
            ],
            "isssis"
        );
    } catch (Exception $e) {
        // Table might not exist – we don't fail the request for that
        error_log("Failed to log message: " . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Reminder sent successfully',
        'sid' => $messageId
    ]);

} catch (TwilioException $e) {
    error_log("Twilio error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to send reminder: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Unexpected error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred'
    ]);
}