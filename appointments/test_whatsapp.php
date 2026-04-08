<?php
/**
 * Dev/test page: sends one message via the local Node WhatsApp bridge (send.js on port 3210).
 * Usage: open this URL with ?phone=961xxxxxxx (digits, with or without +).
 */
require_once __DIR__ . '/../includes/functions.php';

$isCli = (PHP_SAPI === 'cli');
$output = '';
$exitCode = 0;

$phone = '';
if ($isCli) {
    $phone = $argv[1] ?? '';
} else {
    $phone = isset($_GET['phone']) ? trim((string) $_GET['phone']) : '';
}

if ($phone === '') {
    $output = "Pass the recipient phone as a query parameter, e.g.:\n"
        . "  /appointments/test_whatsapp.php?phone=96181665330\n"
        . "Or from CLI: php test_whatsapp.php 96181665330\n"
        . "Ensure npm start is running so the Node sender is up.\n";
    $exitCode = 2;
} else {
    $msg = 'Test from dental clinic (Node WhatsApp) — please ignore.';
    $result = sendWhatsapp($phone, $msg);
    if (!empty($result['ok'])) {
        $output = "Success: message queued/sent via local WhatsApp Node server.\n";
    } else {
        $output = 'Error: ' . ($result['error'] ?? 'Unknown error') . "\n";
        $exitCode = 1;
    }
}

if ($isCli) {
    echo $output;
    exit($exitCode);
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp test</title>
</head>
<body>
    <div class="shell">
        <h1>WhatsApp test (Node)</h1>
        <pre><?php echo htmlspecialchars($output, ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
</body>
</html>
