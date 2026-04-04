<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
if ($_SESSION['role'] != 'patient') {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$userId = Auth::userId();
$patientId = getPatientIdFromUserId($userId);

if (!$patientId) {
    die('Patient record not found.');
}

$error = '';
$success = '';

$patient = $db->fetchOne('SELECT full_name FROM patients WHERE id = ?', [$patientId], 'i');
$calendarConfig = getClinicBookingCalendarConfig($db);
$slotMinutes = $calendarConfig['slot_minutes'];
$hoursConfig = $calendarConfig['hours'];

$doctors = $db->fetchAll(
    "SELECT id, full_name FROM users WHERE role = 'doctor' AND COALESCE(is_active, 1) = 1 ORDER BY full_name"
);

$visitTypeOptions = [
    'Check-up / cleaning',
    'Consultation',
    'Filling',
    'Crown',
    'Extraction',
    'Whitening',
    'Emergency / pain',
    'Other',
];

$doctorId = isset($_GET['doctor_id']) ? (int) $_GET['doctor_id'] : 0;
$weekOffset = isset($_GET['week']) ? (int) $_GET['week'] : 0;
if ($weekOffset < -104 || $weekOffset > 104) {
    $weekOffset = 0;
}

if ($doctorId <= 0 && !empty($doctors)) {
    $doctorId = (int) $doctors[0]['id'];
}

$selectedDoctor = null;
if ($doctorId > 0) {
    foreach ($doctors as $d) {
        if ((int) $d['id'] === $doctorId) {
            $selectedDoctor = $d;
            break;
        }
    }
}

$today = new DateTimeImmutable('today');
$monday = $today->modify('monday this week')->modify(($weekOffset * 7) . ' days');
$weekEnd = $monday->modify('+6 days');
$weekStartStr = $monday->format('Y-m-d');
$weekEndStr = $weekEnd->format('Y-m-d');

/**
 * Returns true if doctor has a blocking appointment overlapping [slotStart, slotEnd) on date.
 */
function patientQueueSlotIsBooked(Database $db, int $doctorId, string $dateYmd, string $slotStartHis, string $slotEndHis): bool
{
    $row = $db->fetchOne(
        "SELECT id FROM appointments 
         WHERE doctor_id = ? AND appointment_date = ? 
         AND status NOT IN ('cancelled', 'no-show')
         AND appointment_time < ? 
         AND ADDTIME(appointment_time, SEC_TO_TIME(duration * 60)) > ? 
         LIMIT 1",
        [$doctorId, $dateYmd, $slotEndHis, $slotStartHis],
        'isss'
    );

    return $row !== null;
}

/**
 * True if confirmed appointment or pending patient request overlaps [slotStart, slotEnd).
 */
function patientQueueSlotConflict(Database $db, int $doctorId, string $dateYmd, string $slotStartHis, string $slotEndHis): bool
{
    if (patientQueueSlotIsBooked($db, $doctorId, $dateYmd, $slotStartHis, $slotEndHis)) {
        return true;
    }

    $row = $db->fetchOne(
        "SELECT id FROM appointment_requests 
         WHERE doctor_id = ? AND requested_date = ? 
         AND requested_time < ? 
         AND ADDTIME(requested_time, SEC_TO_TIME(duration_minutes * 60)) > ? 
         LIMIT 1",
        [$doctorId, $dateYmd, $slotEndHis, $slotStartHis],
        'isss'
    );

    return $row !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $postDoctor = (int) ($_POST['doctor_id'] ?? 0);
    $postDate = trim((string) ($_POST['appointment_date'] ?? ''));
    $postTime = trim((string) ($_POST['appointment_time'] ?? ''));
    $postDuration = (int) ($_POST['duration'] ?? $slotMinutes);
    $treatmentType = trim((string) ($_POST['treatment_type'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    $validDoctor = false;
    foreach ($doctors as $d) {
        if ((int) $d['id'] === $postDoctor) {
            $validDoctor = true;
            break;
        }
    }

    if (!$validDoctor) {
        $error = 'Please choose a valid dentist.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate)) {
        $error = 'Invalid appointment date.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $postTime)) {
        $error = 'Invalid time.';
    } elseif ((int) $postDuration !== (int) $slotMinutes) {
        $error = 'Invalid slot length.';
    } elseif ($treatmentType === '' || strlen($treatmentType) > 100) {
        $error = 'Please choose a visit type.';
    } else {
        $postTime .= ':00';
        $dayN = (int) (new DateTimeImmutable($postDate))->format('N');
        $band = clinicHoursBandForWeekdayN($dayN, $hoursConfig);
        if ($band === null) {
            $error = 'The clinic is closed on that day.';
        } else {
            $openT = DateTimeImmutable::createFromFormat('Y-m-d H:i', $postDate . ' ' . $band['open']);
            $closeT = DateTimeImmutable::createFromFormat('Y-m-d H:i', $postDate . ' ' . $band['close']);
            $slotStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $postDate . ' ' . $postTime);
            if (!$openT || !$closeT || !$slotStart) {
                $error = 'Could not read date or time.';
            } else {
                $slotEnd = $slotStart->modify('+' . $slotMinutes . ' minutes');
                if ($slotStart < $openT || $slotEnd > $closeT) {
                    $error = 'That time is outside clinic hours.';
                } else {
                    $now = new DateTimeImmutable('now');
                    if ($slotStart < $now) {
                        $error = 'You cannot book a time in the past.';
                    } elseif (patientQueueSlotConflict($db, $postDoctor, $postDate, $slotStart->format('H:i:s'), $slotEnd->format('H:i:s'))) {
                        $error = 'That slot is no longer available. Please pick another time.';
                    } else {
                        $slotStartHis = $slotStart->format('H:i:s');
                        $slotEndHis = $slotEnd->format('H:i:s');
                        $bookError = '';
                        $requestId = 0;
                        $db->beginTransaction();
                        try {
                            if (patientQueueSlotConflict($db, $postDoctor, $postDate, $slotStartHis, $slotEndHis)) {
                                throw new RuntimeException('That slot is no longer available. Please pick another time.');
                            }
                            $dup = $db->fetchOne(
                                "SELECT id FROM appointment_requests 
                                 WHERE patient_id = ? AND doctor_id = ? AND requested_date = ? AND requested_time = ? 
                                 LIMIT 1",
                                [$patientId, $postDoctor, $postDate, $postTime],
                                'iiss'
                            );
                            if ($dup) {
                                throw new RuntimeException('You already have a pending request for this slot.');
                            }
                            $requestId = (int) $db->insert(
                                "INSERT INTO appointment_requests (
                                    patient_id, doctor_id, requested_date, requested_time, duration_minutes,
                                    treatment_type, description
                                ) VALUES (?, ?, ?, ?, ?, ?, ?)",
                                [
                                    $patientId,
                                    $postDoctor,
                                    $postDate,
                                    $postTime,
                                    $slotMinutes,
                                    $treatmentType,
                                    $description !== '' ? $description : null,
                                ],
                                'iississ'
                            );

                            if ($requestId <= 0) {
                                throw new RuntimeException('Could not submit your request. Please try again.');
                            }
                            $db->commit();
                        } catch (RuntimeException $e) {
                            $db->rollback();
                            $bookError = $e->getMessage();
                        } catch (Throwable $e) {
                            $db->rollback();
                            $bookError = 'Could not submit your request. Please try again.';
                        }

                        if ($bookError !== '') {
                            $error = $bookError;
                        } else {
                            logAction('CREATE', 'appointment_requests', $requestId, null, [
                                'patient_id' => $patientId,
                                'doctor_id' => $postDoctor,
                                'requested_date' => $postDate,
                                'requested_time' => $postTime,
                                'treatment_type' => $treatmentType,
                                'source' => 'patient_queue',
                            ]);
                            sendNotification(
                                $userId,
                                'appointment_reminder',
                                'Appointment request sent',
                                'Your dentist will review your request for ' . formatDate($postDate) . ' at ' . formatTime($postTime) . '. You will be notified when they respond.',
                                'in-app',
                                null,
                                null
                            );
                            $docLabel = '';
                            foreach ($doctors as $d) {
                                if ((int) $d['id'] === $postDoctor) {
                                    $docLabel = (string) $d['full_name'];
                                    break;
                                }
                            }
                            $success = 'Your request was sent to Dr. ' . $docLabel
                                . '. You will receive a WhatsApp message when they accept or decline.';
                            $_POST = [];
                        }
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['weekly_queue_request']) && !isset($_POST['book_appointment'])) {
    $postDoctor = (int) ($_POST['doctor_id'] ?? 0);
    $preferredDate = trim((string) ($_POST['preferred_date'] ?? ''));
    $priority = (string) ($_POST['priority'] ?? 'medium');
    $treatmentType = trim((string) ($_POST['treatment_type'] ?? ''));
    $queueNotes = trim((string) ($_POST['queue_notes'] ?? ''));

    $validDoctor = false;
    foreach ($doctors as $d) {
        if ((int) $d['id'] === $postDoctor) {
            $validDoctor = true;
            break;
        }
    }

    $allowedPriority = ['low', 'medium', 'high', 'emergency'];
    if (!in_array($priority, $allowedPriority, true)) {
        $priority = 'medium';
    }

    if (!$validDoctor) {
        $error = 'Please choose a dentist.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate)) {
        $error = 'Please choose a valid date.';
    } elseif ($treatmentType === '' || !in_array($treatmentType, $visitTypeOptions, true)) {
        $error = 'Please select a visit type.';
    } else {
        $todayYmd = (new DateTimeImmutable('today'))->format('Y-m-d');
        if ($preferredDate < $todayYmd) {
            $error = 'Date must be today or in the future.';
        }
    }

    if ($error === '') {
        try {
            $preferredDayName = (new DateTimeImmutable($preferredDate))->format('l');
        } catch (Exception $e) {
            $error = 'Invalid date.';
            $preferredDayName = '';
        }
    }

    if ($error === '') {
        $reason = substr($treatmentType, 0, 100);
        $notesVal = $queueNotes !== '' ? $queueNotes : '';
        $flexDays = isset($_POST['date_flexibility_days']) && $_POST['date_flexibility_days'] !== ''
            ? max(0, min(30, (int) $_POST['date_flexibility_days']))
            : 0;

        if (dbColumnExists('waiting_queue', 'date_flexibility_days')) {
            $db->insert(
                "INSERT INTO waiting_queue (
                    patient_id, patient_name, doctor_id, queue_type, priority, reason,
                    preferred_treatment, preferred_day, preferred_date, notes, date_flexibility_days, status
                ) VALUES (?, ?, ?, 'weekly', ?, ?, NULL, ?, ?, ?, ?, 'waiting')",
                [
                    $patientId,
                    $patient['full_name'],
                    $postDoctor,
                    $priority,
                    $reason,
                    $preferredDayName,
                    $preferredDate,
                    $notesVal,
                    $flexDays,
                ],
                'isisssssi'
            );
        } else {
            $db->insert(
                "INSERT INTO waiting_queue (
                    patient_id, patient_name, doctor_id, queue_type, priority, reason,
                    preferred_treatment, preferred_day, preferred_date, notes, status
                ) VALUES (?, ?, ?, 'weekly', ?, ?, NULL, ?, ?, ?, 'waiting')",
                [
                    $patientId,
                    $patient['full_name'],
                    $postDoctor,
                    $priority,
                    $reason,
                    $preferredDayName,
                    $preferredDate,
                    $notesVal,
                ],
                'isisssss'
            );
        }

        $docLabel = '';
        foreach ($doctors as $d) {
            if ((int) $d['id'] === $postDoctor) {
                $docLabel = (string) $d['full_name'];
                break;
            }
        }
        $success = 'Your request was added to the weekly queue for Dr. ' . $docLabel
            . ' on ' . formatDate($preferredDate) . '. The clinic will follow up with you.';
        $_POST = [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment_request'])) {
    $cancelId = (int) ($_POST['request_id'] ?? 0);
    if ($cancelId <= 0) {
        $error = 'Invalid request.';
    } else {
        $cancelRow = $db->fetchOne(
            'SELECT * FROM appointment_requests WHERE id = ? AND patient_id = ? LIMIT 1',
            [$cancelId, $patientId],
            'ii'
        );
        if (!$cancelRow) {
            $error = 'That request was not found or may have already been processed.';
        } else {
            $db->execute('DELETE FROM appointment_requests WHERE id = ? AND patient_id = ?', [$cancelId, $patientId], 'ii');
            logAction('DELETE', 'appointment_requests', $cancelId, $cancelRow, null);
            sendNotification(
                $userId,
                'appointment_reminder',
                'Request cancelled',
                'You cancelled your online booking request for ' . formatDate($cancelRow['requested_date']) . ' at ' . formatTime($cancelRow['requested_time']) . '.',
                'in-app',
                null,
                null
            );
            $success = 'Your pending request was cancelled.';
        }
    }
}

$busyByDate = [];
if ($selectedDoctor) {
    $busyRows = $db->fetchAll(
        "SELECT appointment_date, appointment_time, duration 
         FROM appointments 
         WHERE doctor_id = ? AND appointment_date BETWEEN ? AND ? 
         AND status NOT IN ('cancelled', 'no-show')",
        [$doctorId, $weekStartStr, $weekEndStr],
        'iss'
    );
    foreach ($busyRows as $br) {
        $d = $br['appointment_date'];
        if (!isset($busyByDate[$d])) {
            $busyByDate[$d] = [];
        }
        $busyByDate[$d][] = [
            'start' => $br['appointment_time'],
            'duration' => (int) $br['duration'],
        ];
    }
    $pendingRows = $db->fetchAll(
        "SELECT requested_date AS appointment_date, requested_time AS appointment_time, duration_minutes AS duration 
         FROM appointment_requests 
         WHERE doctor_id = ? AND requested_date BETWEEN ? AND ?",
        [$doctorId, $weekStartStr, $weekEndStr],
        'iss'
    );
    foreach ($pendingRows as $br) {
        $d = $br['appointment_date'];
        if (!isset($busyByDate[$d])) {
            $busyByDate[$d] = [];
        }
        $busyByDate[$d][] = [
            'start' => $br['appointment_time'],
            'duration' => (int) $br['duration'],
        ];
    }
}

function patientQueueSlotFreeHelper(string $dateYmd, string $timeHis, int $durationMin, array $dayBusy, DateTimeImmutable $now): string
{
    $slotStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateYmd . ' ' . $timeHis);
    if (!$slotStart) {
        return 'unavailable';
    }
    $slotEnd = $slotStart->modify('+' . $durationMin . ' minutes');
    if ($slotStart < $now) {
        return 'past';
    }
    $slotTs0 = $slotStart->getTimestamp();
    $slotTs1 = $slotEnd->getTimestamp();
    foreach ($dayBusy as $b) {
        $apptTime = (string) $b['start'];
        if (strlen($apptTime) === 5) {
            $apptTime .= ':00';
        }
        $bStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateYmd . ' ' . $apptTime);
        if (!$bStart) {
            continue;
        }
        $bEnd = $bStart->modify('+' . max(1, (int) $b['duration']) . ' minutes');
        if ($slotTs0 < $bEnd->getTimestamp() && $slotTs1 > $bStart->getTimestamp()) {
            return 'busy';
        }
    }

    return 'free';
}

$now = new DateTimeImmutable('now');
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $d = $monday->modify("+{$i} days");
    $ymd = $d->format('Y-m-d');
    $n = (int) $d->format('N');
    $band = clinicHoursBandForWeekdayN($n, $hoursConfig);
    if ($band === null) {
        continue;
    }
    $slots = [];
    $openDt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $band['open']);
    $closeDt = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ymd . ' ' . $band['close']);
    if ($openDt && $closeDt) {
        $cursor = $openDt;
        while ($cursor < $closeDt) {
            $next = $cursor->modify('+' . $slotMinutes . ' minutes');
            if ($next > $closeDt) {
                break;
            }
            $his = $cursor->format('H:i:s');
            $dayBusy = $busyByDate[$ymd] ?? [];
            $state = $selectedDoctor
                ? patientQueueSlotFreeHelper($ymd, $his, $slotMinutes, $dayBusy, $now)
                : 'unavailable';
            $slots[] = [
                'time' => $his,
                'label' => $cursor->format('g:i A'),
                'state' => $state,
            ];
            $cursor = $next;
        }
    }
    $weekDays[] = [
        'date' => $d,
        'ymd' => $ymd,
        'slots' => $slots,
    ];
}

$slotByDayTime = [];
$calendarTimeKeySet = [];
foreach ($weekDays as $col) {
    $ymd = $col['ymd'];
    $slotByDayTime[$ymd] = [];
    foreach ($col['slots'] as $sl) {
        $k = $sl['time'];
        $slotByDayTime[$ymd][$k] = $sl;
        $calendarTimeKeySet[$k] = true;
    }
}
$calendarTimeRows = array_keys($calendarTimeKeySet);
sort($calendarTimeRows, SORT_STRING);

$myPendingAppointmentRequests = $db->fetchAll(
    'SELECT ar.*, u.full_name AS doctor_name
     FROM appointment_requests ar
     INNER JOIN users u ON u.id = ar.doctor_id
     WHERE ar.patient_id = ?
     ORDER BY ar.requested_date ASC, ar.requested_time ASC, ar.id ASC',
    [$patientId],
    'i'
);
$pendingRequestsCount = count($myPendingAppointmentRequests);

$pageTitle = 'Join Queue';
include '../layouts/header.php';
?>

<style>
/* Desktop: sidebar spans both rows so “Before Your Visit” sits under the calendar (no flex-wrap gap) */
@media (min-width: 992px) {
    .queue-main-layout.row {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        column-gap: var(--bs-gutter-x, 1.5rem);
        row-gap: var(--bs-gutter-x, 1.5rem);
        align-items: start;
    }
    .queue-main-layout.row > .queue-cell-calendar {
        grid-column: 1 / span 7;
        grid-row: 1;
        width: 100% !important;
        max-width: 100% !important;
    }
    .queue-main-layout.row > .queue-cell-sidebar {
        grid-column: 8 / span 5;
        grid-row: 1 / span 2;
        width: 100% !important;
        max-width: 100% !important;
        align-self: start;
    }
    .queue-main-layout.row > .queue-cell-before {
        grid-column: 1 / span 7;
        grid-row: 2;
        width: 100% !important;
        max-width: 100% !important;
    }
}

.queue-page {
    --cal-accent: #667eea;
    --cal-accent-soft: rgba(102, 126, 234, 0.12);
    --cal-slate: #334155;
    --cal-muted: #64748b;
    --cal-line: #e2e8f0;
    /* Panel title icons (all card headers): Book a visit, Weekly queue, Queue Information, Before your visit */
    --queue-card-header-icon: rgb(244, 247, 110);
}
.queue-header {
    background: linear-gradient(135deg,rgb(108, 204, 245) 0%,rgb(108, 163, 245)100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
    position: relative;
    overflow: hidden;
}

.queue-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
    background-size: 50px 50px;
    animation: moveBackground 30s linear infinite;
}

@keyframes moveBackground {
    0% { transform: translate(0, 0); }
    100% { transform: translate(50px, 50px); }
}

.form-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.form-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 25px;
}

