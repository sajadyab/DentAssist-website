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
                        $dup = $db->fetchOne(
                            "SELECT id FROM appointment_requests 
                             WHERE patient_id = ? AND doctor_id = ? AND requested_date = ? AND requested_time = ? 
                             LIMIT 1",
                            [$patientId, $postDoctor, $postDate, $postTime],
                            'iiss'
                        );
                        if ($dup) {
                            $error = 'You already have a pending request for this slot.';
                        } else {
                            $reqNotes = 'Patient self-booked via portal.';
                            if ($description !== '') {
                                $reqNotes .= ' ' . $description;
                            }
                            $requestId = $db->insert(
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
                                'iisisss'
                            );

                            if (!$requestId) {
                                $error = 'Could not submit your request. Please try again.';
                            } else {
                                logAction('CREATE', 'appointment_requests', (int) $requestId, null, [
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
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['queue_type']) && !isset($_POST['book_appointment'])) {
    $queueType = $_POST['queue_type'];
    $priority = $_POST['priority'];
    $reason = $_POST['reason'];
    $preferredTreatment = $_POST['preferred_treatment'];
    $preferredDay = $_POST['preferred_day'] ?? null;

    $db->insert(
        "INSERT INTO waiting_queue (patient_id, patient_name, queue_type, priority, reason, preferred_treatment, preferred_day, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'waiting')",
        [$patientId, $patient['full_name'], $queueType, $priority, $reason, $preferredTreatment, $preferredDay],
        'issssss'
    );

    $success = 'You have been added to the queue.';
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

$pageTitle = 'Join Queue';
include '../layouts/header.php';
?>

<style>
.queue-page {
    --cal-accent: #667eea;
    --cal-accent-soft: rgba(102, 126, 234, 0.12);
    --cal-slate: #334155;
    --cal-muted: #64748b;
    --cal-line: #e2e8f0;
}
.queue-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

.queue-timer {
    background: rgba(255,255,255,0.2);
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    backdrop-filter: blur(10px);
}

.timer-number {
    font-size: 32px;
    font-weight: bold;
    font-family: monospace;
}

select.form-control-modern {
    cursor: pointer;
}

select.form-control-modern option {
    padding: 10px;
}

/* Booking calendar (sidebar) */
.queue-calendar-card .card-header {
    background: linear-gradient(180deg, rgba(102, 126, 234, 0.12) 0%, #fff 100%);
    border-bottom: 1px solid var(--cal-line);
    padding: 0.75rem 1rem;
}
.queue-calendar-card .card-header h5 {
    font-size: 0.95rem;
    color: #2c3e50;
}
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
    <div class="mb-3">
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
                <div class="mt-3">
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-clock"></i> Open: Mon-Fri 9AM-6PM
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-phone"></i> Emergency: Call (555) 123-4567
                    </span>
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="queue-timer">
                    <small>Estimated Wait Time</small>
                    <div class="timer-number">~<?php echo rand(15, 45); ?> min</div>
                    <small>Based on current queue</small>
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

    <div class="row">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-4 queue-calendar-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-calendar-check me-2" style="color:#667eea;"></i>Book a visit
                    </h5>
                    <p class="small text-muted mb-0 mt-1">Rows are times; columns are days. Tap <strong>+</strong> to request a slot — your dentist will confirm by WhatsApp.</p>
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
                        <p class="text-muted small mt-2 mb-0" style="font-size:0.7rem;">
                            <?php echo (int) $slotMinutes; ?>-minute visits · closed days are hidden
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle text-info"></i> Queue Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-card">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-chart-line text-primary fa-lg me-3 mt-1"></i>
                            <div>
                                <strong>Current Queue Status</strong>
                                <p class="small mb-0 mt-1">Queue system is active and accepting patients</p>
                                <small class="text-muted">Last updated: <?php echo date('h:i A'); ?></small>
                            </div>
                        </div>
                    </div>

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

            <div class="form-card mb-4 queue-compact-form">
                <div class="form-header">
                    <h4 class="mb-0">
                        <i class="fas fa-sign-in-alt"></i> Queue Registration
                    </h4>
                    <p class="mb-0 mt-2 small opacity-75">Join the walk-in waiting list</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label-modern">
                                <i class="fas fa-calendar-alt text-primary me-2"></i> Queue Type *
                            </label>
                            <select class="form-select form-control-modern" name="queue_type" id="queueType" onchange="togglePreferredDay()" required>
                                <option value="daily">Daily Queue - Today (Immediate Attention)</option>
                                <option value="weekly">Weekly Queue - Future Appointment</option>
                            </select>
                            <small class="text-muted">Choose daily for same-day service or weekly to schedule for next week</small>
                        </div>

                        <div class="mb-4" id="preferredDayDiv" style="display: none;">
                            <label class="form-label-modern">
                                <i class="fas fa-calendar-week text-primary me-2"></i> Preferred Day
                            </label>
                            <select class="form-select form-control-modern" name="preferred_day">
                                <option value="Monday">📅 Monday</option>
                                <option value="Tuesday">📅 Tuesday</option>
                                <option value="Wednesday">📅 Wednesday</option>
                                <option value="Thursday">📅 Thursday</option>
                                <option value="Friday">📅 Friday</option>
                            </select>
                            <small class="text-muted">Select your preferred day for the appointment</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-modern">
                                <i class="fas fa-chart-line text-primary me-2"></i> Priority Level *
                            </label>
                            <select class="form-select form-control-modern" name="priority" required>
                                <option value="low">🟢 Low - Routine checkup / Cleaning (30-45 min wait)</option>
                                <option value="medium" selected>🟡 Medium - Non-urgent dental issue (20-30 min wait)</option>
                                <option value="high">🟠 High - Pain or discomfort (10-20 min wait)</option>
                                <option value="emergency">🔴 Emergency - Severe pain or injury (Immediate)</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-modern">
                                <i class="fas fa-stethoscope text-primary me-2"></i> Reason for Visit *
                            </label>
                            <textarea class="form-control form-control-modern" name="reason" rows="4" required
                                placeholder="Please describe your dental concern in detail..."></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label-modern">
                                <i class="fas fa-tooth text-primary me-2"></i> Preferred Treatment (Optional)
                            </label>
                            <input type="text" class="form-control form-control-modern" name="preferred_treatment"
                                placeholder="e.g., Cleaning, Filling, Extraction, Root Canal, Whitening">
                        </div>

                        <div class="d-flex gap-3 flex-wrap">
                            <button type="submit" class="btn-queue">
                                <i class="fas fa-hourglass-start"></i> Join Queue
                            </button>
                            <a href="index.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clinic-medical text-success"></i> Before Your Visit
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
                            <option value="Check-up / cleaning">Check-up / cleaning</option>
                            <option value="Consultation">Consultation</option>
                            <option value="Filling">Filling</option>
                            <option value="Crown">Crown</option>
                            <option value="Extraction">Extraction</option>
                            <option value="Whitening">Whitening</option>
                            <option value="Emergency / pain">Emergency / pain</option>
                            <option value="Other">Other</option>
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
function togglePreferredDay() {
    const queueType = document.getElementById('queueType').value;
    const div = document.getElementById('preferredDayDiv');
    div.style.display = queueType === 'weekly' ? 'block' : 'none';
}
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
