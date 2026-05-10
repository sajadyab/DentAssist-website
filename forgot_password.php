<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$pageTitle = __('forgot_password', 'Forgot password');
$authBodyClass = 'auth-shell--forgot';
$authNavActive = '';
include __DIR__ . '/layouts/auth_header.php';
?>

<div class="auth-page">
    <div class="auth-hero">
        <h1 class="auth-app-title">DentAssist</h1>
        <p class="auth-app-subtitle"><?php echo htmlspecialchars(__('smart_dental_clinic', 'Smart Dental Clinic')); ?></p>
        <p class="auth-hero-page"><?php echo htmlspecialchars(__('password_reset', 'Password reset')); ?></p>
    </div>

    <div class="auth-panel reset-card">
        <p class="auth-panel-lead text-center mb-3">
            <?php echo htmlspecialchars(__('forgot_password_intro', 'Please enter your username. A reset link will be sent to your registered phone number via WhatsApp.')); ?>
        </p>

        <div id="message"></div>

        <form id="resetForm" action="api/forgot_pass.php" method="post">
            <div class="mb-3">
                <label for="username" class="form-label"><?php echo htmlspecialchars(__('username', 'Username')); ?></label>
                <input type="text" name="username" id="username" class="form-control" required autocomplete="username">
            </div>
            <button type="submit" class="btn-blue auth-btn-primary mt-1" id="submitBtn"><?php echo htmlspecialchars(__('reset_password', 'Reset Password')); ?></button>

            <p class="auth-inline-link text-center mt-3 mb-0"><a href="login.php"><?php echo htmlspecialchars(__('back_to_login', 'Back to login')); ?></a></p>
        </form>
    </div>
</div>

<?php
ob_start();
$forgotPasswordI18n = [
    'enterUsername' => __('please_enter_username', 'Please enter username.'),
    'sending' => __('sending', 'Sending...'),
    'linkSent' => __('reset_link_sent_redirecting', 'Link sent successfully. Redirecting to login...'),
    'failed' => __('failed_send_reset_link', 'Failed to send reset link.'),
    'networkError' => __('network_error_try_again', 'Network error. Please try again.'),
    'resetPassword' => __('reset_password', 'Reset Password'),
];
?>
    <script>
        const forgotPasswordI18n = <?php echo json_encode($forgotPasswordI18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        document.getElementById("resetForm").addEventListener("submit", function(e) {
            e.preventDefault();
            const form = this;
            const username = form.username.value.trim();
            const msg = document.getElementById("message");
            const btn = document.getElementById("submitBtn");

            msg.innerHTML = "";

            if (!username) {
                msg.innerHTML = '<div class="alert alert-danger py-2">' + forgotPasswordI18n.enterUsername + '</div>';
                return;
            }

            btn.disabled = true;
            btn.textContent = forgotPasswordI18n.sending;

            const body = new URLSearchParams();
            body.append("username", username);

            fetch(form.action, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8"
                },
                body: body.toString()
            })
                .then((res) => res.json())
                .then((data) => {
                    if (data.success) {
                        msg.innerHTML = '<div class="alert alert-success py-2">' + forgotPasswordI18n.linkSent + '</div>';
                        setTimeout(() => {
                            window.location.href = "login.php";
                        }, 1200);
                        return;
                    }

                    msg.innerHTML = '<div class="alert alert-danger py-2">' + (data.message || forgotPasswordI18n.failed) + "</div>";
                    btn.disabled = false;
                    btn.textContent = forgotPasswordI18n.resetPassword;
                })
                .catch(() => {
                    msg.innerHTML = '<div class="alert alert-danger py-2">' + forgotPasswordI18n.networkError + '</div>';
                    btn.disabled = false;
                    btn.textContent = forgotPasswordI18n.resetPassword;
                });
        });
    </script>
<?php
$authFooterExtra = ob_get_clean();
include __DIR__ . '/layouts/auth_footer.php';
