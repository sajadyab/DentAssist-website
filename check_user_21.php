<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT id, email, sync_status FROM users WHERE id = ?');
$id = 21;
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
echo 'User 21: ' . $row['email'] . ' - sync_status: ' . $row['sync_status'] . PHP_EOL;
?>