<?php
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireLogin();

if (Auth::hasRole('patient')) {
    header('Location: ' . SITE_URL . '/patient/index.php');
    exit;
}

$pageTitle = 'Requests & Queue';

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_appointment_request'])) {
    $reqId = (int) ($_POST['request_id'] ?? 0);
    if ($reqId <= 0) {
        $_SESSION['queue_flash_error'] = 'Invalid request.';
        header('Location: index.php');
        exit;
    }

    $req = $db->fetchOne(
        "SELECT ar.*, p.full_name AS patient_name, p.user_id AS patient_user_id 
         FROM appointment_requests ar 
         INNER JOIN patients p ON p.id = ar.patient_id 
         WHERE ar.id = ?",
        [$reqId],
        'i'
    );

    if (!$req) {
        $_SESSION['queue_flash_error'] = 'That booking request was not found (it may have been removed already).';
        header('Location: index.php');
        exit;
    }

    $uid = (int) Auth::userId();
    $role = $_SESSION['role'] ?? '';
    if ($role === 'doctor' && (int) $req['doctor_id'] !== $uid) {
        $_SESSION['queue_flash_error'] = 'You can only approve requests assigned to you.';
        header('Location: index.php');
        exit;
    }

    $timeFull = (string) $req['requested_time'];
    if (strlen($timeFull) === 5) {
        $timeFull .= ':00';
    }
    if (!DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $req['requested_date'] . ' ' . $timeFull)) {
        $_SESSION['queue_flash_error'] = 'Could not read the requested time.';
        header('Location: index.php');
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
                treatment_type, description, chair_number, status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 'scheduled', ?, ?)",
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
            ],
            'iississsi'
        );

        if (!$apptId) {
            throw new RuntimeException('Could not save the appointment.');
        }

        if ($db->execute('DELETE FROM appointment_requests WHERE id = ?', [$reqId], 'i') < 1) {
            throw new RuntimeException('Could not remove the pending request after booking. Please try again or fix the queue entry manually.');
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $_SESSION['queue_flash_error'] = $e->getMessage();
        header('Location: index.php');
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

    $_SESSION['queue_flash_ok'] = 'Appointment saved and the patient was notified (WhatsApp when a phone number is on file).';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deny_appointment_request'])) {
    $reqId = (int) ($_POST['request_id'] ?? 0);
    if ($reqId <= 0) {
        $_SESSION['queue_flash_error'] = 'Invalid request.';
        header('Location: index.php');
        exit;
    }

    $req = $db->fetchOne(
        "SELECT ar.*, p.full_name AS patient_name, p.user_id AS patient_user_id 
         FROM appointment_requests ar 
         INNER JOIN patients p ON p.id = ar.patient_id 
         WHERE ar.id = ?",
        [$reqId],
        'i'
    );

    if (!$req) {
        $_SESSION['queue_flash_error'] = 'That booking request was not found.';
        header('Location: index.php');
        exit;
    }

    $uid = (int) Auth::userId();
    $role = $_SESSION['role'] ?? '';
    if ($role === 'doctor' && (int) $req['doctor_id'] !== $uid) {
        $_SESSION['queue_flash_error'] = 'You can only decline requests assigned to you.';
        header('Location: index.php');
        exit;
    }

    $timeFull = (string) $req['requested_time'];
    if (strlen($timeFull) === 5) {
        $timeFull .= ':00';
    }

    $doctorRow = $db->fetchOne('SELECT full_name FROM users WHERE id = ?', [(int) $req['doctor_id']], 'i');
    $doctorName = $doctorRow ? (string) $doctorRow['full_name'] : 'the clinic';
    $patientName = (string) $req['patient_name'];
    $firstName = trim(explode(' ', $patientName)[0] ?? '') ?: 'there';

    $db->beginTransaction();
    try {
        $del = $db->execute('DELETE FROM appointment_requests WHERE id = ?', [$reqId], 'i');
        if ($del < 1) {
            throw new RuntimeException('Could not remove that request. It may have been processed already.');
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $_SESSION['queue_flash_error'] = $e->getMessage();
        header('Location: index.php');
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

    $_SESSION['queue_flash_ok'] = 'Request removed and the patient was notified (WhatsApp when a phone number is on file).';
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_weekly_queue'])) {
    $wid = (int) ($_POST['weekly_queue_id'] ?? 0);
    $r = $_SESSION['role'] ?? '';
    $uid = (int) Auth::userId();
    if ($wid <= 0) {
        $_SESSION['queue_flash_error'] = 'Invalid request.';
        header('Location: index.php');
        exit;
    }
    $wq = $db->fetchOne('SELECT * FROM waiting_queue WHERE id = ? AND queue_type = ?', [$wid, 'weekly'], 'is');
    if (!$wq || ($wq['status'] ?? '') !== 'waiting') {
        $_SESSION['queue_flash_error'] = 'That weekly request was not found or is already cleared.';
        header('Location: index.php');
        exit;
    }
    if ($r === 'doctor') {
        if ((int) ($wq['doctor_id'] ?? 0) === 0) {
            $_SESSION['queue_flash_error'] = 'You cannot resolve clinic-desk entries.';
            header('Location: index.php');
            exit;
        }
        if ((int) $wq['doctor_id'] !== $uid) {
            $_SESSION['queue_flash_error'] = 'You can only resolve requests for your own patients.';
            header('Location: index.php');
            exit;
        }
    }
    $db->execute('DELETE FROM waiting_queue WHERE id = ?', [$wid], 'i');
    logAction('DELETE', 'waiting_queue', $wid, $wq, null);
    $_SESSION['queue_flash_ok'] = 'Weekly request resolved and removed from the list.';
    $returnTo = trim((string) ($_POST['return_url'] ?? ''));
    if ($returnTo !== '' && preg_match('#^(?:\.\./)*dashboard\.php(?:\?[-\w.&=%]*)?$#i', $returnTo)) {
        header('Location: ' . $returnTo);
        exit;
    }
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff_weekly_portal'])) {
    $r = $_SESSION['role'] ?? '';
    if ($r === 'patient') {
        header('Location: ../patient/index.php');
        exit;
    }
    $staffUid = (int) Auth::userId();
    $pid = (int) ($_POST['patient_id'] ?? 0);
    if ($r === 'doctor') {
        $did = $staffUid;
    } else {
        $did = (int) ($_POST['doctor_id'] ?? 0);
    }
    $pref = trim((string) ($_POST['preferred_date'] ?? ''));
    $priority = (string) ($_POST['priority'] ?? 'medium');
    $visitType = trim((string) ($_POST['visit_type'] ?? ''));
    $notes = trim((string) ($_POST['weekly_notes'] ?? ''));
    $flexDays = isset($_POST['date_flexibility_days']) && $_POST['date_flexibility_days'] !== ''
        ? max(0, min(30, (int) $_POST['date_flexibility_days']))
        : 0;
    $allowedP = ['low', 'medium', 'high', 'emergency'];
    if (!in_array($priority, $allowedP, true)) {
        $priority = 'medium';
    }
    $pRow = $db->fetchOne('SELECT full_name FROM patients WHERE id = ?', [$pid], 'i');
    $dRow = $db->fetchOne("SELECT id FROM users WHERE id = ? AND role = 'doctor'", [$did], 'i');
    if (!$pRow || !$dRow) {
        $_SESSION['queue_flash_error'] = 'Choose a valid patient and dentist.';
        header('Location: index.php');
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pref) || $visitType === '' || strlen($visitType) > 100) {
        $_SESSION['queue_flash_error'] = 'Enter a valid preferred date and visit type.';
        header('Location: index.php');
        exit;
    }
    try {
        $dayName = (new DateTimeImmutable($pref))->format('l');
    } catch (Exception $e) {
        $_SESSION['queue_flash_error'] = 'Invalid preferred date.';
        header('Location: index.php');
        exit;
    }
    $reason = substr($visitType, 0, 100);
    $notesVal = $notes !== '' ? $notes : '';
    if (dbColumnExists('waiting_queue', 'date_flexibility_days')) {
        $newWqId = (int) $db->insert(
            "INSERT INTO waiting_queue (
                patient_id, patient_name, doctor_id, queue_type, priority, reason,
                preferred_treatment, preferred_day, preferred_date, notes, date_flexibility_days, status
            ) VALUES (?, ?, ?, 'weekly', ?, ?, NULL, ?, ?, ?, ?, 'waiting')",
            [
                $pid,
                $pRow['full_name'],
                $did,
                $priority,
                $reason,
                $dayName,
                $pref,
                $notesVal,
                $flexDays,
            ],
            'isisssssi'
        );
    } else {
        $newWqId = (int) $db->insert(
            "INSERT INTO waiting_queue (
                patient_id, patient_name, doctor_id, queue_type, priority, reason,
                preferred_treatment, preferred_day, preferred_date, notes, status
            ) VALUES (?, ?, ?, 'weekly', ?, ?, NULL, ?, ?, ?, 'waiting')",
            [
                $pid,
                $pRow['full_name'],
                $did,
                $priority,
                $reason,
                $dayName,
                $pref,
                $notesVal,
            ],
            'isisssss'
        );
    }
    if ($newWqId > 0) {
        logAction('CREATE', 'waiting_queue', $newWqId, null, ['source' => 'staff_weekly_portal']);
    }
    $_SESSION['queue_flash_ok'] = 'Weekly queue request added.';
    header('Location: index.php');
    exit;
}

