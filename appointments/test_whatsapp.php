<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Twilio\Rest\Client;

$sid = 'AC5bb7e058bae85f111273b3e61011b879';          // from config.php
$token = '11327c3ba046c7c470e94947413d0119';        // from config.php
$from = 'whatsapp:+14155238886';   // sandbox number
$to = 'whatsapp:+96181665330';     // your WhatsApp number (cleaned)

$client = new Client($sid, $token);
try {
    $message = $client->messages->create($to, [
        'from' => $from,
        'body' => 'Test from dental clinic – please ignore.'
    ]);
    echo "Success! Message SID: " . $message->sid . "\n";
    echo "Status: " . $message->status . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>