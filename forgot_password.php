<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Reset Password - DentAssist</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css" />

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

        .reset-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            padding: 36px;
            width: 100%;
            max-width: 420px;
        }

        .reset-header {
            text-align: center;
            margin-bottom: 18px;
        }

        .reset-header p {
            color: #666;
            font-size: 13px;
        }

        .form-control {
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 14px;
        }

        .btn-reset {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            padding: 12px;
            width: 100%;
            cursor: pointer;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
        }

        .btn-reset:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        @media (max-width: 768px) {
            .reset-card {
                padding: 25px;
                max-width: 320px;
                border-radius: 12px;
            }

            .reset-header h4 {
                font-size: 1.2rem;
                margin-bottom: 8px;
            }

            .form-control {
                padding: 10px;
                font-size: 14px;
            }

            label {
                font-size: 0.95rem;
                font-weight: 600;
            }

            .btn-reset {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>

<body>

    <div class="reset-card">

        <div class="reset-header">
            <h4>DentAssist Password Reset</h4>
            <p>Please enter your <strong>username</strong> and your registered <strong>email</strong> (today) or phone number.</p>
        </div>

        <div id="message"></div>

        <form id="resetForm" action="api/forgot_pass.php" method="post">

            <label for="username">Username</label>
            <input type="text" name="username" id="username" class="form-control" required autocomplete="username">
            <button type="submit" class="btn-reset mt-3" id="submitBtn">Reset Password</button>

            <p class="text-center mt-3 mb-0 small"><a href="login.php">Back to login</a></p>

        </form>

    </div>

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

</body>

</html>