$role = $_SESSION['role'] ?? '';
$currentUserId = (int) Auth::userId();
$showDoctorFieldOnAddForm = ($role !== 'doctor');

// Patient portal weekly requests (dentist assigned)
$weeklyPortalSql = "SELECT wq.*, COALESCE(p.full_name, wq.patient_name) AS patient_name, p.phone AS patient_phone, u.full_name AS doctor_name
     FROM waiting_queue wq
     LEFT JOIN patients p ON wq.patient_id = p.id
     LEFT JOIN users u ON u.id = wq.doctor_id
     WHERE wq.queue_type = 'weekly' AND wq.status = 'waiting' AND wq.doctor_id IS NOT NULL";
$weeklyPortalParams = [];
$weeklyPortalTypes = '';
if ($role === 'doctor') {
    $weeklyPortalSql .= ' AND wq.doctor_id = ?';
    $weeklyPortalParams[] = $currentUserId;
    $weeklyPortalTypes = 'i';
}
$weeklyPortalSql .= ' ORDER BY wq.preferred_date IS NULL, wq.preferred_date ASC, wq.joined_at ASC';
$weeklyPortalQueue = $db->fetchAll($weeklyPortalSql, $weeklyPortalParams, $weeklyPortalTypes);

// Clinic-desk weekly entries (no dentist assigned yet) — staff only
$weeklyClinicQueue = [];
if ($role !== 'doctor') {
    $weeklyClinicQueue = $db->fetchAll(
        "SELECT wq.*, COALESCE(p.full_name, wq.patient_name) AS patient_name, p.phone AS patient_phone
         FROM waiting_queue wq
         LEFT JOIN patients p ON wq.patient_id = p.id
         WHERE wq.queue_type = 'weekly' AND wq.status = 'waiting' AND wq.doctor_id IS NULL
         ORDER BY wq.joined_at ASC",
        [],
        ''
    );
}

