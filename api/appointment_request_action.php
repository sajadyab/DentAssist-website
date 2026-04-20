<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if (Auth::hasRole('patient')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!dbTableExists('appointment_requests')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Requests are not available']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action = (string) ($data['action'] ?? '');
$reqId = (int) ($data['request_id'] ?? 0);

if ($reqId <= 0 || !in_array($action, ['approve', 'decline'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$db = Database::getInstance();
$role = (string) ($_SESSION['role'] ?? '');
$uid = (int) Auth::userId();

$req = $db->fetchOne(
    "SELECT ar.*, p.full_name AS patient_name, p.user_id AS patient_user_id 
     FROM appointment_requests ar 
     INNER JOIN patients p ON p.id = ar.patient_id 
     WHERE ar.id = ?",
    [$reqId],
    'i'
);

if (!$req) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Request not found']);
    exit;
}

if ($role === 'doctor' && (int) $req['doctor_id'] !== $uid) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not allowed for this request']);
    exit;
}

$timeFull = (string) $req['requested_time'];
if (strlen($timeFull) === 5) {
    $timeFull .= ':00';
}

if ($action === 'approve') {
    if (!DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $req['requested_date'] . ' ' . $timeFull)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Could not read the requested time']);
        exit;
    }

    $db->beginTransaction();
    try {
        $notes = 'Confirmed from patient portal request.';
        if (!empty($req['description'])) {
            $notes .= ' ' . $req['description'];
        }

        $apptId = $db->insert(
            "INSERT INTO appointments (
                patient_id, doctor_id, appointment_date, appointment_time, duration,
                treatment_type, description, chair_number, status, notes, created_by, sync_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 'scheduled', ?, ?, ?)",
            [
                (int) $req['patient_id'],
                (int) $req['doctor_id'],
                $req['requested_date'],
                $timeFull,
                (int) $req['duration_minutes'],
                $req['treatment_type'],
                $req['description'] !== null && $req['description'] !== '' ? $req['description'] : null,
                $notes,
                $uid,
                'pending',
            ],
            'iississsis'
        );

        if (!$apptId) {
            throw new RuntimeException('Could not save the appointment.');
        }
        sync_push_row_now('appointments', (int) $apptId);

        if ($db->execute('DELETE FROM appointment_requests WHERE id = ?', [$reqId], 'i') < 1) {
            throw new RuntimeException('Could not remove the pending request after booking.');
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    $doctorRow = $db->fetchOne('SELECT full_name FROM users WHERE id = ?', [(int) $req['doctor_id']], 'i');
    $doctorName = $doctorRow ? (string) $doctorRow['full_name'] : 'your dentist';
    $patientName = (string) $req['patient_name'];
    $firstName = trim(explode(' ', $patientName)[0] ?? '') ?: 'there';

    $phone = getPatientWhatsappDigitsByPatientId($db, (int) $req['patient_id']);
    $waMsg = buildAppointmentRequestAcceptedWhatsappMessage(
        $firstName,
        $doctorName,
        formatDate($req['requested_date']),
        formatTime($timeFull),
        (int) $req['duration_minutes'],
        (string) $req['treatment_type']
    );
    if ($phone !== '') {
        sendWhatsapp($phone, $waMsg);
    }

    if (!empty($req['patient_user_id'])) {
        $body = 'Your appointment is confirmed for ' . formatDate($req['requested_date'])
            . ' at ' . formatTime($timeFull) . ' with Dr. ' . $doctorName . ' (' . $req['treatment_type'] . ').';
        sendNotification(
            (int) $req['patient_user_id'],
            'appointment_reminder',
            'Appointment confirmed',
            $body,
            'in-app',
            (int) $apptId,
            null
        );
    }

    logAction('CREATE', 'appointments', (int) $apptId, null, [
        'patient_id' => (int) $req['patient_id'],
        'doctor_id' => (int) $req['doctor_id'],
        'appointment_date' => $req['requested_date'],
        'appointment_time' => $timeFull,
        'source' => 'appointment_request_approved',
    ]);

    echo json_encode(['success' => true, 'appointment_id' => (int) $apptId]);
    exit;
}

// decline
$doctorRow = $db->fetchOne('SELECT full_name FROM users WHERE id = ?', [(int) $req['doctor_id']], 'i');
$doctorName = $doctorRow ? (string) $doctorRow['full_name'] : 'the clinic';
$patientName = (string) $req['patient_name'];
$firstName = trim(explode(' ', $patientName)[0] ?? '') ?: 'there';

$db->beginTransaction();
try {
    $del = $db->execute('DELETE FROM appointment_requests WHERE id = ?', [$reqId], 'i');
    if ($del < 1) {
        throw new RuntimeException('Could not remove that request.');
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

logAction('DELETE', 'appointment_requests', $reqId, $req, null);

$phone = getPatientWhatsappDigitsByPatientId($db, (int) $req['patient_id']);
$waMsg = buildAppointmentRequestDeclinedWhatsappMessage(
    $firstName,
    $doctorName,
    formatDate($req['requested_date']),
    formatTime($timeFull)
);
if ($phone !== '') {
    sendWhatsapp($phone, $waMsg);
}

if (!empty($req['patient_user_id'])) {
    sendNotification(
        (int) $req['patient_user_id'],
        'appointment_reminder',
        'Appointment request update',
        'Your requested time on ' . formatDate($req['requested_date']) . ' could not be confirmed. Please pick another slot or contact the clinic.',
        'in-app',
        null,
        null
    );
}

echo json_encode(['success' => true]);
