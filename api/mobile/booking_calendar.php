<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';

header('Content-Type: application/json');

// Token Auth
$headers = function_exists('getallheaders') ? getallheaders() : [];
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

$db = Database::getInstance();
$tokenRow = $db->fetchOne("SELECT user_id FROM api_tokens WHERE token = ?", [$token], 's');

if (!$tokenRow) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$userId = (int)$tokenRow['user_id'];

// Get patient ID - THIS WAS MISSING
$patient = $db->fetchOne("SELECT id FROM patients WHERE user_id = ?", [$userId], 'i');
if (!$patient) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}
$patientId = (int)$patient['id'];

// Get doctors
$doctors = $db->fetchAll("SELECT id, full_name FROM users WHERE role = 'doctor' AND COALESCE(is_active, 1) = 1 ORDER BY full_name");

// Get visit types
$visitTypes = [['id' => 0, 'name' => 'Consultation']];
$treatments = $db->fetchAll("SELECT id, name FROM treatments ORDER BY name");
if ($treatments) {
    foreach ($treatments as $t) {
        $visitTypes[] = ['id' => (int)$t['id'], 'name' => $t['name']];
    }
}

$slotMinutes = getClinicSlotMinutes($db);

// Get requested doctor and week
$doctorId = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
$weekOffset = isset($_GET['week']) ? (int)$_GET['week'] : 0;

if ($doctorId <= 0 && !empty($doctors)) {
    $doctorId = (int)$doctors[0]['id'];
}

// Calculate week
$today = new DateTimeImmutable('today');
$monday = $today->modify('monday this week')->modify(($weekOffset * 7) . ' days');
$weekStartStr = $monday->format('Y-m-d');
$weekEndStr = $monday->modify('+6 days')->format('Y-m-d');

$hours = getDoctorBookingHours($db, $doctorId);

// Get busy slots
$busyByDate = [];
if ($doctorId > 0) {
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
        if (!isset($busyByDate[$d])) $busyByDate[$d] = [];
        $busyByDate[$d][] = ['start' => $br['appointment_time'], 'duration' => (int)$br['duration']];
    }
    
    $pendingSlots = $db->fetchAll(
        "SELECT requested_date, requested_time, duration_minutes 
         FROM appointment_requests 
         WHERE doctor_id = ? AND requested_date BETWEEN ? AND ?",
        [$doctorId, $weekStartStr, $weekEndStr],
        'iss'
    );
    foreach ($pendingSlots as $br) {
        $d = $br['requested_date'];
        if (!isset($busyByDate[$d])) $busyByDate[$d] = [];
        $busyByDate[$d][] = ['start' => $br['requested_time'], 'duration' => (int)$br['duration_minutes']];
    }
}

// ✅ Get pending requests for THIS patient
$pendingRequests = $db->fetchAll(
    "SELECT ar.id, ar.requested_date, ar.requested_time, ar.treatment_type, ar.description, ar.created_at,
            u.full_name AS doctor_name
     FROM appointment_requests ar
     INNER JOIN users u ON u.id = ar.doctor_id
     WHERE ar.patient_id = ?
     ORDER BY ar.requested_date ASC, ar.requested_time ASC",
    [$patientId],
    'i'
);

$formattedPending = [];
foreach ($pendingRequests as $pr) {
    $formattedPending[] = [
        'id' => (int)$pr['id'],
        'doctor_name' => $pr['doctor_name'],
        'requested_date' => $pr['requested_date'],
        'requested_time' => substr($pr['requested_time'], 0, 5),
        'treatment_type' => $pr['treatment_type'],
        'description' => $pr['description'],
        'created_at' => $pr['created_at'],
    ];
}

// Generate week days
$now = new DateTimeImmutable('now');
$weekDays = [];

for ($i = 0; $i < 7; $i++) {
    $d = $monday->modify("+{$i} days");
    $ymd = $d->format('Y-m-d');
    $n = (int) $d->format('N');

    $band = clinicHoursBandForWeekdayN($n, $hours);
    if ($band === null || empty($band['open'])) {
        continue;
    }
    
    $slots = [];
    $openParts = explode(':', $band['open']);
    $closeParts = explode(':', $band['close']);
    
    $openMinutes = (int)$openParts[0] * 60 + (int)($openParts[1] ?? 0);
    $closeMinutes = (int)$closeParts[0] * 60 + (int)($closeParts[1] ?? 0);
    
    for ($mins = $openMinutes; $mins <= $closeMinutes; $mins += $slotMinutes) {
        $h = floor($mins / 60);
        $m = $mins % 60;
        $his = sprintf('%02d:%02d:00', $h, $m);
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $displayH = $h > 12 ? $h - 12 : ($h == 0 ? 12 : $h);
        $label = sprintf('%d:%02d %s', $displayH, $m, $ampm);
        
        $state = 'free';
        
        $slotDateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ymd . ' ' . $his);
        if ($slotDateTime && $slotDateTime < $now) {
            $state = 'past';
        } else {
            $dayBusy = $busyByDate[$ymd] ?? [];
            foreach ($dayBusy as $b) {
                $bStart = $b['start'];
                if (strlen($bStart) === 5) $bStart .= ':00';
                
                $parts = explode(':', $bStart);
                $bStartMin = (int)$parts[0] * 60 + (int)$parts[1];
                $bEndMin = $bStartMin + (int)$b['duration'];
                
                if ($mins < $bEndMin && ($mins + $slotMinutes) > $bStartMin) {
                    $state = 'busy';
                    break;
                }
            }
        }
        
        $slots[] = [
            'time' => $his,
            'label' => $label,
            'state' => $state,
        ];
    }
    
    if (!empty($slots)) {
        $weekDays[] = [
            'date_ymd' => $ymd,
            'display_day' => $d->format('D'),
            'display_date' => $d->format('M j'),
            'slots' => $slots,
        ];
    }
}

echo json_encode([
    'success' => true,
    'data' => [
        'doctors' => $doctors ?: [],
        'visit_types' => $visitTypes,
        'week_days' => $weekDays,
        'slot_duration' => $slotMinutes,
        'week_start' => $weekStartStr,
        'week_end' => $weekEndStr,
        'pending_requests' => $formattedPending,
    ]
]);