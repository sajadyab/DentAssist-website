<?php
require_once 'supabase_client.php';
require_once 'includes/config.php';

$supabase = new SupabaseAPI(SUPABASE_URL, SUPABASE_KEY);

try {
    $users = $supabase->select('users');
    echo 'Cloud users: ' . count($users) . PHP_EOL;
    foreach($users as $user) {
        echo '  ID: ' . $user['id'] . ', Local_ID: ' . ($user['local_id'] ?? 'null') . ', Email: ' . ($user['email'] ?? 'no email') . ', Created: ' . ($user['created_at'] ?? 'no created_at') . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>