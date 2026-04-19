<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query('SELECT id, email, sync_status FROM users WHERE sync_status = "pending"');
while($row = $stmt->fetch_assoc()) {
    echo 'User ' . $row['id'] . ': ' . $row['email'] . ' - sync_status: pending' . PHP_EOL;
}
?>