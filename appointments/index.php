<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();
// only staff users may view appointment listing
if (Auth::hasRole('patient')) {
    header('Location: ../patient/index.php');
    exit;
}
$pageTitle = 'Appointments';

$db = Database::getInstance();
$role = $_SESSION['role'] ?? '';
$currentUserId = (int) Auth::userId();

$appointmentsRedirect = static function (string $anchor = '') {
    $q = [
        'date' => $_POST['filter_date'] ?? $_GET['date'] ?? date('Y-m-d'),
    ];
    $st = $_POST['filter_status'] ?? $_GET['status'] ?? '';
    $doc = $_POST['filter_doctor_id'] ?? $_GET['doctor_id'] ?? '';
    if ($st !== '') {
        $q['status'] = $st;
    }
    if ($doc !== '') {
        $q['doctor_id'] = $doc;
    }
    header('Location: index.php?' . http_build_query($q) . $anchor);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in_appointment'])) {
    if (!dbTableExists('clinic_arrivals')) {
        $_SESSION['appointments_flash_error'] = 'Clinic arrivals are not set up. Add the clinic_arrivals table from database.sql.';
        $appointmentsRedirect('#clinic-arrivals');
    }
    $apptId = (int) ($_POST['appointment_id'] ?? 0);
    $appt = $db->fetchOne('SELECT * FROM appointments WHERE id = ?', [$apptId], 'i');
    $today = date('Y-m-d');
    if (!$appt) {
        $_SESSION['appointments_flash_error'] = 'Appointment not found.';
    } elseif (($appt['appointment_date'] ?? '') !== $today) {
        $_SESSION['appointments_flash_error'] = 'Check-in is only allowed for appointments scheduled today.';
    } elseif (($appt['status'] ?? '') !== 'scheduled') {
        $_SESSION['appointments_flash_error'] = 'Only scheduled appointments can be checked in from this list.';
    } elseif ($role === 'doctor' && (int) ($appt['doctor_id'] ?? 0) !== $currentUserId) {
        $_SESSION['appointments_flash_error'] = 'You can only check in your own appointments.';
    } else {
        $dup = $db->fetchOne(
            'SELECT id FROM clinic_arrivals WHERE appointment_id = ? AND kind = ? LIMIT 1',
            [$apptId, 'scheduled'],
            'is'
        );
        if ($dup) {
            $_SESSION['appointments_flash_error'] = 'This appointment is already on the arrivals list.';
        } else {
            $timeFull = (string) ($appt['appointment_time'] ?? '');
            if (strlen($timeFull) === 5) {
                $timeFull .= ':00';
            }
            $db->beginTransaction();
            try {
                $newId = (int) $db->insert(
                    "INSERT INTO clinic_arrivals (
                        doctor_id, kind, patient_id, patient_display_name, appointment_id,
                        treatment_type, appointment_date, appointment_time, priority, created_by
                    ) VALUES (?, 'scheduled', ?, NULL, ?, ?, ?, ?, 'medium', ?)",
                    [
                        (int) $appt['doctor_id'],
                        (int) $appt['patient_id'],
                        $apptId,
                        substr((string) ($appt['treatment_type'] ?? ''), 0, 100),
                        $appt['appointment_date'],
                        $timeFull,
                        $currentUserId,
                    ],
                    'iiisssi'
                );
                if ($newId <= 0) {
                    throw new RuntimeException('Could not add arrival.');
                }
                $db->execute(
                    "UPDATE appointments SET status = 'checked-in', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status = 'scheduled'",
                    [$apptId],
                    'i'
                );
                $stRow = $db->fetchOne("SELECT status FROM appointments WHERE id = ?", [$apptId], 'i');
                if (!$stRow || ($stRow['status'] ?? '') !== 'checked-in') {
                    throw new RuntimeException('Checked in, but could not update appointment status.');
                }
                logAction('CREATE', 'clinic_arrivals', $newId, null, ['source' => 'appointment_check_in', 'appointment_id' => $apptId]);
                logAction('UPDATE', 'appointments', $apptId, null, ['status' => 'checked-in', 'via' => 'check_in']);
                $db->commit();
                $_SESSION['appointments_flash_ok'] = 'Patient checked in and added to scheduled arrivals.';
            } catch (Throwable $e) {
                $db->rollback();
                $_SESSION['appointments_flash_error'] = $e->getMessage();
            }
        }
    }
    $appointmentsRedirect('#clinic-arrivals');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_clinic_arrival'])) {
    $aid = (int) ($_POST['arrival_id'] ?? 0);
    if ($aid > 0 && dbTableExists('clinic_arrivals')) {
        $row = $db->fetchOne('SELECT * FROM clinic_arrivals WHERE id = ?', [$aid], 'i');
        if ($row) {
            if ($role === 'doctor' && (int) ($row['doctor_id'] ?? 0) !== $currentUserId) {
                $_SESSION['appointments_flash_error'] = 'You can only remove your own arrivals list entries.';
            } else {
                $db->execute('DELETE FROM clinic_arrivals WHERE id = ?', [$aid], 'i');
                logAction('DELETE', 'clinic_arrivals', $aid, $row, null);
                $_SESSION['appointments_flash_ok'] = 'Arrival removed.';
            }
        }
    }
    $appointmentsRedirect('#clinic-arrivals');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_walkin_arrival']) && dbTableExists('clinic_arrivals')) {
    $aid = (int) ($_POST['arrival_id'] ?? 0);
    if ($aid <= 0) {
        $_SESSION['appointments_flash_error'] = 'Invalid request.';
        $appointmentsRedirect('#clinic-arrivals');
    }
    $row = $db->fetchOne(
        "SELECT * FROM clinic_arrivals WHERE id = ? AND kind = 'walk_in'",
        [$aid],
        'i'
    );
    if (!$row) {
        $_SESSION['appointments_flash_error'] = 'That walk-in was not found.';
        $appointmentsRedirect('#clinic-arrivals');
    }
    if ($role === 'doctor' && (int) ($row['doctor_id'] ?? 0) !== $currentUserId) {
        $_SESSION['appointments_flash_error'] = 'You can only complete your own walk-in entries.';
        $appointmentsRedirect('#clinic-arrivals');
    }
    $pid = (int) ($row['patient_id'] ?? 0);
    if ($pid <= 0) {
        $_SESSION['appointments_flash_error'] = 'Link this walk-in to a patient before marking completed.';
        $appointmentsRedirect('#clinic-arrivals');
    }
    $docId = (int) ($row['doctor_id'] ?? 0);
    if ($docId <= 0) {
        $_SESSION['appointments_flash_error'] = 'This walk-in has no dentist assigned.';
        $appointmentsRedirect('#clinic-arrivals');
    }

    $reason = trim((string) ($row['reason'] ?? ''));
    $treatmentType = $reason !== '' ? substr($reason, 0, 100) : 'Walk-in visit';

    $arrivedRaw = (string) ($row['arrived_at'] ?? '');
    $apptDate = date('Y-m-d');
    $apptTime = date('H:i:s');
    if ($arrivedRaw !== '') {
        $ts = strtotime($arrivedRaw);
        if ($ts !== false) {
            $apptDate = date('Y-m-d', $ts);
            $apptTime = date('H:i:s', $ts);
        }
    }

    $db->beginTransaction();
    try {
        $newApptId = (int) $db->insert(
            "INSERT INTO appointments (
                patient_id, doctor_id, appointment_date, appointment_time,
                duration, treatment_type, description, status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?)",
            [
                $pid,
                $docId,
                $apptDate,
                $apptTime,
                30,
                $treatmentType,
                'Recorded from walk-in clinic arrival.',
                $currentUserId,
            ],
            'iississi'
        );
        if ($newApptId <= 0) {
            throw new RuntimeException('Could not record completed visit.');
        }
        $db->execute('DELETE FROM clinic_arrivals WHERE id = ?', [$aid], 'i');
        logAction('CREATE', 'appointments', $newApptId, null, ['via' => 'walk_in_completed', 'clinic_arrival_id' => $aid, 'status' => 'completed']);
        logAction('DELETE', 'clinic_arrivals', $aid, $row, null);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $_SESSION['appointments_flash_error'] = $e->getMessage();
        $appointmentsRedirect('#clinic-arrivals');
    }
    $wa = notifyPatientPostTreatmentInstructionsOnCompleted($newApptId);
    if (!empty($wa['skipped_whatsapp'])) {
        $_SESSION['appointments_flash_ok'] = 'Appointment marked completed. ' . ($wa['message'] ?? 'No WhatsApp sent (no matching treatment instructions).');
    } elseif (!empty($wa['ok'])) {
        $_SESSION['appointments_flash_ok'] = $wa['message'] ?? 'Appointment completed; post-treatment WhatsApp sent.';
    } else {
        $_SESSION['appointments_flash_ok'] = 'Appointment marked completed. ' . ($wa['message'] ?? '') . ($wa['error'] ? ' ' . $wa['error'] : '');
    }
    $appointmentsRedirect('#clinic-arrivals');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_scheduled_arrival'])) {
    $aid = (int) ($_POST['arrival_id'] ?? 0);
    if ($aid <= 0 || !dbTableExists('clinic_arrivals')) {
        $_SESSION['appointments_flash_error'] = 'Invalid request.';
        $appointmentsRedirect('#clinic-arrivals');
    }
    $row = $db->fetchOne(
        "SELECT * FROM clinic_arrivals WHERE id = ? AND kind = 'scheduled'",
        [$aid],
        'i'
    );
    if (!$row) {
        $_SESSION['appointments_flash_error'] = 'That scheduled arrival was not found.';
        $appointmentsRedirect('#clinic-arrivals');
    }
    if ($role === 'doctor' && (int) ($row['doctor_id'] ?? 0) !== $currentUserId) {
        $_SESSION['appointments_flash_error'] = 'You can only complete your own arrivals list entries.';
        $appointmentsRedirect('#clinic-arrivals');
    }
    $apptId = (int) ($row['appointment_id'] ?? 0);
    if ($apptId <= 0) {
        $_SESSION['appointments_flash_error'] = 'This arrival is not linked to an appointment. Use Remove instead.';
        $appointmentsRedirect('#clinic-arrivals');
    }
    $db->beginTransaction();
    try {
        $aff = $db->execute(
            "UPDATE appointments SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND status NOT IN ('cancelled', 'completed')",
            [$apptId],
            'i'
        );
        if ($aff < 1) {
            throw new RuntimeException('Could not mark that appointment completed (it may already be completed or cancelled).');
        }
        $db->execute('DELETE FROM clinic_arrivals WHERE id = ?', [$aid], 'i');
        logAction('UPDATE', 'appointments', $apptId, null, ['status' => 'completed', 'via' => 'clinic_arrivals']);
        logAction('DELETE', 'clinic_arrivals', $aid, $row, null);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $_SESSION['appointments_flash_error'] = $e->getMessage();
        $appointmentsRedirect('#clinic-arrivals');
    }
    $wa = notifyPatientPostTreatmentInstructionsOnCompleted($apptId);
    if (!empty($wa['skipped_whatsapp'])) {
        $_SESSION['appointments_flash_ok'] = 'Appointment marked completed. ' . ($wa['message'] ?? 'No WhatsApp sent (no matching treatment instructions).');
    } elseif (!empty($wa['ok'])) {
        $_SESSION['appointments_flash_ok'] = $wa['message'] ?? 'Appointment completed; post-treatment WhatsApp sent.';
    } else {
        $_SESSION['appointments_flash_ok'] = 'Appointment marked completed. ' . ($wa['message'] ?? '') . ($wa['error'] ? ' ' . $wa['error'] : '');
    }
    $appointmentsRedirect('#clinic-arrivals');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_walkin_arrival']) && dbTableExists('clinic_arrivals')) {
    $pid = (int) ($_POST['walkin_patient_id'] ?? 0);
    $reason = trim((string) ($_POST['walkin_reason'] ?? ''));
    $priority = (string) ($_POST['walkin_priority'] ?? 'medium');
    $docId = $role === 'doctor' ? $currentUserId : (int) ($_POST['walkin_doctor_id'] ?? 0);
    $allowedP = ['low', 'medium', 'high', 'emergency'];
    if (!in_array($priority, $allowedP, true)) {
        $priority = 'medium';
    }
    if ($pid <= 0) {
        $_SESSION['appointments_flash_error'] = 'Select a patient for the walk-in.';
    } elseif ($reason === '') {
        $_SESSION['appointments_flash_error'] = 'Reason is required for walk-ins.';
    } elseif ($role !== 'doctor' && $docId <= 0) {
        $_SESSION['appointments_flash_error'] = 'Select a dentist.';
    } else {
        $newId = (int) $db->insert(
            "INSERT INTO clinic_arrivals (
                doctor_id, kind, patient_id, patient_display_name, reason, priority, created_by
            ) VALUES (?, 'walk_in', ?, NULL, ?, ?, ?)",
            [
                $docId,
                $pid,
                substr($reason, 0, 255),
                $priority,
                $currentUserId,
            ],
            'iissi'
        );
        if ($newId > 0) {
            logAction('CREATE', 'clinic_arrivals', $newId, null, ['kind' => 'walk_in']);
            $_SESSION['appointments_flash_ok'] = 'Walk-in recorded.';
        }
    }
    $appointmentsRedirect('#clinic-arrivals');
}

