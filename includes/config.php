<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Twilio credentials – replace with your actual values
define('TWILIO_SID', 'AC5bb7e058bae85f111273b3e61011b879');
define('TWILIO_AUTH_TOKEN', '11327c3ba046c7c470e94947413d0119');
define('TWILIO_WHATSAPP_NUMBER', '+14155238886'); // Twilio sandbox number
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dental_clinic');

// Application configuration
define('SITE_NAME', 'Dental Clinic Management System _ByteDent_');
// Correct SITE_URL to match project directory (case-sensitive on some setups)
define('SITE_URL', 'http://' . $_SERVER['HTTP_HOST'] . '/Dental');
// Upload path and URL
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/Dental/assets/uploads/');
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