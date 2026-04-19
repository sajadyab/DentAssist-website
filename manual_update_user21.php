<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('UPDATE users SET email = ?, username = ?, full_name = ?, updated_at = NOW() WHERE id = ?');
$email = 'zeina@gmail.com';
$username = 'zeina_ayoub';
$full_name = 'Zeina Ayoub';
$id = 21;
$stmt->bind_param('sssi', $email, $username, $full_name, $id);
$stmt->execute();
echo 'Updated user 21 manually' . PHP_EOL;
?>