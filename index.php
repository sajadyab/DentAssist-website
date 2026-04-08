<?php
// Redirect to login or dashboard
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (Auth::isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
?>