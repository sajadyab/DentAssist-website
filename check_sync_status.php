<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT * FROM sync_runtime_status WHERE table_name = ? AND local_id IN (?, ?) ORDER BY created_at DESC');
$table = 'users';
$id1 = 17;
$id2 = 18;
$stmt->bind_param('sii', $table, $id1, $id2);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    echo 'User ' . $row['local_id'] . ': ' . $row['status'] . ' - ' . ($row['message'] ?? 'no message') . PHP_EOL;
}
?>