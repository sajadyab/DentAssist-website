<?php
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