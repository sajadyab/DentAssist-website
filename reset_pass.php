<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Set New Password - DentAssist</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 16px;
        }
        .card-reset {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 36px;
            width: 100%;
            max-width: 440px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:disabled {
            opacity: 0.7;
        }
    </style>
</head>
<body>
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
</body>
</html>

