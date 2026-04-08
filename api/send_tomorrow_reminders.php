<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=UTF-8');

ini_set('display_errors', '0');
error_reporting(E_ALL);

Auth::requireLogin();

if (Auth::hasRole('patient')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$db = Database::getInstance();
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$rows = $db->fetchAll(
    "SELECT a.id,
            a.appointment_date,
            a.appointment_time,
            a.treatment_type,
            p.full_name AS patient_name,
            p.phone AS patient_phone,
            u.full_name AS doctor_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN users u ON a.doctor_id = u.id
     WHERE a.appointment_date = ?
       AND a.status NOT IN ('cancelled', 'completed', 'no-show')
     ORDER BY a.appointment_time ASC, a.id ASC",
    [$tomorrow],
    's'
);

$clinicName = defined('SITE_NAME') ? trim(strip_tags((string) SITE_NAME)) : 'Dental Clinic';
if ($clinicName === '') {
    $clinicName = 'Dental Clinic';
}

$sent = 0;
$skippedNoPhone = 0;
$failed = [];

foreach ($rows as $row) {
    $phone = trim((string) ($row['patient_phone'] ?? ''));
    if ($phone === '') {
        $skippedNoPhone++;
        continue;
    }

    $patientName = (string) ($row['patient_name'] ?? 'Patient');
    $dateStr = formatDate($row['appointment_date']);
    $timeStr = formatTime($row['appointment_time']);
    $doctor = (string) ($row['doctor_name'] ?? '');
    $treatment = (string) ($row['treatment_type'] ?? '');

    $message = "Dear {$patientName},\n\n"
        . "This is a reminder that you have an appointment tomorrow ({$dateStr}) at {$timeStr}.\n"
        . "Doctor: Dr. {$doctor}\n"
        . "Treatment: {$treatment}\n\n"
        . "Please arrive 15 minutes early. If you need to reschedule, please contact us.\n\n"
        . "Thank you,\n{$clinicName}";

    $sendResult = sendWhatsapp($phone, $message);
    if ($sendResult['ok']) {
        $sent++;
        try {
            $db->execute(
                'UPDATE appointments SET reminder_sent_24h = TRUE, reminder_sent_at = NOW() WHERE id = ?',
                [(int) $row['id']],
                'i'
            );
        } catch (Exception $e) {
            error_log('send_tomorrow_reminders: could not update reminder flags: ' . $e->getMessage());
        }
    } else {
        $failed[] = [
            'appointment_id' => (int) $row['id'],
            'patient' => $patientName,
            'error' => (string) ($sendResult['error'] ?? 'Send failed'),
        ];
    }
}

$total = count($rows);
$attempted = $total - $skippedNoPhone;
$success = $total === 0 || $sent > 0;
if ($total > 0 && $attempted > 0 && $sent === 0) {
    $success = false;
}

$parts = [];
$parts[] = "Tomorrow: {$tomorrow}";
$parts[] = "Appointments: {$total}";
$parts[] = "WhatsApp sent: {$sent}";
if ($skippedNoPhone > 0) {
    $parts[] = "Skipped (no phone): {$skippedNoPhone}";
}
if (count($failed) > 0) {
    $parts[] = 'Failed: ' . count($failed);
}

echo json_encode([
    'success' => $success,
    'message' => implode('. ', $parts) . '.',
    'tomorrow' => $tomorrow,
    'total_appointments' => $total,
    'sent' => $sent,
    'skipped_no_phone' => $skippedNoPhone,
    'failed' => $failed,
]);
