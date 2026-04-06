<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

header('Content-Type: application/json; charset=utf-8');

if (Auth::hasRole('patient')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = Database::getInstance();
$role = (string) ($_SESSION['role'] ?? '');
$uid = (int) Auth::userId();

$startRaw = $_GET['start'] ?? date('Y-m-d\T00:00:00');
$endRaw = $_GET['end'] ?? date('Y-m-d\T00:00:00', strtotime('+8 days'));

try {
    $startDt = new DateTimeImmutable(substr($startRaw, 0, 10));
    $endExclusive = new DateTimeImmutable(substr($endRaw, 0, 10));
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date range']);
    exit;
}

if ($endExclusive <= $startDt) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid range']);
    exit;
}

$rangeEndInclusive = $endExclusive->modify('-1 day');
$from = $startDt->format('Y-m-d');
$to = $rangeEndInclusive->format('Y-m-d');

if ($role === 'doctor') {
    $doctorId = $uid;
} else {
    $doctorId = (int) ($_GET['doctor_id'] ?? 0);
    if ($doctorId <= 0) {
        echo json_encode([
            'error' => 'doctor_id required',
            'config' => null,
            'events' => [],
        ]);
        exit;
    }
    $docOk = $db->fetchOne(
        "SELECT id FROM users WHERE id = ? AND role = 'doctor' AND COALESCE(is_active, 1) = 1",
        [$doctorId],
        'i'
    );
    if (!$docOk) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid doctor']);
        exit;
    }
}

$calConfig = getClinicBookingCalendarConfig($db);
$slotMinutes = $calConfig['slot_minutes'];
$hours = $calConfig['hours'];

$minOpenMin = PHP_INT_MAX;
$maxCloseMin = 0;
$bands = [];
foreach ([1, 2, 3, 4, 5, 6, 7] as $n) {
    $band = clinicHoursBandForWeekdayN($n, $hours);
    $bands[$n] = $band;
    if ($band && isset($band['open'], $band['close'])) {
        $op = clinicTimeToMinutes($band['open']);
        $cl = clinicTimeToMinutes($band['close']);
        if ($op !== null && $cl !== null && $cl > $op) {
            $minOpenMin = min($minOpenMin, $op);
            $maxCloseMin = max($maxCloseMin, $cl);
        }
    }
}

if ($minOpenMin === PHP_INT_MAX) {
    $minOpenMin = 9 * 60;
    $maxCloseMin = 18 * 60;
}

$slotMinTime = minutesToHi($minOpenMin) . ':00';
$slotMaxTime = minutesToHi($maxCloseMin) . ':00';

$businessHours = [];
$wd = $hours['weekday'] ?? null;
if ($wd && isset($wd['open'], $wd['close'])) {
    $businessHours[] = [
        'daysOfWeek' => [1, 2, 3, 4, 5],
        'startTime' => normalizeHi($wd['open']),
        'endTime' => normalizeHi($wd['close']),
    ];
}
$sat = $hours['saturday'] ?? null;
if ($sat && isset($sat['open'], $sat['close'])) {
    $businessHours[] = [
        'daysOfWeek' => [6],
        'startTime' => normalizeHi($sat['open']),
        'endTime' => normalizeHi($sat['close']),
    ];
}
$sun = $hours['sunday'] ?? null;
if ($sun && isset($sun['open'], $sun['close'])) {
    $businessHours[] = [
        'daysOfWeek' => [0],
        'startTime' => normalizeHi($sun['open']),
        'endTime' => normalizeHi($sun['close']),
    ];
}

$events = [];

$appointments = $db->fetchAll(
    "SELECT a.id, a.patient_id, a.doctor_id, a.appointment_date, a.appointment_time,
            a.duration, a.treatment_type, a.description, a.notes, a.chair_number, a.status,
            a.end_time,
            p.full_name AS patient_name, p.phone AS patient_phone,
            u.full_name AS doctor_name
     FROM appointments a
     INNER JOIN patients p ON p.id = a.patient_id
     INNER JOIN users u ON u.id = a.doctor_id
     WHERE a.doctor_id = ?
       AND a.appointment_date BETWEEN ? AND ?
       AND a.status NOT IN ('cancelled')
     ORDER BY a.appointment_date, a.appointment_time",
    [$doctorId, $from, $to],
    'iss'
);

