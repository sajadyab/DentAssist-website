<?php
require_once 'includes/config.php';
require_once 'supabase_client.php';

$s = new SupabaseAPI(SUPABASE_URL, SUPABASE_KEY);
try {
    echo "All users in Supabase (no filter):\n";
    $rows = $s->select('users', ['select' => '*']);
    echo 'Found ' . count($rows) . " total users\n";
    foreach ($rows as $row) {
        $updated = $row['updated_at'] ?? 'null';
        echo 'ID: ' . ($row['id'] ?? 'null') . ', local_id: ' . ($row['local_id'] ?? 'null') . ', username: ' . ($row['username'] ?? 'null') . ', updated_at: ' . $updated . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>