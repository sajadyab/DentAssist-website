<?php
require_once 'supabase_client.php';
require_once 'includes/config.php';

$supabase = new SupabaseAPI(SUPABASE_URL, SUPABASE_KEY);

try {
    // Update cloud user 21 to have a unique username to avoid conflict with local user 22
    $result = $supabase->update('users', ['username' => 'zeina_ayoub', 'local_id' => 21], ['id' => '21']);
    echo 'Updated cloud user 21 username to avoid conflict' . PHP_EOL;
    print_r($result);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>