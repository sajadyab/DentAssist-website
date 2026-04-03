<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$isAdmin = Auth::isAdmin(); // Check if user is admin (not just doctor)
$userRole = $_SESSION['role'] ?? '';
$isDoctor = ($userRole === 'doctor'); // Regular doctor (not admin)

$db = Database::getInstance();
$activeTab = $_GET['tab'] ?? 'profile'; // default to profile

// If non-admin tries to access admin tabs, redirect to profile
if (!$isAdmin && in_array($activeTab, ['users', 'clinic'])) {
    header('Location: ' . url('settings/index.php?tab=profile'));
    exit;
}

// Helper function to get clinic setting
function getClinicSetting($key, $default = '') {
    global $db;
    $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = ?", [$key]);
    return $result ? $result['setting_value'] : $default;
}

// Helper function to update clinic setting (admin only)
// Helper function to update clinic setting (admin only)
function updateClinicSetting($key, $value) {
    global $db;
    try {
        // First check if the setting exists
        $existing = $db->fetchOne("SELECT id FROM clinic_settings WHERE setting_key = ?", [$key]);
        if ($existing) {
            // Update existing record
            $result = $db->execute("UPDATE clinic_settings SET setting_value = ? WHERE setting_key = ?", [$value, $key], "ss");
            if ($result === false) {
                error_log("updateClinicSetting: UPDATE failed for $key");
                return false;
            }
        } else {
            // Insert new record
            $result = $db->execute("INSERT INTO clinic_settings (setting_key, setting_value) VALUES (?, ?)", [$key, $value], "ss");
            if ($result === false) {
                error_log("updateClinicSetting: INSERT failed for $key");
                return false;
            }
        }
        error_log("updateClinicSetting: Successfully updated $key to $value");
        return true;
    } catch (Exception $e) {
        error_log("updateClinicSetting: Exception - " . $e->getMessage());
        return false;
    }
}
// Handle profile update (available to all users)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $userId = Auth::userId();
    $fullName = $_POST['full_name'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Check if email exists for other users
    $existing = $db->fetchOne("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $userId]);
    if ($existing) {
        $error = __('email_exists');
    } else {
        $db->execute("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?", 
                     [$fullName, $phone, $email, $userId], "sssi");
        $success = __('profile_updated');
        // Update session if needed
        $_SESSION['full_name'] = $fullName;
    }
}

// Handle password change (available to all users)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $userId = Auth::userId();
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Get current user
    $user = $db->fetchOne("SELECT password_hash FROM users WHERE id = ?", [$userId]);
    
    if (!password_verify($currentPassword, $user['password_hash'])) {
        $error = __('current_password_incorrect');
    } elseif (strlen($newPassword) < 6) {
        $error = __('password_too_short');
    } elseif ($newPassword !== $confirmPassword) {
        $error = __('passwords_do_not_match');
    } else {
        $newHash = Auth::hashPassword($newPassword);
        $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$newHash, $userId], "si");
        $success = __('password_updated');
    }
}

// Handle clinic info update (admin only)
// Handle clinic info update (admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_clinic']) && $isAdmin) {
    $clinicName = $_POST['clinic_name'] ?? '';
    $clinicPhone = $_POST['clinic_phone'] ?? '';
    $clinicEmail = $_POST['clinic_email'] ?? '';
    $clinicAddress = $_POST['clinic_address'] ?? '';
    $openingHours = $_POST['opening_hours'] ?? '';
    
    // Update all clinic settings
    updateClinicSetting('clinic_name', $clinicName);
    updateClinicSetting('clinic_phone', $clinicPhone);
    updateClinicSetting('clinic_email', $clinicEmail);
    updateClinicSetting('clinic_address', $clinicAddress);
    updateClinicSetting('opening_hours', $openingHours);
    
    // Handle points and referrals toggles
    $allowPoints = isset($_POST['allow_points']) ? 1 : 0;
    $allowReferrals = isset($_POST['allow_referrals']) ? 1 : 0;
    
    $pointsUpdate = updateClinicSetting('allow_points_view', $allowPoints);
    $referralsUpdate = updateClinicSetting('allow_referrals_view', $allowReferrals);
    
    if ($pointsUpdate && $referralsUpdate) {
        $success = __('clinic_info_updated');
        // Refresh the values for display
        $allowPoints = $allowPoints;
        $allowReferrals = $allowReferrals;
    } else {
        $error = 'Error updating points/referrals settings. Please try again.';
    }
}

