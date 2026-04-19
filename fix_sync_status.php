<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('UPDATE users SET sync_status = "synced" WHERE id IN (?, ?)');
$id1 = 17;
$id2 = 18;
$stmt->bind_param('ii', $id1, $id2);
$stmt->execute();
echo 'Updated sync_status for users 17 and 18 to synced' . PHP_EOL;
?>