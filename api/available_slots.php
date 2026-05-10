<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
if (empty($date)) {
    echo json_encode(['slots' => []]);
    exit;
}

$db = Database::getInstance();

$excludeId = (int) ($_GET['exclude_id'] ?? 0);

// Define clinic working hours (adjust as needed)
$startHour = 9; // 9 AM
$endHour = 17;  // 5 PM
$slotDuration = (int) ($_GET['duration'] ?? 30);
$allowedDurations = [15, 30, 45, 60, 90, 120];
if (!in_array($slotDuration, $allowedDurations, true)) {
    $slotDuration = 30;
}

/**
 * Canonical HH:MM:00 so booked times from MySQL always match grid keys.
 *
 * @param mixed $mysqlTime
 */
function available_slots_time_key($mysqlTime): string
{
    $raw = trim((string) $mysqlTime);
    if ($raw === '') {
        return '';
    }
    if (!preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $raw, $m)) {
        return $raw;
    }

    return sprintf('%02d:%02d:00', (int) $m[1], (int) $m[2]);
}

$bookedKeys = [];

if ($excludeId > 0) {
    $appointments = $db->fetchAll(
        'SELECT appointment_time FROM appointments 
         WHERE appointment_date = ? AND status != \'cancelled\' AND id != ?',
        [$date, $excludeId],
        'si'
    );
} else {
    $appointments = $db->fetchAll(
        'SELECT appointment_time FROM appointments 
         WHERE appointment_date = ? AND status != \'cancelled\'',
        [$date],
        's'
    );
}

foreach ($appointments as $apt) {
    $k = available_slots_time_key($apt['appointment_time'] ?? '');
    if ($k !== '') {
        $bookedKeys[$k] = true;
    }
}

// Only FREE slots returned (no booked/disabled placeholders in dropdowns).
$slots = [];
for ($hour = $startHour; $hour < $endHour; $hour++) {
    for ($min = 0; $min < 60; $min += $slotDuration) {
        $timeFull = sprintf('%02d:%02d:00', $hour, $min);
        if (!empty($bookedKeys[$timeFull])) {
            continue;
        }
        $slots[] = [
            'time' => substr($timeFull, 0, 5),
        ];
    }
}

echo json_encode(['slots' => $slots]);
