<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$pageTitle = 'Sign in';
$authBodyClass = 'auth-shell--login';
$authNavActive = 'login';
include 'layouts/auth_header.php';
?>

<div class="auth-page">
    <div class="auth-hero">
        <h1 class="auth-app-title">DentAssist</h1>
        <p class="auth-app-subtitle">Smart Dental Clinic</p>
    </div>

    <div class="auth-panel login-card">
        <p class="auth-panel-prompt text-center mb-3">
            Don&apos;t have an account? <a href="register.php">Register</a>
        </p>

        <div id="message"></div>

        <form method="POST" action="api/login.php" data-api="api/login.php" data-message-target="#message" class="mt-1">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text"
                       class="form-control"
                       id="username"
                       name="username"
                       placeholder="Username or email"
                       required
                       autofocus
                       autocomplete="username">
            </div>

            <div class="mb-2">
                <label for="password" class="form-label">Password</label>
                <div class="auth-password-wrap">
                    <input type="password"
                           class="form-control auth-password-input"
                           id="password"
                           name="password"
                           placeholder="Password"
                           required
                           autocomplete="current-password">
                    <button type="button" class="auth-password-toggle" id="loginPwToggle" aria-label="Show password" tabindex="0">
                        <i class="far fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <div class="mb-3 form-check auth-remember">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>

            <button type="submit" class="btn-blue auth-btn-primary">Sign In</button>
        </form>

        <p class="auth-inline-link text-center mt-3 mb-0">
            Forgot password? <a href="forgot_password.php">Click here</a>
        </p>
    </div>
</div>

<?php
ob_start();
?>
<script>
(function () {
    var btn = document.getElementById('loginPwToggle');
    var input = document.getElementById('password');
    if (!btn || !input) return;
    btn.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        var i = btn.querySelector('i');
        if (i) {
            i.className = show ? 'far fa-eye-slash' : 'far fa-eye';
        }
    });
})();
</script>
<?php
$authFooterExtra = ob_get_clean();
include 'layouts/auth_footer.php';
