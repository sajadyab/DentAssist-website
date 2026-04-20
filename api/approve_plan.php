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

// Define clinic working hours (adjust as needed)
$startHour = 9; // 9 AM
$endHour = 17;  // 5 PM
$slotDuration = 30; // minutes

$bookedSlots = [];
$appointments = $db->fetchAll(
    "SELECT appointment_time, chair_number FROM appointments 
     WHERE appointment_date = ? AND status != 'cancelled'",
    [$date],
    "s"
);

foreach ($appointments as $apt) {
    $bookedSlots[$apt['appointment_time']][] = $apt['chair_number'];
}

$slots = [];
for ($hour = $startHour; $hour < $endHour; $hour++) {
    for ($min = 0; $min < 60; $min += $slotDuration) {
        $time = sprintf("%02d:%02d:00", $hour, $min);
        $available = !isset($bookedSlots[$time]);
        $slots[] = [
            'time' => substr($time, 0, 5),
            'available' => $available
        ];
    }
}

echo json_encode(['slots' => $slots]);
?>