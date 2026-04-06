<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');
api_require_login();

// block patients from editing appointments
if (Auth::hasRole('patient')) {
    api_error('Forbidden.', 403);
}

$appointmentId = (int) ($_POST['id'] ?? 0);
if ($appointmentId <= 0) {
    api_error('Invalid appointment.', 422);
}

$appointment = repo_appointment_find_by_id_with_patient_name($appointmentId);
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

$existing = repo_appointment_find_active_chair_conflict(
    $appointmentDate,
    $appointmentTime,
    $chairNumber,
    $appointmentId
);
if ($existing) {
    api_error('This time slot is already booked for the selected chair', 409);
}

$ok = repo_appointment_update_staff($appointmentId, [
    'patient_id' => (int) ($_POST['patient_id'] ?? 0),
    'doctor_id' => (int) ($_POST['doctor_id'] ?? 0),
    'appointment_date' => $appointmentDate,
    'appointment_time' => $appointmentTime,
    'duration' => (int) ($_POST['duration'] ?? 30),
    'treatment_type' => (string) ($_POST['treatment_type'] ?? ''),
    'description' => $_POST['description'] ?? null,
    'chair_number' => $chairNumber,
    'status' => (string) ($_POST['status'] ?? 'scheduled'),
    'notes' => $_POST['notes'] ?? null,
]);

if (!$ok) {
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