// Handle language change (available to all users)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_language'])) {
    $newLang = $_POST['language'] ?? 'en';
    if (setLanguage($newLang)) {
        $success = __('language_updated');
    }
    header('Location: ' . url('settings/index.php?tab=language'));
    exit;
}

// Handle user management (admin only)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $isAdmin) {
    if (isset($_POST['add_user'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $fullName = $_POST['full_name'];
        $role = $_POST['role'];
        $phone = $_POST['phone'] ?? '';
        $isAdminUser = isset($_POST['is_admin']) ? 1 : 0;
        $password = $_POST['password'] ?? generateRandomPassword();
        
        // Check if username exists
        $existing = $db->fetchOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existing) {
            $error = __('username_email_exists');
        } else {
            $passwordHash = Auth::hashPassword($password);
            $db->execute(
                "INSERT INTO users (username, email, password_hash, full_name, role, phone, is_admin, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
                [$username, $email, $passwordHash, $fullName, $role, $phone, $isAdminUser],
                "ssssssi"
            );
            $success = __('user_added') . " - Password: " . $password;
        }
    } elseif (isset($_POST['toggle_user_status'])) {
        $userId = $_POST['user_id'];
        $currentStatus = $_POST['current_status'];
        $newStatus = $currentStatus == 1 ? 0 : 1;
        $db->execute("UPDATE users SET is_active = ? WHERE id = ?", [$newStatus, $userId], "ii");
        $success = __('user_status_updated');
    } elseif (isset($_POST['toggle_admin_status'])) {
        $userId = $_POST['user_id'];
        $currentAdmin = $_POST['current_admin'];
        $newAdmin = $currentAdmin == 1 ? 0 : 1;
        // Don't allow removing admin from yourself
        if ($userId == Auth::userId() && $newAdmin == 0) {
            $error = __('cannot_remove_own_admin');
        } else {
            $db->execute("UPDATE users SET is_admin = ? WHERE id = ?", [$newAdmin, $userId], "ii");
            $success = __('admin_status_updated');
        }
    } elseif (isset($_POST['delete_user'])) {
        $userId = $_POST['user_id'];
        // Don't allow deleting own account
        if ($userId != Auth::userId()) {
            $db->execute("DELETE FROM users WHERE id = ?", [$userId], "i");
            $success = __('user_deleted');
        } else {
            $error = __('cannot_delete_self');
        }
    } elseif (isset($_POST['reset_user_password'])) {
        $userId = $_POST['user_id'];
        $newPassword = generateRandomPassword();
        $passwordHash = Auth::hashPassword($newPassword);
        $db->execute("UPDATE users SET password_hash = ? WHERE id = ?", [$passwordHash, $userId], "si");
        $success = __('password_reset') . " - New Password: " . $newPassword;
    }
}

// Get users for admin view (admin only)
$users = [];
if ($isAdmin) {
    $users = $db->fetchAll(
        "SELECT id, username, email, full_name, role, phone, is_admin, is_active, last_login, created_at 
         FROM users 
         WHERE role <> 'patient'
         ORDER BY role, is_admin DESC, full_name"
    );
}

// Get current user data
$currentUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [Auth::userId()]);