// Filters
$date = $_GET['date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$doctorId = $_GET['doctor_id'] ?? '';

// Get doctors for filter
$doctors = repo_user_list_doctors(false);

// Check if we have patients
$patientCount = $db->fetchOne("SELECT COUNT(*) as count FROM patients")['count'];

// Build query
$where = ["appointment_date = ?"];
$params = [$date];
$types = "s";

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($doctorId)) {
    $where[] = "doctor_id = ?";
    $params[] = $doctorId;
    $types .= "i";
}

$whereClause = implode(" AND ", $where);

// Get appointments
$appointments = $db->fetchAll(
    "SELECT a.*, 
            p.full_name as patient_name,
            p.phone as patient_phone,
            u.full_name as doctor_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN users u ON a.doctor_id = u.id
     WHERE $whereClause
     ORDER BY a.appointment_time",
    $params,
    $types
);

$todayForCheckIn = date('Y-m-d');
$checkedInAppointmentIds = [];
$scheduledArrivals = [];
$walkinArrivals = [];
$doctorSelectList = repo_user_list_doctors(true);
$allPatientsForQueue = repo_patient_list_for_select();

if (dbTableExists('clinic_arrivals')) {
    $chkRows = $db->fetchAll(
        "SELECT appointment_id FROM clinic_arrivals WHERE kind = 'scheduled' AND appointment_id IS NOT NULL",
        [],
        ''
    );
    foreach ($chkRows as $cr) {
        $apid = (int) ($cr['appointment_id'] ?? 0);
        if ($apid > 0) {
            $checkedInAppointmentIds[$apid] = true;
        }
    }

    $schedSql = "SELECT ca.*,
            p.full_name AS patient_name_join, p.phone AS patient_phone_join, p.date_of_birth AS patient_dob_join,
            p.medical_history AS patient_medical_history_join, p.allergies AS patient_allergies_join, p.current_medications AS patient_meds_join
         FROM clinic_arrivals ca
         LEFT JOIN patients p ON p.id = ca.patient_id
         WHERE ca.kind = 'scheduled'";
    $walkSql = "SELECT ca.*,
            p.full_name AS patient_name_join, p.phone AS patient_phone_join, p.date_of_birth AS patient_dob_join,
            p.medical_history AS patient_medical_history_join, p.allergies AS patient_allergies_join, p.current_medications AS patient_meds_join
         FROM clinic_arrivals ca
         LEFT JOIN patients p ON p.id = ca.patient_id
         WHERE ca.kind = 'walk_in'";
    $arParams = [];
    $arTypes = '';
    if ($role === 'doctor') {
        $schedSql .= ' AND ca.doctor_id = ?';
        $walkSql .= ' AND ca.doctor_id = ?';
        $arParams = [$currentUserId];
        $arTypes = 'i';
    }
    $schedSql .= ' ORDER BY ca.arrived_at ASC';
    $walkSql .= ' ORDER BY ca.arrived_at ASC';
    $scheduledArrivals = $db->fetchAll($schedSql, $arParams, $arTypes);
    $walkinArrivals = $db->fetchAll($walkSql, $arParams, $arTypes);
}