.form-header h4 {
    margin-bottom: 5px;
}

.queue-registration-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 8px 28px rgba(31, 105, 216, 0.12);
    overflow: hidden;
    /* Weekly queue form only (Dentist, Date, Visit type, …) — does NOT affect the “Weekly queue request” title icon */
    --queue-registration-field-icon: rgb(121, 168, 240);
    --queue-registration-label-color: rgb(8, 9, 10);
}
.queue-registration-card .card-body .form-label-modern {
    color: var(--queue-registration-label-color);
}
.queue-registration-card .card-body .form-label-modern > i {
    color: var(--queue-registration-field-icon) !important;
}
.queue-registration-card .queue-notes-field {
    min-height: 2.75rem;
    max-height: 5rem;
    resize: vertical;
    font-size: 0.875rem;
    line-height: 1.4;
    padding-top: 0.4rem;
    padding-bottom: 0.4rem;
}
/* Shared header style: matches Queue Information card */
.queue-panel-card-header {
    background: #fff !important;
    border-bottom: none !important;
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
}
.queue-panel-card-header .card-title {
    font-size: 1.25rem;
    font-weight: 500;
    color: #212529;
    line-height: 1.2;
}
.queue-panel-card-header .card-title i {
    color: var(--queue-card-header-icon) !important;
}
.queue-registration-card .queue-panel-card-header {
    border-radius: 20px 20px 0 0;
}
.queue-registration-card .form-control-modern:focus,
.queue-registration-card .form-select:focus {
    border-color: rgb(108, 163, 245);
    box-shadow: 0 0 0 3px rgba(108, 163, 245, 0.2);
}
.queue-registration-card .btn-queue-reg {
    background: linear-gradient(135deg, rgb(108, 163, 245) 0%, rgb(31, 105, 216) 100%);
    border: none;
    padding: 11px 26px;
    border-radius: 25px;
    color: #fff !important;
    font-weight: 600;
    transition: opacity 0.2s ease;
}
.queue-registration-card .btn-queue-reg:hover {
    color: #fff !important;
    opacity: 0.92;
}
.queue-registration-card .btn-cancel-reg {
    background: rgba(31, 105, 216, 0.1);
    border: 1px solid rgba(31, 105, 216, 0.25);
    padding: 11px 22px;
    border-radius: 25px;
    color: rgb(31, 105, 216) !important;
    font-weight: 600;
    text-decoration: none;
}
.queue-registration-card .btn-cancel-reg:hover {
    background: rgba(31, 105, 216, 0.16);
    color: rgb(24, 80, 165) !important;
}

