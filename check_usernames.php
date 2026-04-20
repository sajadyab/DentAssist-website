<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query('SELECT id, username, email FROM users ORDER BY id');
while($row = $stmt->fetch_assoc()) {
    echo 'User ' . $row['id'] . ': ' . $row['username'] . ' - ' . $row['email'] . PHP_EOL;
}
?>