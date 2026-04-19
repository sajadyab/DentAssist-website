<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query('SELECT table_name, last_cloud_sync FROM sync_metadata WHERE table_name = "users"');
$row = $stmt->fetch_assoc();
echo 'Users last sync: ' . ($row['last_cloud_sync'] ?? 'null') . PHP_EOL;
?>