<?php
/**
 * Clinic arrivals are shown on the Appointments page. This URL is kept for bookmarks and sidebar compatibility.
 */
require_once '../includes/config.php';
require_once '../includes/auth.php';

Auth::requireLogin();

if (Auth::hasRole('patient')) {
    header('Location: ' . SITE_URL . '/patient/index.php');
    exit;
}

header('Location: ' . SITE_URL . '/appointments/index.php#clinic-arrivals', true, 302);
exit;