$appointmentsFlashOk = $_SESSION['appointments_flash_ok'] ?? '';
$appointmentsFlashError = $_SESSION['appointments_flash_error'] ?? '';
unset($_SESSION['appointments_flash_ok'], $_SESSION['appointments_flash_error']);

include '../layouts/header.php';
?>

<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 appointments-page-header">
        <h1 class="h3 appointments-page-title">Appointments</h1>
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end appointments-header-actions">
            <button type="button" class="btn btn-success" id="btnWhatsAppTomorrowReminders"
                    title="Send a WhatsApp reminder to every patient with an appointment scheduled for tomorrow">
                <i class="fab fa-whatsapp"></i> Send Tomorrow Reminders
            </button>
            <button type="button" class="btn btn-primary btn-new-appointment" onclick="window.location.href='add.php'">
                <i class="fas fa-plus"></i> New Appointment
            </button>
        </div>
    </div>

    <?php if ($appointmentsFlashOk !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($appointmentsFlashOk); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($appointmentsFlashError !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($appointmentsFlashError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($patientCount == 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            No patients found in the system. You need to <a href="../patients/add.php">add patients</a> before you can schedule appointments.
        </div>
    <?php endif; ?>
    
    <!-- Date Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-3 appointments-date-nav">
        <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' -1 day')); ?>&status=<?php echo urlencode($status); ?>&doctor_id=<?php echo urlencode($doctorId); ?>"
           class="btn btn-outline-primary btn-date-nav">
            <i class="fas fa-chevron-left"></i> <span class="d-none d-sm-inline">Previous</span><span class="d-sm-none">Prev</span>
        </a>
        <div class="appointments-date-heading mb-0">
            <div class="fw-semibold"><?php echo date('l, F j, Y', strtotime($date)); ?></div>
            <form method="get" class="appointments-date-tools mt-2">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
                <input type="hidden" name="doctor_id" value="<?php echo htmlspecialchars((string) $doctorId); ?>">
                <input type="date" class="form-control form-control-sm" name="date" value="<?php echo htmlspecialchars($date); ?>" onchange="this.form.submit()">
            </form>
        </div>
        <form method="get" class="appointments-date-tools">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>">
            <select class="form-select form-select-sm" name="doctor_id" onchange="this.form.submit()" aria-label="Filter by doctor">
                <option value="">All doctors</option>
                <?php foreach ($doctors as $doctor): ?>
                    <option value="<?php echo (int) $doctor['id']; ?>" <?php echo $doctorId == $doctor['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($doctor['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' +1 day')); ?>&status=<?php echo urlencode($status); ?>&doctor_id=<?php echo urlencode($doctorId); ?>"
           class="btn btn-outline-primary btn-date-nav">
            <span class="d-none d-sm-inline">Next</span><span class="d-sm-none">Next</span> <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    
    <!-- Appointments List -->
    <div class="card" id="appointments-day-card">
        <div class="card-body appointments-table-wrap">
            <?php if (empty($appointments)): ?>
                <p class="text-muted text-center py-4">No appointments found for this date</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Treatment</th>
                                <th>Status</th>
                                <th>Chair</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $apt): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo formatTime($apt['appointment_time']); ?></strong><br>
                                        <small class="text-muted"><?php echo $apt['duration']; ?> min</small>
                                    </td>
                                    <td>
                                        <?php $aptPid = (int) ($apt['patient_id'] ?? 0); ?>
                                        <?php if ($aptPid > 0): ?>
                                            <a href="../patients/view.php?id=<?php echo $aptPid; ?>" class="patient-name-link fw-semibold"><?php echo htmlspecialchars($apt['patient_name']); ?></a>
                                        <?php else: ?>
                                            <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong>
                                        <?php endif; ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars((string) ($apt['patient_phone'] ?? '')); ?></small>
                                    </td>
                                    <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                    <td><?php echo $apt['treatment_type']; ?></td>
                                    <td><?php echo getStatusBadge($apt['status']); ?></td>
                                    <td>
                                        <?php if ($apt['chair_number']): ?>
                                            <span class="badge bg-info">Chair <?php echo $apt['chair_number']; ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="appointments-actions">
                                            <button type="button" class="btn btn-sm btn-info"
                                                    onclick="viewAppointment(<?php echo $apt['id']; ?>)"
                                                    title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning"
                                                    onclick="editAppointment(<?php echo $apt['id']; ?>)"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php
                                            $canCheckIn = dbTableExists('clinic_arrivals')
                                                && ($apt['appointment_date'] ?? '') === $todayForCheckIn
                                                && ($apt['status'] ?? '') === 'scheduled'
                                                && empty($checkedInAppointmentIds[(int) $apt['id']]);
                                            ?>
                                            <?php if ($canCheckIn): ?>
                                                <form method="post" class="appointments-checkin-form">
                                                    <input type="hidden" name="check_in_appointment" value="1">
                                                    <input type="hidden" name="appointment_id" value="<?php echo (int) $apt['id']; ?>">
                                                    <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($date); ?>">
                                                    <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($status); ?>">
                                                    <input type="hidden" name="filter_doctor_id" value="<?php echo htmlspecialchars((string) $doctorId); ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Check in">
                                                        <i class="fas fa-sign-in-alt"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                    onclick="cancelAppointment(<?php echo $apt['id']; ?>)"
                                                    title="Cancel">
                                                <i class="fas fa-times"></i>
                                            </button>
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

    <div id="clinic-arrivals" class="mt-4 pt-3">
        <h2 class="h4 clinic-arrivals-heading">Clinic arrivals</h2>

        <?php if (!dbTableExists('clinic_arrivals')): ?>
            <div class="alert alert-warning">
                The <code>clinic_arrivals</code> table is not installed. Run the <code>clinic_arrivals</code> section from <code>database.sql</code> (or import the full schema), then refresh this page.
            </div>
        <?php else: ?>
            <div class="arrivals-narrow-wrap">
                <div class="card mb-4">
                    <div class="card-header arrivals-hdr-blue arrivals-section-header border-0">
                        <div class="arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-user-check me-2"></i>Scheduled arrivals</h5>
                            </div>
                            <div class="flex-shrink-0" style="min-width: 1px;" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body appointments-table-wrap">
                            <?php if (empty($scheduledArrivals)): ?>
                                <p class="text-muted text-center py-4 mb-0">No one checked in yet. Check in patients from the appointments table above.</p>
                            <?php else: ?>
                                <div class="arrivals-table-fit">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:20%">Patient</th>
                                                <th style="width:16%">Treatment</th>
                                                <th style="width:12%">Appointment</th>
                                                <th style="width:12%">Arrival</th>
                                                <th style="width:22%">Caution</th>
                                                <th style="width:18%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($scheduledArrivals as $sa): ?>
                                                <?php
                                                $saPhone = trim((string) ($sa['patient_phone_join'] ?? ''));
                                                ?>
                                                <?php
                                                $cautionRowSa = [
                                                    'medical_history' => $sa['patient_medical_history_join'] ?? null,
                                                    'current_medications' => $sa['patient_meds_join'] ?? null,
                                                    'allergies' => $sa['patient_allergies_join'] ?? null,
                                                ];
                                                ?>
                                                <?php $saPid = (int) ($sa['patient_id'] ?? 0); ?>
                                                <?php $saPname = (string) ($sa['patient_name_join'] ?? $sa['patient_display_name'] ?? '—'); ?>
                                                <tr data-patient-id="<?php echo $saPid; ?>">
                                                    <td>
                                                        <?php if ($saPid > 0): ?>
                                                            <a href="../patients/view.php?id=<?php echo $saPid; ?>" class="patient-name-link fw-semibold"><?php echo htmlspecialchars($saPname); ?></a>
                                                        <?php else: ?>
                                                            <strong><?php echo htmlspecialchars($saPname); ?></strong>
                                                        <?php endif; ?>
                                                        <?php if ($saPhone !== ''): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($saPhone); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars((string) ($sa['treatment_type'] ?? '')); ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars(formatTime($sa['appointment_time'] ?? '')); ?></strong>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars(formatTime($sa['arrived_at'] ?? '')); ?></strong>
                                                    </td>
                                                    <td class="caution-cell"><?php echo renderCautionBadgesHtml($cautionRowSa); ?></td>
                                                    <td>
                                                        <div class="appointments-actions">
                                                            <?php if (!empty($sa['patient_id'])): ?>
                                                                <button type="button" class="btn btn-sm btn-info" title="Safety Check" onclick="openSafetyCheck(<?php echo (int) $sa['patient_id']; ?>, this)">
                                                                    <i class="fas fa-shield-alt"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if (!empty($sa['appointment_id'])): ?>
                                                                <form method="post" class="appointments-actions-form" onsubmit="return confirm('Mark this visit completed?');">
                                                                    <input type="hidden" name="complete_scheduled_arrival" value="1">
                                                                    <input type="hidden" name="arrival_id" value="<?php echo (int) $sa['id']; ?>">
                                                                    <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($date); ?>">
                                                                    <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($status); ?>">
                                                                    <input type="hidden" name="filter_doctor_id" value="<?php echo htmlspecialchars((string) $doctorId); ?>">
                                                                    <button type="submit" class="btn btn-sm btn-success" title="Mark completed">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <form method="post" class="appointments-actions-form" onsubmit="return confirm('Remove this entry from the list?');">
                                                                <input type="hidden" name="dismiss_clinic_arrival" value="1">
                                                                <input type="hidden" name="arrival_id" value="<?php echo (int) $sa['id']; ?>">
                                                                <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($date); ?>">
                                                                <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($status); ?>">
                                                                <input type="hidden" name="filter_doctor_id" value="<?php echo htmlspecialchars((string) $doctorId); ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger" title="Remove">
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
                <div class="card mb-0">
                        <div class="card-header arrivals-hdr-yellow arrivals-section-header border-0">
                            <div class="arrivals-section-header__inner align-items-center">
                                <div>
                                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                                        <i class="fas fa-walking"></i><span>Walk-ins</span>
                                    </h5>
                                </div>
                                <div class="flex-shrink-0">
                                    <button type="button" class="btn arrivals-add-walkin-btn" data-bs-toggle="modal" data-bs-target="#addWalkinModal" title="Add walk-in" aria-label="Add walk-in">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body appointments-table-wrap">
                            <?php if (empty($walkinArrivals)): ?>
                                <p class="text-muted text-center py-4 mb-0">No walk-ins on the list. Use the <strong>+</strong> button when someone arrives without an appointment.</p>
                            <?php else: ?>
                                <div class="arrivals-table-fit">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width:18%">Patient</th>
                                                <th style="width:16%">Reason</th>
                                                <th style="width:10%">Priority</th>
                                                <th style="width:10%">Arrival</th>
                                                <th style="width:7%">Age</th>
                                                <th style="width:20%">Caution</th>
                                                <th style="width:19%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($walkinArrivals as $wa): ?>
                                                <?php
                                                $pname = $wa['patient_name_join'] ?? $wa['patient_display_name'] ?? '—';
                                                $waPhone = trim((string) ($wa['patient_phone_join'] ?? ''));
                                                $pcolors = ['emergency' => 'danger', 'high' => 'warning', 'medium' => 'info', 'low' => 'secondary'];
                                                $pc = $pcolors[$wa['priority'] ?? 'medium'] ?? 'secondary';
                                                ?>
                                                <?php
                                                $cautionRowWa = [
                                                    'medical_history' => $wa['patient_medical_history_join'] ?? null,
                                                    'current_medications' => $wa['patient_meds_join'] ?? null,
                                                    'allergies' => $wa['patient_allergies_join'] ?? null,
                                                ];
                                                $dob = $wa['patient_dob_join'] ?? null;
                                                $age = '—';
                                                if (!empty($dob) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dob)) {
                                                    try {
                                                        $age = (string) (new DateTimeImmutable((string) $dob))->diff(new DateTimeImmutable('today'))->y;
                                                    } catch (Throwable $e) {
                                                        $age = '—';
                                                    }
                                                }
                                                ?>
                                                <?php $waPid = (int) ($wa['patient_id'] ?? 0); ?>
                                                <tr data-patient-id="<?php echo $waPid; ?>">
                                                    <td>
                                                        <?php if ($waPid > 0): ?>
                                                            <a href="../patients/view.php?id=<?php echo $waPid; ?>" class="patient-name-link fw-semibold"><?php echo htmlspecialchars((string) $pname); ?></a>
                                                        <?php else: ?>
                                                            <strong><?php echo htmlspecialchars((string) $pname); ?></strong>
                                                        <?php endif; ?>
                                                        <?php if ($waPhone !== ''): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($waPhone); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars((string) ($wa['reason'] ?? '')); ?></td>
                                                    <td><span class="badge bg-<?php echo $pc; ?>"><?php echo ucfirst((string) ($wa['priority'] ?? '')); ?></span></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars(formatTime($wa['arrived_at'] ?? '')); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($age); ?></td>
                                                    <td class="caution-cell"><?php echo renderCautionBadgesHtml($cautionRowWa); ?></td>
                                                    <td>
                                                        <div class="appointments-actions">
                                                            <?php if (!empty($wa['patient_id'])): ?>
                                                                <button type="button" class="btn btn-sm btn-info" title="Safety Check" onclick="openSafetyCheck(<?php echo (int) $wa['patient_id']; ?>, this)">
                                                                    <i class="fas fa-shield-alt"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                            <?php if (!empty($wa['patient_id'])): ?>
                                                                <form method="post" class="appointments-actions-form" onsubmit="return confirm('Mark this visit completed?');">
                                                                    <input type="hidden" name="complete_walkin_arrival" value="1">
                                                                    <input type="hidden" name="arrival_id" value="<?php echo (int) $wa['id']; ?>">
                                                                    <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($date); ?>">
                                                                    <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($status); ?>">
                                                                    <input type="hidden" name="filter_doctor_id" value="<?php echo htmlspecialchars((string) $doctorId); ?>">
                                                                    <button type="submit" class="btn btn-sm btn-success" title="Mark completed">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                            <form method="post" class="appointments-actions-form" onsubmit="return confirm('Remove this entry?');">
                                                                <input type="hidden" name="dismiss_clinic_arrival" value="1">
                                                                <input type="hidden" name="arrival_id" value="<?php echo (int) $wa['id']; ?>">
                                                                <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($date); ?>">
                                                                <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($status); ?>">
                                                                <input type="hidden" name="filter_doctor_id" value="<?php echo htmlspecialchars((string) $doctorId); ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger" title="Remove">
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
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Appointment Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="appointmentForm">
                    <input type="hidden" id="appointmentId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Patient *</label>
                            <select class="form-select" id="patientId" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php
                                $patients = repo_patient_list_for_select();
                                foreach ($patients as $patient):
                                ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Doctor *</label>
                            <select class="form-select" id="doctorId" name="doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" id="appointmentDate" 
                                   name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time *</label>
                            <input type="time" class="form-control" id="appointmentTime" 
                                   name="appointment_time" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <select class="form-select" id="duration" name="duration">
                                <option value="15">15 minutes</option>
                                <option value="30" selected>30 minutes</option>
                                <option value="45">45 minutes</option>
                                <option value="60">60 minutes</option>
                                <option value="90">90 minutes</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Chair Number</label>
                            <input type="number" class="form-control" id="chairNumber" 
                                   name="chair_number" min="1" max="10">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Treatment Type *</label>
                            <input type="text" class="form-control" id="treatmentType" 
                                   name="treatment_type" required>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="scheduled">Scheduled</option>
                                <option value="checked-in">Checked In</option>
                                <option value="in-treatment">In Treatment</option>
                                <option value="completed">Completed</option>
                                <option value="follow-up">Follow Up</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAppointment()">Save Appointment</button>
            </div>
        </div>
    </div>
