<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';
$chair = $_GET['chair'] ?? null;

if (empty($date) || empty($time)) {
    echo json_encode(['available' => false, 'message' => 'Date and time required']);
    exit;
}

$db = Database::getInstance();

// Check if any appointment exists for this date/time/chair (excluding cancelled)
$params = [$date, $time];
$types = "ss";

$chairCondition = "";
if (!empty($chair)) {
    $chairCondition = " AND chair_number = ?";
    $params[] = $chair;
    $types .= "i";
}

$existing = $db->fetchOne(
    "SELECT id FROM appointments 
     WHERE appointment_date = ? AND appointment_time = ? AND status != 'cancelled' $chairCondition",
    $params,
    $types
);

if ($existing) {
    echo json_encode(['available' => false, 'message' => 'Slot already booked']);
} else {
    echo json_encode(['available' => true, 'message' => 'Slot available']);
}
?>