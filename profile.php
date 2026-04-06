<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$userId = Auth::userId();

$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId], "i");

$pageTitle = 'My Profile';

include 'layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">My Profile</h1>
    </div>

    <div id="message"></div>

    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <img src="/Dental/assets/images/logo.png"
                        alt="App Logo"
                        width="50"
                        height="50">
                    <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                    <p class="text-muted"><?php echo ucfirst($user['role']); ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo $user['email']; ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo $user['phone'] ?? 'Not provided'; ?></p>
                    <p><small>Member since: <?php echo formatDate($user['created_at']); ?></small></p>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <!-- Edit Profile -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Edit Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="api/profile.php" data-api="api/profile.php" data-message-target="#message">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo $user['email']; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo $user['phone']; ?>">
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="api/profile.php" data-api="api/profile.php" data-message-target="#message">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'layouts/footer.php'; ?>