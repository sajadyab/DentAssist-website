<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query('SELECT id, email FROM users WHERE email LIKE "%@patients.local"');
while($row = $stmt->fetch_assoc()) {
    echo 'User ' . $row['id'] . ': ' . $row['email'] . PHP_EOL;
}
?>