foreach ($appointments as $apt) {
    $date = $apt['appointment_date'];
    $tStart = normalizeTimeString((string) $apt['appointment_time']);
    $tEnd = normalizeTimeString((string) $apt['end_time']);
    $startIso = $date . 'T' . $tStart;
    $endIso = $date . 'T' . $tEnd;
    $patientName = (string) $apt['patient_name'];
    $treatment = (string) $apt['treatment_type'];
    $events[] = [
        'id' => 'appt-' . $apt['id'],
        'title' => $patientName,
        'start' => $startIso,
        'end' => $endIso,
        'classNames' => ['slot-scheduled'],
        'extendedProps' => [
            'kind' => 'scheduled',
            'appointment_id' => (int) $apt['id'],
            'patient_id' => (int) $apt['patient_id'],
            'patient_name' => $patientName,
            'patient_phone' => (string) ($apt['patient_phone'] ?? ''),
            'treatment_type' => $treatment,
            'doctor_name' => (string) $apt['doctor_name'],
            'status' => (string) $apt['status'],
            'duration' => (int) $apt['duration'],
            'description' => $apt['description'],
            'notes' => $apt['notes'],
            'chair_number' => $apt['chair_number'],
        ],
    ];
}

$requests = [];
$reqsByDate = [];
if (dbTableExists('appointment_requests')) {
    $requests = $db->fetchAll(
        "SELECT ar.*, p.full_name AS patient_name, p.phone AS patient_phone, u.full_name AS doctor_name
         FROM appointment_requests ar
         INNER JOIN patients p ON p.id = ar.patient_id
         INNER JOIN users u ON u.id = ar.doctor_id
         WHERE ar.doctor_id = ?
           AND ar.requested_date BETWEEN ? AND ?
         ORDER BY ar.requested_date, ar.requested_time, ar.id",
        [$doctorId, $from, $to],
        'iss'
    );

    foreach ($requests as $req) {
        $date = $req['requested_date'];
        if (!isset($reqsByDate[$date])) {
            $reqsByDate[$date] = [];
        }
        $reqsByDate[$date][] = $req;
        $tStart = normalizeTimeString((string) $req['requested_time']);
        $durMin = (int) $req['duration_minutes'];
        if ($durMin < 5) {
            $durMin = $slotMinutes;
        }
        $slotStart = dateTimeAt($date, $tStart);
        $slotEnd = $slotStart->modify('+' . $durMin . ' minutes');
        $patientName = (string) $req['patient_name'];
        $treatment = (string) $req['treatment_type'];
        $events[] = [
            'id' => 'req-' . $req['id'],
            'title' => $patientName,
            'start' => $slotStart->format('Y-m-d\TH:i:s'),
            'end' => $slotEnd->format('Y-m-d\TH:i:s'),
            'classNames' => ['slot-request'],
            'extendedProps' => [
                'kind' => 'request',
                'request_id' => (int) $req['id'],
                'patient_id' => (int) $req['patient_id'],
                'patient_name' => $patientName,
                'patient_phone' => (string) ($req['patient_phone'] ?? ''),
                'treatment_type' => $treatment,
                'doctor_name' => (string) $req['doctor_name'],
                'description' => $req['description'],
                'duration_minutes' => $durMin,
            ],
        ];
    }
}

$apptsByDate = [];
foreach ($appointments as $apt) {
    $d = $apt['appointment_date'];
    if (!isset($apptsByDate[$d])) {
        $apptsByDate[$d] = [];
    }
    $apptsByDate[$d][] = $apt;
}

$now = new DateTimeImmutable('now');

