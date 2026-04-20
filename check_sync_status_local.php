<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT id, email, sync_status FROM users WHERE id IN (?, ?)');
$id1 = 17;
$id2 = 18;
$stmt->bind_param('ii', $id1, $id2);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    echo 'User ' . $row['id'] . ': ' . $row['email'] . ' - sync_status: ' . ($row['sync_status'] ?? 'null') . PHP_EOL;
}
?>