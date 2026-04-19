<?php
include 'includes/db.php';
$db = Database::getInstance()->getConnection();
$users = $db->query('SELECT id, email, created_at FROM users ORDER BY id');
echo 'Local users: ' . $users->num_rows . PHP_EOL;
while($user = $users->fetch_assoc()) {
    echo '  ' . $user['id'] . ': ' . $user['email'] . ' (' . $user['created_at'] . ')' . PHP_EOL;
}
?>