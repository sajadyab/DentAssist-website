<?php
$url = 'https://zfzrviojwinrascpdoyc.supabase.co';  // replace with your actual URL
$key = 'sb_publishable_a0kU3h5n4ytw5N8hTY1PQg_1Cz7ZKoD';           // your key

$url = SUPABASE_URL . '/rest/v1/appointments?limit=1';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . SUPABASE_KEY,
    'Authorization: Bearer ' . SUPABASE_KEY
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "Response: $response\n";
?>