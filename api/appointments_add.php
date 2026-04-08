<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');
api_require_login();

// Patients should not be able to add appointments via staff interface
if (Auth::hasRole('patient')) {
    api_error('Forbidden.', 403);
}

$patientId = (int) ($_POST['patient_id'] ?? 0);
$doctorId = (int) ($_POST['doctor_id'] ?? 0);
$appointmentDate = (string) ($_POST['appointment_date'] ?? '');
$appointmentTime = (string) ($_POST['appointment_time'] ?? '');
$duration = (int) ($_POST['duration'] ?? 30);
$treatmentType = trim((string) ($_POST['treatment_type'] ?? ''));
$description = $_POST['description'] ?? null;
$chairNumber = $_POST['chair_number'] ?? null;
$notes = $_POST['notes'] ?? null;
$saveAndNew = isset($_POST['save_and_new']);

if ($patientId <= 0 || $doctorId <= 0 || $appointmentDate === '' || $appointmentTime === '' || $treatmentType === '') {
    api_error('Please fill in all required fields.', 422);
}

if ($chairNumber !== null && $chairNumber !== '') {
    $chairNumber = (int) $chairNumber;
} else {
    $chairNumber = null;
}

$existing = repo_appointment_find_active_chair_conflict(
    $appointmentDate,
    $appointmentTime,
    $chairNumber,
    null
);
if ($existing) {
    api_error('This time slot is already booked for the selected chair', 409);
}

$appointmentId = repo_appointment_insert_staff_scheduled([
    'patient_id' => $patientId,
    'doctor_id' => $doctorId,
    'appointment_date' => $appointmentDate,
    'appointment_time' => $appointmentTime,
    'duration' => $duration,
    'treatment_type' => $treatmentType,
    'description' => $description,
    'chair_number' => $chairNumber,
    'notes' => $notes,
    'created_by' => (int) Auth::userId(),
]);

if (!$appointmentId) {
    api_error('Error scheduling appointment', 500);
}

logAction('CREATE', 'appointments', (int) $appointmentId, null, $_POST);

$db = Database::getInstance();
$patientData = $db->fetchOne('SELECT user_id FROM patients WHERE id = ?', [$patientId], 'i');
if ($patientData && !empty($patientData['user_id'])) {
    sendNotification(
        (int) $patientData['user_id'],
        'appointment_reminder',
        'Appointment Scheduled',
        'Your appointment has been scheduled for ' . formatDate($appointmentDate) . ' at ' . formatTime($appointmentTime)
    );
}

if ($saveAndNew) {
    api_ok(['redirect' => url('appointments/add.php?success=1')], 'Appointment scheduled successfully.');
}

api_ok(['redirect' => url('appointments/view.php?id=' . (int) $appointmentId)], 'Appointment scheduled successfully.');