.queue-pending-requests-card .queue-pending-row {
    display: flex;
    align-items: center;
    gap: 0.5rem 1rem;
    flex-wrap: nowrap;
    border: 1px solid rgba(31, 105, 216, 0.15);
    border-radius: 10px;
    padding: 10px 12px;
    background: #fafbff;
    margin-bottom: 8px;
    font-size: 0.8125rem;
}
.queue-pending-requests-card .queue-pending-row:last-child {
    margin-bottom: 0;
}
.queue-pending-requests-card .queue-pending-row-main {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    color: #475569;
}
.queue-pending-requests-card .queue-pending-row-main strong {
    color: #1e293b;
    font-weight: 600;
}
@media (max-width: 640px) {
    .queue-pending-requests-card .queue-pending-row {
        flex-wrap: wrap;
    }
    .queue-pending-requests-card .queue-pending-row-main {
        white-space: normal;
    }
}
.queue-pending-requests-card .btn-cancel-pending {
    background: rgba(220, 53, 69, 0.08);
    border: 1px solid rgba(220, 53, 69, 0.35);
    color: #b02a37 !important;
    font-weight: 600;
    border-radius: 25px;
    padding: 8px 16px;
    font-size: 0.8125rem;
}
.queue-pending-requests-card .btn-cancel-pending:hover {
    background: rgba(220, 53, 69, 0.15);
    color: #842029 !important;
}

