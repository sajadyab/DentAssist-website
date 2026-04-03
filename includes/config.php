<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dental_clinic');

// Application configuration
define('SITE_NAME', 'DentAssist<br>Smart Dental Clinic');
// Outgoing mail for password reset (php.ini sendmail / SMTP must be configured on the server)
if (!defined('MAIL_FROM_ADDRESS')) {
    define('MAIL_FROM_ADDRESS', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME', SITE_NAME);
}

// Correct SITE_URL to match project directory (case-sensitive on some setups)
define('SITE_URL', 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/Dentaltry');
// Public URL used in WhatsApp links (must be reachable from patient phone).
// Example: https://your-domain.com/Dental or https://xxxx.ngrok-free.app/Dental
if (!defined('PUBLIC_SITE_URL')) {
    define('PUBLIC_SITE_URL', '');
}
// WhatsApp: PHP talks to the local Node server (npm start → assets/js/whatsapp/send.js)
if (!defined('WHATSAPP_NODE_SEND_URL')) {
    define('WHATSAPP_NODE_SEND_URL', 'http://127.0.0.1:3210/send');
}
// Upload path and URL
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/Dentaltry/assets/uploads/');
define('UPLOAD_URL', SITE_URL . '/assets/uploads/');

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