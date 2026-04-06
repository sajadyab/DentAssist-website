<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');
api_require_login();

// block patients from editing appointments
if (Auth::hasRole('patient')) {
    api_error('Forbidden.', 403);
}

$db = Database::getInstance();
$appointmentId = (int) ($_POST['id'] ?? 0);
if ($appointmentId <= 0) {
    api_error('Invalid appointment.', 422);
}

$appointment = $db->fetchOne(
    "SELECT a.*, p.full_name as patient_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     WHERE a.id = ?",
    [$appointmentId],
    'i'
);
if (!$appointment) {
    api_error('Appointment not found.', 404);
}

$previousStatus = (string) ($appointment['status'] ?? '');

$appointmentDate = (string) ($_POST['appointment_date'] ?? '');
$appointmentTime = (string) ($_POST['appointment_time'] ?? '');
$chairNumber = $_POST['chair_number'] ?? null;
if ($chairNumber !== null && $chairNumber !== '') {
    $chairNumber = (int) $chairNumber;
} else {
    $chairNumber = null;
}

$existing = $db->fetchOne(
    "SELECT id FROM appointments
     WHERE appointment_date = ? AND appointment_time = ? AND chair_number = ?
     AND status != 'cancelled' AND id != ?",
    [$appointmentDate, $appointmentTime, $chairNumber, $appointmentId],
    'ssii'
);
if ($existing) {
    api_error('This time slot is already booked for the selected chair', 409);
}

$payload = [
    (int) ($_POST['patient_id'] ?? 0),
    (int) ($_POST['doctor_id'] ?? 0),
    $appointmentDate,
    $appointmentTime,
    (int) ($_POST['duration'] ?? 30),
    (string) ($_POST['treatment_type'] ?? ''),
    $_POST['description'] ?? null,
    $chairNumber,
    (string) ($_POST['status'] ?? 'scheduled'),
    $_POST['notes'] ?? null,
    $appointmentId,
];

$result = $db->execute(
    "UPDATE appointments SET
        patient_id = ?, doctor_id = ?, appointment_date = ?, appointment_time = ?,
        duration = ?, treatment_type = ?, description = ?, chair_number = ?, status = ?, notes = ?
     WHERE id = ?",
    $payload,
    'iississsssi'
);

if ($result === false) {
    api_error('Error updating appointment', 500);
}

logAction('UPDATE', 'appointments', $appointmentId, $appointment, $_POST);

$newStatus = (string) ($_POST['status'] ?? 'scheduled');
$message = 'Appointment updated successfully';
if ($newStatus === 'completed' && $previousStatus !== 'completed') {
    $whatsappNotifyResult = notifyPatientPostTreatmentInstructionsOnCompleted($appointmentId);
    if (!empty($whatsappNotifyResult['message'])) {
        $message .= ' (WhatsApp: ' . (string) $whatsappNotifyResult['message'] . ')';
    }
}

api_ok(['reload' => true], $message);