for ($d = $startDt; $d < $endExclusive; $d = $d->modify('+1 day')) {
    $dateStr = $d->format('Y-m-d');
    $n = (int) $d->format('N');
    $band = $bands[$n] ?? null;
    if (!$band || !isset($band['open'], $band['close'])) {
        continue;
    }

    $openDt = dateTimeAt($dateStr, normalizeTimeString((string) $band['open']));
    $closeDt = dateTimeAt($dateStr, normalizeTimeString((string) $band['close']));
    if ($closeDt <= $openDt) {
        continue;
    }

    $slotLen = new DateInterval('PT' . $slotMinutes . 'M');
    $cursor = $openDt;
    while ($cursor < $closeDt) {
        $slotEnd = $cursor->add($slotLen);
        if ($slotEnd > $closeDt) {
            break;
        }

        if ($slotEnd <= $now) {
            $cursor = $slotEnd;
            continue;
        }

        $busy = false;
        foreach ($apptsByDate[$dateStr] ?? [] as $apt) {
            $aStart = dateTimeAt($apt['appointment_date'], normalizeTimeString((string) $apt['appointment_time']));
            $aEnd = dateTimeAt($apt['appointment_date'], normalizeTimeString((string) $apt['end_time']));
            if (intervalsOverlap($cursor, $slotEnd, $aStart, $aEnd)) {
                $busy = true;
                break;
            }
        }
        if ($busy) {
            $cursor = $slotEnd;
            continue;
        }
        foreach ($reqsByDate[$dateStr] ?? [] as $req) {
            $durMin = max(5, (int) $req['duration_minutes']);
            $rStart = dateTimeAt($req['requested_date'], normalizeTimeString((string) $req['requested_time']));
            $rEnd = $rStart->modify('+' . $durMin . ' minutes');
            if (intervalsOverlap($cursor, $slotEnd, $rStart, $rEnd)) {
                $busy = true;
                break;
            }
        }
        if ($busy) {
            $cursor = $slotEnd;
            continue;
        }

        $events[] = [
            'id' => 'avail-' . $dateStr . '-' . $cursor->format('H-i'),
            'title' => '',
            'start' => $cursor->format('Y-m-d\TH:i:s'),
            'end' => $slotEnd->format('Y-m-d\TH:i:s'),
            'classNames' => ['slot-available'],
            'display' => 'block',
            'extendedProps' => [
                'kind' => 'available',
                'doctor_id' => $doctorId,
                'slot_date' => $dateStr,
                'slot_time' => $cursor->format('H:i:s'),
                'slot_minutes' => $slotMinutes,
            ],
        ];

        $cursor = $slotEnd;
    }
}

echo json_encode([
    'config' => [
        'slotMinutes' => $slotMinutes,
        'slotMinTime' => $slotMinTime,
        'slotMaxTime' => $slotMaxTime,
        'businessHours' => $businessHours,
    ],
    'events' => $events,
]);

/**
 * @return int|null minutes from midnight
 */
function clinicTimeToMinutes(string $t): ?int
{
    $t = trim($t);
    if ($t === '') {
        return null;
    }
    $parts = explode(':', $t);
    $h = (int) $parts[0];
    $m = (int) ($parts[1] ?? 0);

    return $h * 60 + $m;
}

function minutesToHi(int $m): string
{
    $h = intdiv($m, 60);
    $min = $m % 60;

    return sprintf('%02d:%02d', $h, $min);
}

/** FullCalendar expects H:i or HH:mm:ss */
function normalizeHi(string $t): string
{
    $t = trim($t);
    if (strlen($t) === 5) {
        return $t . ':00';
    }

    return $t;
}

function normalizeTimeString(string $t): string
{
    $t = trim($t);
    if (strlen($t) === 5) {
        return $t . ':00';
    }
    if (strlen($t) === 8) {
        return $t;
    }

    return $t . ':00';
}

function dateTimeAt(string $date, string $timeHms): DateTimeImmutable
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $timeHms);

    return $dt ?: new DateTimeImmutable($date . ' ' . $timeHms);
}

function intervalsOverlap(
    DateTimeImmutable $aStart,
    DateTimeImmutable $aEnd,
    DateTimeImmutable $bStart,
    DateTimeImmutable $bEnd
): bool {
    return $aStart < $bEnd && $aEnd > $bStart;
}