$appointmentRequestsSql = "SELECT ar.*, p.full_name AS patient_name, p.phone AS patient_phone, u.full_name AS doctor_name
     FROM appointment_requests ar
     INNER JOIN patients p ON p.id = ar.patient_id
     INNER JOIN users u ON u.id = ar.doctor_id";
if ($role === 'doctor') {
    $appointmentRequests = $db->fetchAll(
        $appointmentRequestsSql . ' WHERE ar.doctor_id = ? ORDER BY ar.requested_date ASC, ar.requested_time ASC, ar.id ASC',
        [$currentUserId],
        'i'
    );
} else {
    $appointmentRequests = $db->fetchAll(
        $appointmentRequestsSql . ' ORDER BY ar.requested_date ASC, ar.requested_time ASC, ar.id ASC',
        []
    );
}

$doctorSelectList = UserRepository::listDoctors(true);
$allPatientsForQueue = PatientRepository::listForSelect();

$staffWeeklyVisitTypeOptions = [
    'Check-up / cleaning',
    'Consultation',
    'Filling',
    'Crown',
    'Extraction',
    'Whitening',
    'Emergency / pain',
    'Other',
];

$queueFlashOk = $_SESSION['queue_flash_ok'] ?? '';
$queueFlashError = $_SESSION['queue_flash_error'] ?? '';
unset($_SESSION['queue_flash_ok'], $_SESSION['queue_flash_error']);