// Get clinic settings for display (admin only)
$clinicName = $clinicPhone = $clinicEmail = $clinicAddress = $openingHours = '';
$allowPoints = $allowReferrals = 1;
if ($isAdmin) {
    $clinicName = getClinicSetting('clinic_name', 'Dental Clinic');
    $clinicPhone = getClinicSetting('clinic_phone', '(555) 123-4567');
    $clinicEmail = getClinicSetting('clinic_email', 'info@dentalclinic.com');
    $clinicAddress = getClinicSetting('clinic_address', '123 Main St, Anytown, USA');
    $openingHours = getClinicSetting('opening_hours', 'Monday-Friday: 9am - 5pm\nSaturday: 9am - 1pm\nSunday: Closed');
    $allowPoints = getClinicSetting('allow_points_view', '1');
    $allowReferrals = getClinicSetting('allow_referrals_view', '1');
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">
        <?php echo __('settings_title'); ?>
        <?php if ($isAdmin): ?>
            <small class="text-muted">(Admin Access)</small>
        <?php elseif ($isDoctor): ?>
            <small class="text-muted">(Doctor Access - Limited)</small>
        <?php else: ?>
            <small class="text-muted">(Limited Access)</small>
        <?php endif; ?>
    </h1>

    <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs - Different tabs based on role -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab == 'profile' ? 'active' : ''; ?>" 
               href="<?php echo url('settings/index.php?tab=profile'); ?>">
                <i class="fas fa-user"></i> <?php echo __('my_profile'); ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab == 'password' ? 'active' : ''; ?>" 
               href="<?php echo url('settings/index.php?tab=password'); ?>">
                <i class="fas fa-key"></i> <?php echo __('change_password'); ?>
            </a>
        </li>
        <?php if ($isAdmin): ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab == 'users' ? 'active' : ''; ?>" 
               href="<?php echo url('settings/index.php?tab=users'); ?>">
                <i class="fas fa-users"></i> <?php echo __('user_management'); ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab == 'clinic' ? 'active' : ''; ?>" 
               href="<?php echo url('settings/index.php?tab=clinic'); ?>">
                <i class="fas fa-hospital"></i> <?php echo __('clinic_info'); ?>
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab == 'language' ? 'active' : ''; ?>" 
               href="<?php echo url('settings/index.php?tab=language'); ?>">
                <i class="fas fa-globe"></i> <?php echo __('language'); ?>
            </a>
        </li>
    </ul>

    <!-- Tab Content -->
    <?php if ($activeTab == 'profile'): ?>
        <!-- Profile Info (All Users) -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-user-edit"></i> <?php echo __('my_profile'); ?></h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo __('full_name'); ?> *</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo __('email'); ?> *</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo __('phone'); ?></label>
                            <input type="text" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($currentUser['phone']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo __('role'); ?></label>
                            <input type="text" class="form-control" value="<?php echo ucfirst($currentUser['role']); ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo __('username'); ?></label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($currentUser['username']); ?>" disabled>
                        </div>
                        <?php if ($currentUser['is_admin']): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo __('admin_privileges'); ?></label>
                            <input type="text" class="form-control" value="Yes" disabled style="background-color: #d4edda; color: #155724;">
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('save_changes'); ?>
                    </button>
                </form>
            </div>
        </div>

    <?php elseif ($activeTab == 'password'): ?>
        <!-- Change Password (All Users) -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-key"></i> <?php echo __('change_password'); ?></h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('current_password'); ?> *</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('new_password'); ?> *</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" 
                               pattern=".{6,}" title="Minimum 6 characters" required>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('confirm_password'); ?> *</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="showPasswords">
                        <label class="form-check-label" for="showPasswords"><?php echo __('show_passwords'); ?></label>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> <?php echo __('update_password'); ?>
                    </button>
                </form>
            </div>
        </div>

    <?php elseif ($activeTab == 'users' && $isAdmin): ?>
        <!-- User Management (Admin Only) -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-user-plus"></i> <?php echo __('add_new_user'); ?></h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo __('username'); ?> *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo __('full_name'); ?> *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo __('email'); ?> *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo __('role'); ?> *</label>
                            <select class="form-select" name="role" required>
                                <option value="doctor">Doctor</option>
                                <option value="assistant">Assistant</option>
                                <option value="patient">Patient</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo __('phone'); ?></label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo __('password'); ?></label>
                            <input type="text" class="form-control" name="password" 
                                   placeholder="Auto-generated if left empty">
                            <small class="text-muted">Leave empty to auto-generate</small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_admin" id="is_admin">
                                <label class="form-check-label" for="is_admin">
                                    <i class="fas fa-crown"></i> Grant Admin Privileges (Full System Access)
                                </label>
                                <br><small class="text-muted">Admins can manage users, clinic info, and all system settings</small>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_user" class="btn btn-success">
                        <i class="fas fa-plus"></i> <?php echo __('add_user'); ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-users"></i> <?php echo __('system_users'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            32
                                <th>ID</th>
                                <th><?php echo __('username'); ?></th>
                                <th><?php echo __('full_name'); ?></th>
                                <th><?php echo __('email'); ?></th>
                                <th><?php echo __('role'); ?></th>
                                <th>Admin</th>
                                <th><?php echo __('phone'); ?></th>
                                <th><?php echo __('status'); ?></th>
                                <th><?php echo __('last_login'); ?></th>
                                <th><?php echo __('actions'); ?></th>
                            </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php
                                    $badgeClass = $user['role'] == 'doctor' ? 'danger' : ($user['role'] == 'assistant' ? 'warning' : 'info');
                                    ?>
                                    <span class="badge bg-<?php echo $badgeClass; ?>"><?php echo ucfirst($user['role']); ?></span>
                                </td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="badge bg-primary"><i class="fas fa-crown"></i> Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <form method="post" style="display: inline-block;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                            <button type="submit" name="toggle_user_status" class="btn btn-sm btn-warning" title="Toggle Status">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        </form>
                                        <form method="post" style="display: inline-block;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_admin" value="<?php echo $user['is_admin']; ?>">
                                            <button type="submit" name="toggle_admin_status" class="btn btn-sm btn-info" title="Toggle Admin Privileges">
                                                <i class="fas fa-crown"></i>
                                            </button>
                                        </form>
                                        <form method="post" style="display: inline-block;" 
                                              onsubmit="return confirm('Reset password for <?php echo htmlspecialchars($user['full_name']); ?>?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="reset_user_password" class="btn btn-sm btn-secondary" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>
                                        <?php if ($user['id'] != Auth::userId()): ?>
                                        <form method="post" style="display: inline-block;" 
                                              onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user['full_name']); ?>?');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="delete_user" class="btn btn-sm btn-danger" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php elseif ($activeTab == 'clinic' && $isAdmin): ?>
        <!-- Clinic Info (Admin Only) -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-hospital"></i> <?php echo __('clinic_info'); ?></h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('clinic_name'); ?></label>
                        <input type="text" class="form-control" name="clinic_name" 
                               value="<?php echo htmlspecialchars($clinicName); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('clinic_phone'); ?></label>
                        <input type="text" class="form-control" name="clinic_phone" 
                               value="<?php echo htmlspecialchars($clinicPhone); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('clinic_email'); ?></label>
                        <input type="email" class="form-control" name="clinic_email" 
                               value="<?php echo htmlspecialchars($clinicEmail); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('clinic_address'); ?></label>
                        <textarea class="form-control" name="clinic_address" rows="3" required><?php echo htmlspecialchars($clinicAddress); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('working_hours'); ?></label>
                        <textarea class="form-control" name="opening_hours" rows="4" required><?php echo htmlspecialchars($openingHours); ?></textarea>
                        <small class="text-muted">Enter each day on a new line (e.g., Monday-Friday: 9am - 5pm)</small>
                    </div>

                    <!-- NEW: Checkboxes for points and referrals -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="allow_points" id="allow_points" value="1" <?php echo $allowPoints ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_points">
                            <i class="fas fa-star"></i> Allow patients to view points and rewards
                        </label>
                        <small class="d-block text-muted">When disabled, points page and all points displays will be hidden from patients.</small>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="allow_referrals" id="allow_referrals" value="1" <?php echo $allowReferrals ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_referrals">
                            <i class="fas fa-share-alt"></i> Allow patients to view referrals and referral code
                        </label>
                        <small class="d-block text-muted">When disabled, referrals page and referral code display will be hidden.</small>
                    </div>

                    <button type="submit" name="update_clinic" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('save_changes'); ?>
                    </button>
                </form>
            </div>
        </div>

    <?php elseif ($activeTab == 'language'): ?>
        <!-- Language Switcher (All Users) -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-language"></i> <?php echo __('language_settings'); ?></h5>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('select_language'); ?></label>
                        <select name="language" class="form-select">
                            <option value="en" <?php echo getLanguage() == 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="ar" <?php echo getLanguage() == 'ar' ? 'selected' : ''; ?>>العربية (Arabic)</option>
                            <option value="fr" <?php echo getLanguage() == 'fr' ? 'selected' : ''; ?>>Français (French)</option>
                        </select>
                    </div>
                    <button type="submit" name="change_language" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo __('save_changes'); ?>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Show/hide passwords functionality
document.getElementById('showPasswords')?.addEventListener('change', function(e) {
    const passwordFields = document.querySelectorAll('input[type="password"]');
    passwordFields.forEach(field => {
        field.type = e.target.checked ? 'text' : 'password';
    });
});
</script>

<?php include '../layouts/footer.php'; ?>