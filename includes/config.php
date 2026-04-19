<?php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dental_clinic_local');

define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InpmenJ2aW9qd2lucmFzY3Bkb3ljIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzUxNDU0MzQsImV4cCI6MjA5MDcyMTQzNH0.bivyEAYdzrNP8DE5G5NfyKK31fM2KiO9Dpw5AQBUg6cy');      
define('SUPABASE_URL', 'https://zfzrviojwinrascpdoyc.supabase.co');   // Replace with your actual Supabase URL
define('SUPABASE_KEY', 'sb_publishable_a0kU3h5n4ytw5N8hTY1PQg_1Cz7ZKoD'); // Replace with your key


// Application configuration
define('SITE_NAME', 'DentAssist<br>Smart Dental Clinic');
// Outgoing mail for password reset (php.ini sendmail / SMTP must be configured on the server)
if (!defined('MAIL_FROM_ADDRESS')) {
    define('MAIL_FROM_ADDRESS', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', SITE_NAME);
}

// Site URL + web path (auto-detect app folder under document root — fixes CSS/JS when folder name or host differs)
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        ? 'https'
        : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $docRaw = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $docResolved = ($docRaw !== '') ? realpath($docRaw) : false;
    $appResolved = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');

    $basePrefix = '';
    if ($docResolved !== false && $appResolved !== false) {
        $d = rtrim(str_replace('\\', '/', $docResolved), '/');
        $a = rtrim(str_replace('\\', '/', $appResolved), '/');
        if ($d !== '' && strpos($a . '/', $d . '/') === 0) {
            $tail = trim(substr($a, strlen($d)), '/');
            $basePrefix = $tail === '' ? '' : '/' . $tail;
        }
    }
    if ($basePrefix === '' && $docResolved === false && $appResolved !== false) {
        $basePrefix = '/' . basename($appResolved);
    }

    define('BASE_PATH', $basePrefix);
    define('SITE_URL', $protocol . '://' . $host . $basePrefix);
}

if (!defined('BASE_PATH')) {
    $pu = parse_url(SITE_URL, PHP_URL_PATH);
    $p = ($pu !== null && $pu !== false) ? rtrim((string) $pu, '/') : '';
    define('BASE_PATH', $p);
}

if (!function_exists('asset_url')) {
    /**
     * Root-relative URL for static files (uses current host — avoids localhost vs 127.0.0.1 mismatches).
     */
    function asset_url(string $path): string
    {
        $path = ltrim($path, '/');
        $b = defined('BASE_PATH') ? BASE_PATH : '';
        if ($b === '' || $b === '/') {
            return '/' . $path;
        }

        return rtrim($b, '/') . '/' . $path;
    }
}
// Public URL used in WhatsApp links (must be reachable from patient phone).
// Example: https://your-domain.com/Dental or https://xxxx.ngrok-free.app/Dental
if (!defined('PUBLIC_SITE_URL')) {
    define('PUBLIC_SITE_URL', '');
}
// WhatsApp: PHP talks to the local Node server (npm start → assets/js/whatsapp/send.js)
if (!defined('WHATSAPP_NODE_SEND_URL')) {
    define('WHATSAPP_NODE_SEND_URL', 'http://127.0.0.1:3210/send');
}
// Upload path and URL (folder under this project)
$__uploadReal = realpath(__DIR__ . '/../assets/uploads');
define('UPLOAD_PATH', ($__uploadReal !== false ? $__uploadReal . DIRECTORY_SEPARATOR : __DIR__ . '/../assets/uploads/'));
define('UPLOAD_URL', rtrim(SITE_URL, '/') . '/assets/uploads/');

// Timezone
date_default_timezone_set('America/New_York');

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>