<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

Auth::requireLogin();

header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$time = $_GET['time'] ?? '';

if (empty($date) || empty($time)) {
    echo json_encode(['available' => false, 'message' => 'Date and time required']);
    exit;
}

$db = Database::getInstance();

$excludeId = (int) ($_GET['exclude_id'] ?? 0);

// Match HH:MM or HH:MM:SS from DB TIME column; ignore current appointment row when editing
$sql = 'SELECT id FROM appointments 
     WHERE appointment_date = ? AND TIME_FORMAT(appointment_time, \'%H:%i\') = TIME_FORMAT(?, \'%H:%i\')
     AND status != \'cancelled\'';
$params = [$date, $time];
$types = 'ss';
if ($excludeId > 0) {
    $sql .= ' AND id != ?';
    $params[] = $excludeId;
    $types .= 'i';
}

$existing = $db->fetchOne($sql, $params, $types);

if ($existing) {
    echo json_encode(['available' => false, 'message' => 'Slot already booked']);
} else {
    echo json_encode(['available' => true, 'message' => 'Slot available']);
}
?>