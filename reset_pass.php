<?php
require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Set new password';
$authBodyClass = 'auth-shell--reset-pass';
$authNavActive = '';
include __DIR__ . '/layouts/auth_header.php';
?>

    <div class="card-reset">
        <h4 class="text-center mb-2">Set New Password</h4>
        <p class="text-center text-muted small mb-3">Enter your new password below.</p>

        <div id="message"></div>

        <form id="setPassForm" method="post" action="api/reset_pass.php">
            <input type="hidden" name="token" id="token">

            <label for="password" class="form-label">New password</label>
            <input type="password" class="form-control mb-3" id="password" name="password" required minlength="6" autocomplete="new-password">

            <label for="confirm_password" class="form-label">Confirm new password</label>
            <input type="password" class="form-control mb-3" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">

            <button type="submit" class="btn btn-primary w-100" id="submitBtn">Update Password</button>

            <p class="text-center mt-3 mb-0 small"><a href="login.php">Back to login</a></p>
        </form>
    </div>

<?php
ob_start();
?>
    <script>
        const params = new URLSearchParams(window.location.search);
        const token = params.get("token") || "";
        document.getElementById("token").value = token;

        const msg = document.getElementById("message");
        const btn = document.getElementById("submitBtn");

        if (!token) {
            msg.innerHTML = '<div class="alert alert-danger py-2">Missing token. Please request a new reset link.</div>';
            btn.disabled = true;
        }

        document.getElementById("setPassForm").addEventListener("submit", function (e) {
            e.preventDefault();

            msg.innerHTML = "";

            const password = document.getElementById("password").value;
            const confirm = document.getElementById("confirm_password").value;

            if (password !== confirm) {
                msg.innerHTML = '<div class="alert alert-danger py-2">Passwords do not match.</div>';
                return;
            }

            btn.disabled = true;
            btn.textContent = "Updating…";

            const body = new URLSearchParams();
            body.append("token", token);
            body.append("password", password);
            body.append("confirm_password", confirm);

            fetch(this.action, {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8" },
                body: body.toString()
            })
                .then((res) => res.json())
                .then((data) => {
                    if (data.success) {
                        msg.innerHTML = '<div class="alert alert-success py-2">Password updated. Redirecting to login...</div>';
                        setTimeout(() => {
                            window.location.href = "login.php";
                        }, 1200);
                        return;
                    }

                    msg.innerHTML = '<div class="alert alert-danger py-2">' + (data.message || "Failed to update password.") + "</div>";
                    btn.disabled = false;
                    btn.textContent = "Update Password";
                })
                .catch(() => {
                    msg.innerHTML = '<div class="alert alert-danger py-2">Network error. Please try again.</div>';
                    btn.disabled = false;
                    btn.textContent = "Update Password";
                });
        });
    </script>
<?php
$authFooterExtra = ob_get_clean();
include __DIR__ . '/layouts/auth_footer.php';
