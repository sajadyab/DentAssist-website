<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query('DESCRIBE users');
while($row = $stmt->fetch_assoc()) {
    echo $row['Field'] . ': ' . $row['Type'] . ' - Key: ' . $row['Key'] . PHP_EOL;
}
?>