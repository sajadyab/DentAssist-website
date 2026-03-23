<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Attempt login using Auth class
    if (Auth::login($username, $password)) {

        // Redirect based on user role
        if ($_SESSION['role'] == 'patient') {
        header('Location: patient/index.php');
    } else {
        header('Location: dashboard.php');
    }
    exit;
    } else {
        $error = 'Invalid username or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 14px;
            text-align: center;
            margin-bottom: 18px;
            font-weight: 600;
            padding: 0;
            background: transparent;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 13px;
            margin-bottom: 18px;
            transition: background-color 0.2s ease;
        }

        .form-control.error-field,
        .form-control.error-field:focus,
        .form-control.error-field:active,
        .form-control.error-field:focus-visible {
            background-color: #ffe6e6 !important;
            border-color: #dc3545 !important;
            color: #212529 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }

        /* override browser autofill style */
        input.form-control.error-field:-webkit-autofill,
        input.form-control.error-field:-webkit-autofill:focus,
        input.form-control.error-field:-webkit-autofill:hover {
            box-shadow: 0 0 0 1000px #ffe6e6 inset !important;
            -webkit-text-fill-color: #212529 !important;
            transition: background-color 5000s ease-in-out 0s !important;
        }

        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            padding: 12px;
            width: 100%;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Mobile responsive for better eye comfort */
        @media (max-width: 768px) {
            .login-card {
                padding: 25px 20px;
                max-width: 320px;
                border-radius: 12px;
            }
            
            .login-header h1 {
                font-size: 20px;
                margin-bottom: 6px;
            }
            
            .login-header p {
                font-size: 12px;
            }
            
            .error-message {
                font-size: 12px;
                margin-bottom: 15px;
            }
            
            .form-control {
                padding: 10px 12px;
                margin-bottom: 15px;
                font-size: 14px;
            }
            
            .btn-login {
                padding: 10px;
                font-size: 14px;
            }
            
            .login-header {
                margin-bottom: 15px;
            }
        }
        
    </style>
</head>
<body>
   <div class="login-card">
    <div class="login-header">
        <h1><?php echo SITE_NAME; ?></h1>
        <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
        <div class="error-message"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <input type="text" 
                   class="form-control<?php echo $error ? ' error-field' : ''; ?>" 
                   name="username" 
                   placeholder="Username or Email" 
                   required 
                   autofocus>
        </div>

        <div class="mb-3">
            <input type="password" 
                   class="form-control<?php echo $error ? ' error-field' : ''; ?>" 
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
        <small>Don't have an account? <a href="register.php">Register as a patient</a></small>
    </div>
</div>
</body>
</html>