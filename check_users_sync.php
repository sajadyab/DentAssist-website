<?php
require_once 'includes/bootstrap.php';
$db = Database::getInstance();

echo "Local users:\n";
$users = $db->fetchAll('SELECT id, username, email, sync_status, created_at, updated_at FROM users ORDER BY id');
foreach ($users as $user) {
    echo "ID: {$user['id']}, Username: {$user['username']}, Email: {$user['email']}, Status: {$user['sync_status']}, Created: {$user['created_at']}, Updated: {$user['updated_at']}\n";
}

echo "\nSync metadata for users:\n";
$metadata = $db->fetchOne('SELECT * FROM sync_metadata WHERE table_name = ?', ['users'], 's');
if ($metadata) {
    echo "Last cloud sync: " . ($metadata['last_cloud_sync'] ?? 'Never') . "\n";
} else {
    echo "No sync metadata found for users table\n";
}
?>