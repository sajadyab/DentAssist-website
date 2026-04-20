<?php
require_once 'includes/bootstrap.php';
$db = Database::getInstance();

echo "Before reset:\n";
$metadata = $db->fetchOne('SELECT * FROM sync_metadata WHERE table_name = ?', ['users'], 's');
if ($metadata) {
    echo 'Last sync: ' . ($metadata['last_cloud_sync'] ?? 'null') . "\n";
} else {
    echo "No metadata found\n";
}

// Reset the sync metadata to force full sync
$db->execute('DELETE FROM sync_metadata WHERE table_name = ?', ['users'], 's');
echo "Sync metadata reset for users table\n";
echo "Next cloud-to-local sync will fetch ALL users\n";
?>