</div>

<!-- Add walk-in Modal -->
<div class="modal fade" id="addWalkinModal" tabindex="-1" aria-labelledby="addWalkinModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWalkinModalLabel"><i class="fas fa-walking me-2"></i>Add walk-in</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="add_walkin_arrival" value="1">
                    <input type="hidden" name="filter_date" value="<?php echo htmlspecialchars($date); ?>">
                    <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($status); ?>">
                    <input type="hidden" name="filter_doctor_id" value="<?php echo htmlspecialchars((string) $doctorId); ?>">
                    <div class="mb-3 queue-add-patient-ts">
                        <label class="form-label fw-semibold">Patient *</label>
                        <select name="walkin_patient_id" id="walkinPatientSelect" required placeholder="Search patients…">
                            <option value="">Select a patient…</option>
                            <?php foreach ($allPatientsForQueue as $p): ?>
                                <option value="<?php echo (int) $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($role !== 'doctor'): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Dentist *</label>
                            <select name="walkin_doctor_id" class="form-select" required>
                                <option value="">Select</option>
                                <?php foreach ($doctorSelectList as $d): ?>
                                    <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason *</label>
                        <input type="text" name="walkin_reason" class="form-control" required maxlength="255" placeholder="Reason for visit">
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Priority *</label>
                        <select name="walkin_priority" class="form-select">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record walk-in</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Safety Check Modal -->
