<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$pageTitle = 'Sign in';
$authBodyClass = 'auth-shell--login';
$authNavActive = 'login';
include 'layouts/auth_header.php';
?>

<div class="login-card">
    <div class="login-header">
        <h1><?php echo SITE_NAME; ?></h1>
        <small>Don't have an account? <a href="register.php">Register.</a></small>
    </div>

    <div id="message"></div>

    <form method="POST" action="api/login.php" data-api="api/login.php" data-message-target="#message">
        <div class="mb-3">
            <div class="d-flex justify-content-end mb-2">

            </div>
            <input type="text"
                   class="form-control"
                   name="username"
                   placeholder="Username or Email"
                   required
                   autofocus>
        </div>

        <div class="mb-3">
            <input type="password"
                   class="form-control"
                   name="password"
                   placeholder="Password"
                   required>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="remember">
            <label class="form-check-label" for="remember">Remember me</label>
        </div>

        <button type="submit" class="btn-login">Sign In</button>
    </form>

    <div class="text-center mt-3">
        <small>Forgot password? <a href="forgot_password.php">Click here.</a></small>
    </div>
</div>

<?php include 'layouts/auth_footer.php'; ?>
