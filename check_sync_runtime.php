<?php
include 'includes/db.php';
$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->prepare('SELECT * FROM sync_runtime_status WHERE table_name = ? ORDER BY created_at DESC LIMIT 10');
$table = 'users';
$stmt->bind_param('s', $table);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()) {
    echo $row['local_id'] . ': ' . $row['status'] . ' - ' . ($row['message'] ?? 'no msg') . PHP_EOL;
}
?>