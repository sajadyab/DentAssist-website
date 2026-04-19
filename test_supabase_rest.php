
<?php
require_once __DIR__ . '/supabase_client.php';

// ===== REPLACE THESE TWO LINES =====
$url =  'https://zfzrviojwinrascpdoyc.supabase.co'; 
$key = 'sb_publishable_a0kU3h5n4ytw5N8hTY1PQg_1Cz7ZKoD';      // ← Your API Key

$client = new SupabaseAPI($url, $key);

echo "✅ Supabase REST client created.<br>";

try {
    // Try to select from a table named 'test'
    $result = $client->select('test', ['limit' => 1]);
    echo "✅ Connection successful! Response:<br><pre>";
    print_r($result);
    echo "</pre>";
} catch (Exception $e) {
    echo "⚠️ Error: " . $e->getMessage();
}
?>