<div class="modal fade" id="safetyCheckModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title fs-6"><i class="fas fa-shield-alt me-2 text-info"></i>Safety Check</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <form id="safetyCheckForm" class="row g-2">
                    <input type="hidden" name="patient_id" value="">

                    <div class="col-12">
                        <div class="small text-muted" id="safetyCheckStatus"></div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small mb-1">Medical history</label>
                        <div class="border rounded p-2 bg-light">
                            <?php
                            $diseaseOptions = [
                                'Cardiovascular Diseases',
                                'Hypertension',
                                'Autoimmune diseases',
                                'Immunosuppression',
                                'Diabetes',
                                'Stroke history',
                                'Osteoporosis',
                                'Epilepsy',
                            ];
                            foreach ($diseaseOptions as $i => $label):
                                $id = 'scCond' . $i;
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="conditions[]" value="<?php echo htmlspecialchars($label); ?>" id="<?php echo $id; ?>">
                                    <label class="form-check-label small" for="<?php echo $id; ?>"><?php echo htmlspecialchars($label); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small mb-1">Medications</label>
                        <div class="border rounded p-2 bg-light">
                            <?php
                            $medOptions = ['Anticoagulants', 'Steroids', 'Chemotherapy'];
                            foreach ($medOptions as $i => $label):
                                $id = 'scMed' . $i;
                            ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="medications[]" value="<?php echo htmlspecialchars($label); ?>" id="<?php echo $id; ?>">
                                    <label class="form-check-label small" for="<?php echo $id; ?>"><?php echo htmlspecialchars($label); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold small mb-1 d-block">Allergies</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="allergies" id="scAllergiesYes" value="yes">
                                <label class="form-check-label small" for="scAllergiesYes">Yes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="allergies" id="scAllergiesNo" value="no" checked>
                                <label class="form-check-label small" for="scAllergiesNo">No</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-info text-white" id="safetyCheckSaveBtn"><i class="fas fa-check me-1"></i>Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
