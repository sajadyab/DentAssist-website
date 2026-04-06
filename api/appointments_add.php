<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');
api_require_login();

// Patients should not be able to add appointments via staff interface
if (Auth::hasRole('patient')) {
    api_error('Forbidden.', 403);
}

$db = Database::getInstance();

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

$existing = $db->fetchOne(
    "SELECT id FROM appointments
     WHERE appointment_date = ? AND appointment_time = ? AND chair_number = ? AND status != 'cancelled'",
    [$appointmentDate, $appointmentTime, $chairNumber],
    'ssi'
);
if ($existing) {
    api_error('This time slot is already booked for the selected chair', 409);
}

$appointmentId = $db->insert(
    "INSERT INTO appointments (
        patient_id, doctor_id, appointment_date, appointment_time, duration,
        treatment_type, description, chair_number, status, notes, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    [
        $patientId,
        $doctorId,
        $appointmentDate,
        $appointmentTime,
        $duration,
        $treatmentType,
        $description,
        $chairNumber,
        'scheduled',
        $notes,
        (int) Auth::userId(),
    ],
    'iississsssi'
);

if (!$appointmentId) {
    api_error('Error scheduling appointment', 500);
}

logAction('CREATE', 'appointments', (int) $appointmentId, null, $_POST);

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

