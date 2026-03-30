<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

Auth::requireLogin();

// Only staff can access inventory alerts
if (Auth::hasRole('patient')) {
    echo json_encode(['error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

$db = Database::getInstance();
$today = date('Y-m-d');
$nextWeek = date('Y-m-d', strtotime('+7 days'));

$expired = $db->fetchAll(
    "SELECT id, item_name, expiry_date FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date < ?",
    [$today],
    "s"
);

$expiring = $db->fetchAll(
    "SELECT id, item_name, expiry_date FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN ? AND ?",
    [$today, $nextWeek],
    "ss"
);

$lowstock = $db->fetchAll(
    "SELECT id, item_name, quantity, reorder_level FROM inventory WHERE quantity <= reorder_level AND quantity > 0"
);

echo json_encode([
    'expired' => $expired,
    'expiring' => $expiring,
    'lowstock' => $lowstock
]);
?>