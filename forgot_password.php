<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Forgot password';
$authBodyClass = 'auth-shell--forgot';
$authNavActive = '';
include __DIR__ . '/layouts/auth_header.php';
?>

    <div class="reset-card">

        <div class="reset-header">
            <h4><?php echo htmlspecialchars($authBrandPlain); ?> — password reset</h4>
            <p>Please enter your username. A reset link will be sent to your <strong>registered</strong> phone number via <strong>WhatsApp</strong>.</p>
        </div>

        <div id="message"></div>

        <form id="resetForm" action="api/forgot_pass.php" method="post">

            <label for="username">Username</label>
            <input type="text" name="username" id="username" class="form-control" required autocomplete="username">
            <button type="submit" class="btn-reset mt-3" id="submitBtn">Reset Password</button>

            <p class="text-center mt-3 mb-0 small"><a href="login.php">Back to login</a></p>

        </form>

    </div>

<?php
ob_start();
?>
    <script>
        document.getElementById("resetForm").addEventListener("submit", function(e) {
            e.preventDefault();
            const form = this;
            const username = form.username.value.trim();
            const msg = document.getElementById("message");
            const btn = document.getElementById("submitBtn");

            msg.innerHTML = "";

            if (!username) {
                msg.innerHTML = '<div class="alert alert-danger py-2">Please enter username.</div>';
                return;
            }

            btn.disabled = true;
            btn.textContent = "Sending…";

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
                        msg.innerHTML = '<div class="alert alert-success py-2">Link sent successfully. Redirecting to login...</div>';
                        setTimeout(() => {
                            window.location.href = "login.php";
                        }, 1200);
                        return;
                    }

                    msg.innerHTML = '<div class="alert alert-danger py-2">' + (data.message || "Failed to send reset link.") + "</div>";
                    btn.disabled = false;
                    btn.textContent = "Reset Password";
                })
                .catch(() => {
                    msg.innerHTML = '<div class="alert alert-danger py-2">Network error. Please try again.</div>';
                    btn.disabled = false;
                    btn.textContent = "Reset Password";
                });
        });
    </script>
<?php
$authFooterExtra = ob_get_clean();
include __DIR__ . '/layouts/auth_footer.php';