.form-control-modern {
    border-radius: 10px;
    border: 1px solid #e0e0e0;
    padding: 12px 15px;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    outline: none;
}

.form-label-modern {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    display: block;
}

.btn-queue {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    color: white;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-queue:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102,126,234,0.4);
}

.btn-cancel {
    background: #6c757d;
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    color: white;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.alert-custom {
    border-radius: 12px;
    border: none;
    padding: 15px 20px;
}

.info-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.info-card:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.priority-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.priority-low { background: #d1ecf1; color: #0c5460; }
.priority-medium { background: #fff3cd; color: #856404; }
.priority-high { background: #f8d7da; color: #721c24; }
.priority-emergency { background: #dc3545; color: white; }

.queue-pending-stat {
    background: rgba(255,255,255,0.2);
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    backdrop-filter: blur(10px);
    max-width: 200px;
    margin-left: auto;
}

.queue-pending-stat-label {
    display: block;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    opacity: 0.9;
    margin-bottom: 0.25rem;
}

.queue-pending-stat-number {
    font-size: 2rem;
    font-weight: 800;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
}

.queue-pending-stat-hint {
    display: block;
    font-size: 0.7rem;
    opacity: 0.85;
    margin-top: 0.2rem;
}

select.form-control-modern {
    cursor: pointer;
}

select.form-control-modern option {
    padding: 10px;
}

/* Booking calendar card body only; header uses .queue-panel-card-header */
.cal-nav .btn-cal {
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.3rem 0.5rem;
    border: 1px solid var(--cal-line);
    background: #fff;
    color: var(--cal-slate);
}
.cal-nav .btn-cal:hover {
    background: var(--cal-accent-soft);
    border-color: rgba(102, 126, 234, 0.25);
    color: #4f46e5;
}
.cal-week-range {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--cal-slate);
}
.cal-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 10px;
    font-size: 0.68rem;
    color: var(--cal-muted);
    margin-bottom: 8px;
}
.cal-legend span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.cal-dot {
    width: 9px;
    height: 9px;
    border-radius: 3px;
    flex-shrink: 0;
}
.cal-dot-free {
    background: #22c55e;
    box-shadow: 0 0 0 1.5px rgba(34, 197, 94, 0.35);
}
.cal-dot-busy {
    background: #93c5fd;
    box-shadow: 0 0 0 1.5px rgba(59, 130, 246, 0.35);
}
.cal-dot-past { background: #e2e8f0; }

.queue-sidebar-calendar .booking-calendar-wrap,
.queue-main-calendar .booking-calendar-wrap {
    width: 100%;
    max-width: 100%;
    margin-left: 0;
    margin-right: 0;
}
.booking-calendar-wrap {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--cal-line);
    background: #fff;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
    -webkit-overflow-scrolling: touch;
}
.booking-calendar {
    display: grid;
    grid-template-columns: repeat(var(--cal-cols, 6), minmax(40px, 1fr));
    gap: 3px;
    padding: 5px;
    min-width: 0;
}
/* Table layout (main calendar) */
.queue-main-calendar .booking-calendar-wrap {
    padding: 0;
    overflow: visible;
    -webkit-overflow-scrolling: auto;
}
.booking-cal-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.72rem;
    table-layout: fixed;
}
.booking-cal-table thead th {
    background: linear-gradient(180deg, rgba(102, 126, 234, 0.14) 0%, rgba(118, 75, 162, 0.08) 100%);
    border-bottom: 2px solid rgba(102, 126, 234, 0.22);
    border-right: 1px solid var(--cal-line);
    color: #1e293b;
    font-weight: 700;
    text-align: center;
    vertical-align: middle;
    padding: 8px 6px;
    font-size: 0.68rem;
    letter-spacing: 0.02em;
}
.booking-cal-table thead th:first-child {
    width: 4.75rem;
    text-align: left;
    padding-left: 10px;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-right: 2px solid rgba(102, 126, 234, 0.15);
}
.booking-cal-table thead th:last-child {
    border-right: none;
}
.booking-cal-table thead th .booking-cal-th-day {
    display: block;
    text-transform: uppercase;
    font-size: 0.62rem;
    color: #475569;
}
.booking-cal-table thead th .booking-cal-th-date {
    display: block;
    font-weight: 600;
    color: var(--cal-muted);
    font-size: 0.65rem;
    margin-top: 2px;
    text-transform: none;
}
.booking-cal-table tbody td {
    border-right: 1px solid var(--cal-line);
    border-bottom: 1px solid var(--cal-line);
    vertical-align: middle;
    padding: 0;
}
.booking-cal-table tbody td:last-child {
    border-right: none;
}
.booking-cal-table tbody tr:nth-child(even) td {
    background: #fafbfd;
}
.booking-cal-table tbody tr:nth-child(even) th.cal-time-cell {
    background: #f1f5f9;
}
.booking-cal-table tbody tr:hover td:not(.cal-table-empty) {
    background: rgba(102, 126, 234, 0.04);
}
.booking-cal-table .cal-time-cell {
    background: #f8fafc;
    font-weight: 700;
    font-size: 0.68rem;
    color: #475569;
    padding: 6px 10px;
    white-space: nowrap;
    border-right: 2px solid rgba(102, 126, 234, 0.12);
}
.cal-table-cell-inner {
    padding: 5px 4px;
    text-align: center;
    min-height: 2.35rem;
}
.cal-table-empty {
    background: repeating-linear-gradient(
        -45deg,
        #f8fafc,
        #f8fafc 4px,
        #f1f5f9 4px,
        #f1f5f9 8px
    ) !important;
    color: #cbd5e1;
    font-size: 0.85rem;
    text-align: center;
    padding: 8px 4px;
}
.cal-slot-btn-free.cal-slot-btn-table {
    font-size: 1.05rem;
    line-height: 1;
    font-weight: 800;
}
.cal-slot-btn-table {
    width: 100%;
    min-height: 2.15rem;
    font-size: 0.62rem;
    font-weight: 700;
    border-radius: 6px;
    padding: 6px 4px;
    line-height: 1.15;
}
.queue-compact-form .form-header { padding: 16px 18px; }
.queue-compact-form .form-header h4 { font-size: 1rem; }
.queue-compact-form .card-body { padding: 1rem 1.25rem !important; }
.queue-compact-form .mb-4 { margin-bottom: 1rem !important; }
.queue-compact-form textarea.form-control-modern { min-height: 88px; }
.cal-col {
    border: 1px solid #ecfeff;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #fafefe;
    min-width: 0;
}
.cal-col-head {
    padding: 5px 4px 4px;
    text-align: center;
    background: linear-gradient(180deg, rgba(102, 126, 234, 0.10) 0%, rgba(118, 75, 162, 0.06) 100%);
    font-weight: 700;
    font-size: 0.62rem;
    color: #2c3e50;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    border-bottom: 1px solid rgba(102, 126, 234, 0.18);
}
.cal-col-head small {
    display: block;
    font-weight: 600;
    color: var(--cal-muted);
    margin-top: 1px;
    font-size: 0.6rem;
    letter-spacing: 0;
    text-transform: none;
}
.cal-col-body {
    padding: 4px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 3px;
    min-height: 48px;
    background: #fff;
}
.cal-slot-btn {
    width: 100%;
    border: none;
    border-radius: 5px;
    padding: 4px 2px;
    font-size: 0.6rem;
    font-weight: 700;
    line-height: 1.15;
    cursor: pointer;
    transition: background .15s ease, transform .1s ease, box-shadow .15s ease;
}
.cal-slot-btn-free {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
    box-shadow: 0 1px 2px rgba(6, 95, 70, 0.06);
}
.cal-slot-btn-free:hover {
    background: #a7f3d0;
    border-color: #34d399;
    color: #064e3b;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(16, 185, 129, 0.2);
}
.cal-slot-btn-busy {
    cursor: not-allowed;
    color: #1d4ed8;
    background: #dbeafe;
    border: 1px solid #93c5fd;
    font-weight: 600;
}
.cal-slot-btn-past {
    cursor: not-allowed;
    color: #94a3b8;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    font-weight: 600;
    opacity: 0.55;
}
.cal-slot-hint {
    text-align: center;
    color: #94a3b8;
    font-size: 0.6rem;
    padding: 8px 3px;
    line-height: 1.35;
}
#slotModal .modal-content { border-radius: 14px; border: none; box-shadow: 0 12px 40px rgba(15,23,42,.12); }
#slotModal .modal-header {
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(180deg, rgba(102, 126, 234, 0.10) 0%, #fff 100%);
    padding: 0.85rem 1rem;
}
#slotModal .modal-title { font-size: 1rem; color: #2c3e50; }
.slot-modal-summary {
    background: #f8fafc;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
}
.slot-modal-summary dt { font-size: 0.68rem; color: #64748b; text-transform: uppercase; margin-bottom: 2px; letter-spacing: 0.03em; }
.slot-modal-summary dd { margin: 0 0 10px 0; font-weight: 600; color: #1e293b; font-size: 0.9rem; }
.slot-modal-summary dd:last-child { margin-bottom: 0; }
</style>

<div class="container-fluid queue-page">
    <div class="mb-3 d-flex justify-content-end">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="queue-header">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2 class="mb-2">
                    <i class="fas fa-hourglass-half"></i> Join Waiting Queue
                </h2>
                <p class="mb-0">Book a time below or join the walk-in queue — same-day care or a future slot</p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="queue-pending-stat" role="status" aria-live="polite">
                    <span class="queue-pending-stat-label">Requests awaiting confirmation</span>
                    <div class="queue-pending-stat-number"><?php echo (int) $pendingRequestsCount; ?></div>
                    <span class="queue-pending-stat-hint"><?php echo $pendingRequestsCount === 1 ? 'online booking request' : 'online booking requests'; ?></span>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row queue-main-layout">
        <div class="col-12 queue-cell-calendar order-1">
            <div class="card border-0 shadow-sm mb-4 queue-calendar-card">
                <div class="card-header bg-white border-0 py-3 queue-panel-card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-check me-2" aria-hidden="true"></i>Book a visit
                    </h5>
                    <p class="small text-muted mb-0 mt-1">Tap <strong>+</strong> to request an appointment, you will be notified of your dentist's response via WhatsApp.</p>
                </div>
                <div class="card-body p-3 queue-main-calendar">
                    <form method="get" class="row g-2 align-items-end mb-2" id="doctorWeekForm">
                        <div class="col-sm-6 col-md-5">
                            <label class="form-label small text-muted mb-1">Dentist</label>
                            <select name="doctor_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?php echo (int) $d['id']; ?>" <?php echo (int) $d['id'] === $doctorId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($d['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-7 cal-nav d-flex flex-wrap align-items-center gap-1 gap-sm-2">
                            <input type="hidden" name="week" id="weekInput" value="<?php echo (int) $weekOffset; ?>">
                            <button type="button" class="btn btn-cal" onclick="document.getElementById('weekInput').value=<?php echo $weekOffset - 1; ?>; document.getElementById('doctorWeekForm').submit();">
                                <i class="fas fa-chevron-left"></i><span class="d-none d-sm-inline"> Prev</span>
                            </button>
                            <span class="cal-week-range px-1">
                                <?php echo htmlspecialchars($monday->format('M j')); ?> – <?php echo htmlspecialchars($weekEnd->format('M j')); ?>
                            </span>
                            <button type="button" class="btn btn-cal" onclick="document.getElementById('weekInput').value=<?php echo $weekOffset + 1; ?>; document.getElementById('doctorWeekForm').submit();">
                                <span class="d-none d-sm-inline">Next </span><i class="fas fa-chevron-right"></i>
                            </button>
                            <?php if ($weekOffset !== 0): ?>
                                <button type="button" class="btn btn-cal text-nowrap" onclick="document.getElementById('weekInput').value=0; document.getElementById('doctorWeekForm').submit();">This week</button>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if (empty($doctors)): ?>
                        <p class="text-muted small mb-0">No dentists available for online booking. Please call the clinic.</p>
                    <?php elseif (empty($weekDays)): ?>
                        <p class="text-muted small mb-0">No open days this week in the clinic schedule. Try another week.</p>
                    <?php else: ?>
                        <div class="cal-legend">
                            <span><span class="cal-dot cal-dot-free"></span> Open</span>
                            <span><span class="cal-dot cal-dot-busy"></span> Taken</span>
                            <span><span class="cal-dot cal-dot-past"></span> Past</span>
                        </div>
                        <div class="booking-calendar-wrap">
                            <?php if (empty($calendarTimeRows)): ?>
                                <div class="cal-slot-hint p-3">No times match clinic hours this week.</div>
                            <?php else: ?>
                                <table class="booking-cal-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Time</th>
                                                <?php foreach ($weekDays as $col): ?>
                                                    <th scope="col">
                                                        <span class="booking-cal-th-day"><?php echo htmlspecialchars($col['date']->format('D')); ?></span>
                                                        <span class="booking-cal-th-date"><?php echo htmlspecialchars($col['date']->format('M j')); ?></span>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($calendarTimeRows as $his): ?>
                                                <?php
                                                $rowLabel = $his;
                                                foreach ($weekDays as $col) {
                                                    if (!empty($slotByDayTime[$col['ymd']][$his])) {
                                                        $rowLabel = $slotByDayTime[$col['ymd']][$his]['label'];
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <th class="cal-time-cell" scope="row"><?php echo htmlspecialchars($rowLabel); ?></th>
                                                    <?php foreach ($weekDays as $col): ?>
                                                        <?php $ymd = $col['ymd']; ?>
                                                        <?php if (empty($slotByDayTime[$ymd][$his])): ?>
                                                            <td class="cal-table-empty" aria-hidden="true">—</td>
                                                        <?php else: ?>
                                                            <?php
                                                            $sl = $slotByDayTime[$ymd][$his];
                                                            $cls = 'cal-slot-btn-past';
                                                            if ($sl['state'] === 'free') {
                                                                $cls = 'cal-slot-btn-free';
                                                            } elseif ($sl['state'] === 'busy') {
                                                                $cls = 'cal-slot-btn-busy';
                                                            }
                                                            ?>
                                                            <td>
                                                                <div class="cal-table-cell-inner">
                                                                    <?php if ($sl['state'] === 'free'): ?>
                                                                        <button type="button"
                                                                            class="cal-slot-btn cal-slot-btn-table <?php echo $cls; ?>"
                                                                            data-date="<?php echo htmlspecialchars($col['ymd']); ?>"
                                                                            data-time="<?php echo htmlspecialchars(substr($sl['time'], 0, 5)); ?>"
                                                                            data-label="<?php echo htmlspecialchars($col['date']->format('l, M j, Y') . ' · ' . $sl['label']); ?>"
                                                                            data-doctor="<?php echo htmlspecialchars($selectedDoctor['full_name'] ?? ''); ?>"
                                                                            onclick="openSlotModal(this)"
                                                                            aria-label="Book this slot">
                                                                            +
                                                                        </button>
                                                                    <?php elseif ($sl['state'] === 'busy'): ?>
                                                                        <button type="button" class="cal-slot-btn cal-slot-btn-table <?php echo $cls; ?>" disabled>Taken</button>
                                                                    <?php else: ?>
                                                                        <button type="button" class="cal-slot-btn cal-slot-btn-table <?php echo $cls; ?>" disabled><?php echo htmlspecialchars($sl['label']); ?></button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                       
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($myPendingAppointmentRequests)): ?>
            <div class="form-card mb-4 queue-compact-form queue-registration-card queue-pending-requests-card">
                <div class="card-header bg-white border-0 py-3 queue-panel-card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-hourglass-half me-2" aria-hidden="true"></i>Pending requests
                    </h5>
                    
                </div>
                <div class="card-body p-3 p-md-4">
                    <?php foreach ($myPendingAppointmentRequests as $pr): ?>
                        <?php
                        $descShort = trim((string) ($pr['description'] ?? ''));
                        if (strlen($descShort) > 80) {
                            $descShort = substr($descShort, 0, 77) . '…';
                        }
                        $line = '<strong>' . htmlspecialchars((string) $pr['doctor_name']) . '</strong>'
                            . ' · ' . htmlspecialchars(formatDate($pr['requested_date']) . ' · ' . formatTime($pr['requested_time']))
                            . ' · ' . htmlspecialchars((string) $pr['treatment_type']);
                        if ($descShort !== '') {
                            $line .= ' · <span class="text-muted">' . htmlspecialchars($descShort) . '</span>';
                        }
                        if (!empty($pr['created_at'])) {
                            $line .= ' · <span class="text-muted">Submitted ' . htmlspecialchars((string) $pr['created_at']) . '</span>';
                        }
                        ?>
                        <div class="queue-pending-row">
                            <div class="queue-pending-row-main"><?php echo $line; ?></div>
                            <form method="post" class="flex-shrink-0" onsubmit="return confirm('Cancel this booking request?');">
                                <input type="hidden" name="cancel_appointment_request" value="1">
                                <input type="hidden" name="request_id" value="<?php echo (int) $pr['id']; ?>">
                                <button type="submit" class="btn btn-cancel-pending btn-sm py-1 px-3">
                                    <i class="fas fa-times me-1"></i> Cancel
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-12 queue-cell-sidebar order-2">
            <div class="form-card mb-4 queue-compact-form queue-registration-card" id="queue-walkin">
                <div class="card-header bg-white border-0 py-3 queue-panel-card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-week me-2" aria-hidden="true"></i>Queue request
                    </h5>
                    <p class="small text-muted mb-0 mt-1"> No free slots? Join the queue to be prioritized if an opening appears within your chosen timeframe.</p>
                </div>
                <div class="card-body p-4">
                    <?php if (empty($doctors)): ?>
                        <p class="text-muted small mb-0">No dentists are available for requests right now. Please call the clinic.</p>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="weekly_queue_request" value="1">
                        <div class="mb-3">
                            <label class="form-label-modern">
                                <i class="fas fa-user-md me-2" aria-hidden="true"></i>Dentist *
                            </label>
                            <select class="form-select form-control-modern" name="doctor_id" required>
                                <option value="">Select a dentist</option>
                                <?php foreach ($doctors as $d): ?>
                                    <option value="<?php echo (int) $d['id']; ?>"><?php echo htmlspecialchars($d['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-modern">
                                <i class="fas fa-calendar-day me-2" aria-hidden="true"></i>Preferred date *
                            </label>
                            <input type="date" class="form-control form-control-modern" name="preferred_date" required
                                min="<?php echo htmlspecialchars(date('Y-m-d')); ?>"
                                value="<?php echo htmlspecialchars(date('Y-m-d')); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-modern">
                                <i class="fas fa-arrows-alt-h me-2" aria-hidden="true"></i>Date flexibility (optional)
                            </label>
                            <input type="number" class="form-control form-control-modern" name="date_flexibility_days" min="0" max="30" step="1" value="0"
                                placeholder="0" aria-describedby="flexHelp">
                            <div id="flexHelp" class="form-text small">± days before or after preferred date </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label-modern">
                                <i class="fas fa-tooth me-2" aria-hidden="true"></i>Visit type *
                            </label>
                            <select class="form-select form-control-modern" name="treatment_type" required>
                                <option value="">Select…</option>
                                <?php foreach ($visitTypeOptions as $opt): ?>
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
                        <div class="mb-3">
                            <label class="form-label-modern">
                                <i class="fas fa-comment-alt me-2" aria-hidden="true"></i>Notes (optional)
                            </label>
                            <textarea class="form-control form-control-modern queue-notes-field" name="queue_notes" rows="2" maxlength="2000"
                                placeholder="Anything the clinic should know"></textarea>
                        </div>
                        <div class="d-flex gap-3 flex-wrap">
                            <button type="submit" class="btn btn-queue-reg">
                               Submit Request
                            </button>
                            <a href="index.php" class="btn btn-cancel-reg">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3 queue-panel-card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2" aria-hidden="true"></i>Queue Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-card">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-clock text-warning fa-lg me-3 mt-1"></i>
                            <div>
                                <strong>Estimated Wait Times by Priority</strong>
                                <p class="small mb-0 mt-1">
                                    🔴 Emergency: Immediate<br>
                                    🟠 High: 10-20 minutes<br>
                                    🟡 Medium: 20-30 minutes<br>
                                    🟢 Low: 30-45 minutes
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="info-card mb-0">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-exclamation-triangle text-danger fa-lg me-3 mt-1"></i>
                            <div>
                                <strong>Emergency Cases</strong>
                                <p class="small mb-0 mt-1">If you have severe pain, bleeding, or trauma, select Emergency priority or call us immediately.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 queue-cell-before order-3">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3 queue-panel-card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clinic-medical me-2" aria-hidden="true"></i>Before Your Visit
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-3">
                        <i class="fas fa-id-card text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Bring Your ID & Insurance Card</strong>
                            <p class="small text-muted mb-0">For verification and coverage</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fas fa-list-alt text-primary me-3 mt-1"></i>
                        <div>
                            <strong>List of Medications</strong>
                            <p class="small text-muted mb-0">Current medications and allergies</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fas fa-file-medical text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Previous Records</strong>
                            <p class="small text-muted mb-0">X-rays or dental records if available</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <i class="fas fa-smile text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Oral Hygiene</strong>
                            <p class="small text-muted mb-0">Brush your teeth before arrival</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="slotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-plus me-2" style="color:#667eea;"></i>Request this time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="slotBookForm">
                <input type="hidden" name="book_appointment" value="1">
                <input type="hidden" name="doctor_id" id="modalDoctorId" value="<?php echo (int) $doctorId; ?>">
                <input type="hidden" name="appointment_date" id="modalApptDate" value="">
                <input type="hidden" name="appointment_time" id="modalApptTime" value="">
                <input type="hidden" name="duration" value="<?php echo (int) $slotMinutes; ?>">
                <div class="modal-body">
                    <div class="slot-modal-summary">
                        <dl class="mb-0">
                            <dt>Dentist</dt>
                            <dd id="modalDoctorName">—</dd>
                            <dt>When</dt>
                            <dd id="modalWhen">—</dd>
                        </dl>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Visit type *</label>
                        <select class="form-select" name="treatment_type" required>
                            <option value="">Select…</option>
                            <?php foreach ($visitTypeOptions as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Notes for the clinic (optional)</label>
                        <textarea class="form-control" name="description" rows="3" maxlength="2000" placeholder="Anything we should know?"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;"><i class="fas fa-check me-1"></i> Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var slotModal;
document.addEventListener('DOMContentLoaded', function() {
    var el = document.getElementById('slotModal');
    if (el && window.bootstrap) slotModal = new bootstrap.Modal(el);
});
function openSlotModal(btn) {
    document.getElementById('modalApptDate').value = btn.getAttribute('data-date');
    document.getElementById('modalApptTime').value = btn.getAttribute('data-time');
    document.getElementById('modalDoctorName').textContent = btn.getAttribute('data-doctor');
    document.getElementById('modalWhen').textContent = btn.getAttribute('data-label');
    if (slotModal) slotModal.show();
}
</script>

<?php include '../layouts/footer.php'; ?>