function showAddAppointmentModal() {
    document.getElementById('modalTitle').textContent = 'New Appointment';
    document.getElementById('appointmentForm').reset();
    document.getElementById('appointmentId').value = '';
    document.getElementById('appointmentDate').value = '<?php echo $date; ?>';
    new bootstrap.Modal(document.getElementById('appointmentModal')).show();
}

function viewAppointment(id) {
    window.location.href = 'view.php?id=' + id;
}

function editAppointment(id) {
    window.location.href = 'edit.php?id=' + id;
}

function saveAppointment() {
    const form = document.getElementById('appointmentForm');
    
    // Check if form is valid
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    console.log('Sending data:', data); // Debug log
    
    // Show loading state
    const saveBtn = document.querySelector('#appointmentModal .btn-primary');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;
    
    fetch('../api/appointments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log('Response status:', response.status); // Debug log
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data); // Debug log
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('appointmentModal')).hide();
            location.reload();
        } else {
            alert('Error saving appointment: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error); // Debug log
        alert('Network error: ' + error.message);
    })
    .finally(() => {
        // Reset button state
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
}

function cancelAppointment(id) {
    const reason = prompt('Please enter cancellation reason:');
    if (reason !== null) {
        fetch('../api/appointments.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: id, reason: reason})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

document.getElementById('btnWhatsAppTomorrowReminders')?.addEventListener('click', function () {
    if (!confirm('Send a WhatsApp reminder to all patients who have an appointment tomorrow? Each message will include their appointment time.')) {
        return;
    }
    const btn = this;
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';

    fetch('../api/send_tomorrow_reminders.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json'
        }
    })
        .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
        .then(function (result) {
            const data = result.data || {};
            let detail = data.message || 'Done.';
            if (Array.isArray(data.failed) && data.failed.length) {
                detail += '\n\nFailed:\n' + data.failed.map(function (f) {
                    return '• ' + (f.patient || ('ID ' + f.appointment_id)) + ': ' + (f.error || 'error');
                }).join('\n');
            }
            alert(detail);
        })
        .catch(function () {
            alert('Network error. Could not send reminders.');
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
});

let safetyCheckReturnEl = null;

function openSafetyCheck(patientId, btnEl) {
    safetyCheckReturnEl = btnEl;
    const modalEl = document.getElementById('safetyCheckModal');
    const form = document.getElementById('safetyCheckForm');
    const statusEl = document.getElementById('safetyCheckStatus');
    if (!modalEl || !form) return;

    form.reset();
    form.querySelector('input[name="patient_id"]').value = String(patientId);
    statusEl.textContent = 'Loading…';

    fetch('../api/patient_safety.php?patient_id=' + encodeURIComponent(patientId), { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Could not load patient safety data.');
            statusEl.textContent = '';

            const conds = Array.isArray(data.conditions) ? data.conditions : [];
            document.querySelectorAll('#safetyCheckForm input[name="conditions[]"]').forEach(function (cb) {
                cb.checked = conds.includes(cb.value);
            });

            const meds = Array.isArray(data.medications) ? data.medications : [];
            document.querySelectorAll('#safetyCheckForm input[name="medications[]"]').forEach(function (cb) {
                cb.checked = meds.includes(cb.value);
            });

            const allergies = (data.allergies || 'no').toString().toLowerCase() === 'yes';
            document.getElementById('scAllergiesYes').checked = allergies;
            document.getElementById('scAllergiesNo').checked = !allergies;
        })
        .catch(err => {
            statusEl.textContent = err.message || 'Error loading.';
        });

    new bootstrap.Modal(modalEl).show();
}

document.getElementById('safetyCheckSaveBtn')?.addEventListener('click', function () {
    const form = document.getElementById('safetyCheckForm');
    const statusEl = document.getElementById('safetyCheckStatus');
    if (!form) return;

    const patientId = parseInt(form.querySelector('input[name="patient_id"]').value || '0', 10);
    const conditions = Array.from(form.querySelectorAll('input[name="conditions[]"]:checked')).map(cb => cb.value);
    const medications = Array.from(form.querySelectorAll('input[name="medications[]"]:checked')).map(cb => cb.value);
    const allergies = document.getElementById('scAllergiesYes').checked ? 'yes' : 'no';

    statusEl.textContent = 'Saving…';
    fetch('../api/patient_safety.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ patient_id: patientId, conditions, medications, allergies })
    })
        .then(r => r.json())
        .then(data => {
            if (!data.success) throw new Error(data.message || 'Save failed.');
            statusEl.textContent = 'Saved.';

            // Update caution badge in the row (closest tr from button)
            if (safetyCheckReturnEl) {
                const tr = safetyCheckReturnEl.closest('tr');
                const cell = tr ? tr.querySelector('.caution-cell') : null;
                if (cell && data.caution_html) {
                    cell.innerHTML = data.caution_html;
                }
            }

            bootstrap.Modal.getInstance(document.getElementById('safetyCheckModal'))?.hide();
        })
        .catch(err => {
            statusEl.textContent = err.message || 'Save error.';
        });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('walkinPatientSelect');
    if (el && typeof TomSelect !== 'undefined') {
        new TomSelect(el, {
            allowEmptyOption: false,
            create: false,
            sortField: { field: 'text', direction: 'asc' },
            placeholder: 'Search patients…',
        });
    }
});
</script>

<?php include '../layouts/footer.php'; ?>