include '../layouts/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<div class="container-fluid">
    <h1 class="h3 mb-4">Requests &amp; Queue</h1>

    <?php if ($queueFlashOk !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($queueFlashOk); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($queueFlashError !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($queueFlashError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8 mb-4">
            <div class="card border-info mb-4">
                <div class="card-header bg-info text-white d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h5 class="card-title mb-0"><i class="fas fa-calendar-plus me-2"></i>Slot requests</h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <?php if (!empty($appointmentRequests)): ?>
                            <span class="badge bg-light text-info"><?php echo count($appointmentRequests); ?> </span>
                        <?php endif; ?>
                       
                    </div>
                </div>
                <div class="card-body p-0 appointments-table-wrap">
                    <?php if (empty($appointmentRequests)): ?>
                        <p class="text-muted text-center py-4 mb-0">No pending online slot requests.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <?php if ($role !== 'doctor'): ?>
                                            <th>Dentist</th>
                                        <?php endif; ?>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Visit type</th>
                                        <th>Notes</th>
                                        <th>Requested</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appointmentRequests as $ar): ?>
                                        <tr>
                                            <td>
                                                <a href="../patients/view.php?id=<?php echo (int) $ar['patient_id']; ?>" class="fw-semibold"><?php echo htmlspecialchars($ar['patient_name'] ?? ''); ?></a>
                                                <?php if (!empty($ar['patient_phone'])): ?>
                                                    <div class="text-muted small text-break"><?php echo htmlspecialchars((string) $ar['patient_phone']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($role !== 'doctor'): ?>
                                                <td class="small"><?php echo htmlspecialchars($ar['doctor_name'] ?? ''); ?></td>
                                            <?php endif; ?>
                                            <td class="small"><?php echo htmlspecialchars(formatDate($ar['requested_date'])); ?></td>
                                            <td class="small"><?php echo htmlspecialchars(formatTime($ar['requested_time'])); ?></td>
                                            <td class="small"><?php echo htmlspecialchars((string) $ar['treatment_type']); ?></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars((string) ($ar['description'] ?? '')); ?></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars((string) ($ar['created_at'] ?? '')); ?></td>
                                            <td>
                                                <div class="appointments-actions">
                                                    <form method="post" onsubmit="return confirm('Confirm this appointment and notify the patient?');">
                                                        <input type="hidden" name="request_id" value="<?php echo (int) $ar['id']; ?>">
                                                        <button type="submit" name="approve_appointment_request" class="btn btn-sm btn-success" title="Accept">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="post" onsubmit="return confirm('Decline this request and notify the patient?');">
                                                        <input type="hidden" name="request_id" value="<?php echo (int) $ar['id']; ?>">
                                                        <button type="submit" name="deny_appointment_request" class="btn btn-sm btn-danger" title="Decline">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-info mb-4">
                <div class="card-header bg-info text-white d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h5 class="card-title mb-0"><i class="fas fa-calendar-week me-2"></i>Waiting queue requests</h5>
                    <?php
                    $weeklyListCount = count($weeklyPortalQueue) + ($role !== 'doctor' ? count($weeklyClinicQueue) : 0);
                    if ($weeklyListCount > 0):
                    ?>
                        <span class="badge bg-light text-info"><?php echo $weeklyListCount; ?> </span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0 appointments-table-wrap">
                    <?php if (empty($weeklyPortalQueue)): ?>
                        <p class="text-muted text-center py-4 mb-0">No waiting queue requests with an assigned dentist.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <?php if ($role !== 'doctor'): ?>
                                            <th>Dentist</th>
                                        <?php endif; ?>
                                        <th>Preferred range</th>
                                        <th>Priority</th>
                                        <th>Visit type</th>
                                        <th>Notes</th>
                                        <th>Requested</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weeklyPortalQueue as $entry): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                if ($entry['patient_id']) {
                                                    echo '<a href="../patients/view.php?id=' . (int) $entry['patient_id'] . '" class="fw-semibold">' . htmlspecialchars($entry['patient_name'] ?? 'Unknown') . '</a>';
                                                } else {
                                                    echo htmlspecialchars($entry['patient_name'] ?? '—');
                                                }
                                                ?>
                                                <?php if (!empty($entry['patient_phone'])): ?>
                                                    <div class="text-muted small text-break"><?php echo htmlspecialchars((string) $entry['patient_phone']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <?php if ($role !== 'doctor'): ?>
                                                <td class="small"><?php echo htmlspecialchars((string) ($entry['doctor_name'] ?? '—')); ?></td>
                                            <?php endif; ?>
                                            <td class="small fw-semibold"><?php echo htmlspecialchars(formatWeeklyPreferredRange($entry)); ?></td>
                                            <td>
                                                <?php
                                                $priorityColors = ['emergency' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'];
                                                $color = $priorityColors[$entry['priority']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst((string) $entry['priority']); ?></span>
                                            </td>
                                            <td class="small"><?php echo htmlspecialchars((string) $entry['reason']); ?></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars((string) ($entry['notes'] ?? '')); ?></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars((string) ($entry['joined_at'] ?? '')); ?></td>
                                            <td>
                                                <div class="appointments-actions">
                                                    <form method="post" onsubmit="return confirm('Resolve and remove this request from the list?');">
                                                        <input type="hidden" name="weekly_queue_id" value="<?php echo (int) $entry['id']; ?>">
                                                        <button type="submit" name="resolve_weekly_queue" value="1" class="btn btn-sm btn-success" title="Resolve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($role !== 'doctor' && !empty($weeklyClinicQueue)): ?>
                        <h6 class="text-muted text-uppercase small mt-4 mb-2">Clinic desk — weekly (no dentist yet)</h6>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Preferred range</th>
                                        <th>Priority</th>
                                        <th>Visit type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weeklyClinicQueue as $entry): ?>
                                        <tr>
                                            <td>
                                                <?php
                                                if ($entry['patient_id']) {
                                                    echo '<a href="../patients/view.php?id=' . (int) $entry['patient_id'] . '" class="fw-semibold">' . htmlspecialchars($entry['patient_name'] ?? 'Unknown') . '</a>';
                                                } else {
                                                    echo htmlspecialchars($entry['patient_name'] ?? '—');
                                                }
                                                ?>
                                                <?php if (!empty($entry['patient_phone'])): ?>
                                                    <div class="text-muted small text-break"><?php echo htmlspecialchars((string) $entry['patient_phone']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small"><?php echo htmlspecialchars(formatWeeklyPreferredRange($entry)); ?></td>
                                            <td>
                                                <?php
                                                $priorityColors = ['emergency' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'];
                                                $color = $priorityColors[$entry['priority']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst((string) $entry['priority']); ?></span>
                                            </td>
                                            <td class="small"><?php echo htmlspecialchars((string) $entry['reason']); ?></td>
                                            <td>
                                                <div class="appointments-actions">
                                                    <form method="post" onsubmit="return confirm('Remove this entry?');">
                                                        <input type="hidden" name="weekly_queue_id" value="<?php echo (int) $entry['id']; ?>">
                                                        <button type="submit" name="resolve_weekly_queue" value="1" class="btn btn-sm btn-success" title="Resolve">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="form-card queue-compact-form queue-registration-card h-100 mb-0">
                <div class="card-header bg-white border-0 py-3 queue-panel-card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-plus-circle me-2 text-primary" aria-hidden="true"></i>Add to queue
                    </h5>
                   </div>
                <div class="card-body p-4">
                    <form method="post">
                        <input type="hidden" name="add_staff_weekly_portal" value="1">
                        <div class="mb-3 queue-add-patient-ts">
                            <label class="form-label-modern">
                                <i class="fas fa-user me-2" aria-hidden="true"></i>Patient *
                            </label>
                            <select name="patient_id" id="queueAddPatient" required placeholder="Search patients…">
                                <option value="">Select a patient…</option>
                                <?php foreach ($allPatientsForQueue as $p): ?>
                                    <option value="<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($showDoctorFieldOnAddForm): ?>
                            <div class="mb-3">
                                <label class="form-label-modern">
                                    <i class="fas fa-user-md me-2" aria-hidden="true"></i>Dentist *
                                </label>
                                <select class="form-select form-control-modern" name="doctor_id" required>
                                    <option value="">Select a dentist</option>
                                    <?php foreach ($doctorSelectList as $d): ?>
                                        <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['full_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label-modern">
                                <i class="fas fa-calendar-day me-2" aria-hidden="true"></i>Preferred date *
                            </label>
                            <input type="date" class="form-control form-control-modern" name="preferred_date" required
                                value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-modern">
                                <i class="fas fa-arrows-alt-h me-2" aria-hidden="true"></i>Date flexibility (optional)
                            </label>
                            <input type="number" class="form-control form-control-modern" name="date_flexibility_days" min="0" max="30" step="1" value="0" placeholder="0">
                            <div class="form-text small"> ± days before or after preferred date</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-modern">
                                <i class="fas fa-tooth me-2" aria-hidden="true"></i>Visit type *
                            </label>
                            <select class="form-select form-control-modern" name="visit_type" required>
                                <option value="">Select…</option>
                                <?php foreach ($staffWeeklyVisitTypeOptions as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-modern">
                                <i class="fas fa-chart-line me-2" aria-hidden="true"></i>Priority *
                            </label>
                            <select class="form-select form-control-modern" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-queue-reg w-100">
                           Submit Request
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const el = document.getElementById('queueAddPatient');
    if (el && typeof TomSelect !== 'undefined') {
        new TomSelect(el, {
            allowEmptyOption: true,
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Search patients…',
        });
    }
});
</script>

<?php include '../layouts/footer.php'; ?>
