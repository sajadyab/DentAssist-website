<?php
require_once 'includes/config.php';
require_once 'supabase_client.php';

$s = new SupabaseAPI(SUPABASE_URL, SUPABASE_KEY);
try {
    echo "Testing users table with updated_at filter...\n";
    $lastSync = '2026-04-12 21:17:34';
    $query = [
        'select' => '*',
        'updated_at' => 'gt.' . str_replace(' ', 'T', $lastSync) . 'Z',
        'limit' => 10
    ];
    echo 'Query: ' . json_encode($query) . "\n";
    $rows = $s->select('users', $query);
    echo 'Found ' . count($rows) . ' users updated after ' . $lastSync . "\n";
    foreach ($rows as $row) {
        echo 'ID: ' . ($row['id'] ?? 'null') . ', local_id: ' . ($row['local_id'] ?? 'null') . ', updated_at: ' . ($row['updated_at'] ?? 'null') . "\n";
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>