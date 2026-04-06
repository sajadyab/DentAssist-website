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
if (!$isAdmin && in_array($activeTab, ['users', 'clinic', 'subscription_plans'])) {
    header('Location: ' . url('settings/index.php?tab=profile'));
    exit;
}

// Helper function to get clinic setting
function getClinicSetting($key, $default = '') {
    global $db;
    $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = ?", [$key]);
    return $result ? $result['setting_value'] : $default;
}

// Get users for admin view (admin only)
$users = [];
if ($isAdmin) {
    $users = $db->fetchAll(
        "SELECT id, username, email, full_name, role, phone, is_admin, is_active, last_login, created_at 
         FROM users ORDER BY role, is_admin DESC, full_name"
    );
}

// Get current user data
$currentUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [Auth::userId()]);

// Get clinic settings for display (admin only)
$clinicName = $clinicPhone = $clinicEmail = $clinicAddress = $openingHours = '';
$allowPoints = 1;
$allowReferrals = 1;
$allowSubscription = 1;

if ($isAdmin) {
    $clinicName = getClinicSetting('clinic_name', 'Dental Clinic');
    $clinicPhone = getClinicSetting('clinic_phone', '(555) 123-4567');
    $clinicEmail = getClinicSetting('clinic_email', 'info@dentalclinic.com');
    $clinicAddress = getClinicSetting('clinic_address', '123 Main St, Anytown, USA');
    $openingHours = getClinicSetting('opening_hours', 'Monday-Friday: 9am - 5pm\nSaturday: 9am - 1pm\nSunday: Closed');
    $allowPoints = getClinicSetting('allow_points_view', '1');
    $allowReferrals = getClinicSetting('allow_referrals_view', '1');
    $allowSubscription = getClinicSetting('allow_subscription_view', '1');
}

include '../layouts/header.php';
?>

<!-- Custom Dental Theme CSS -->

<div class="container-fluid settings-page">
    <!-- Page Header with Dental Icon -->
    <div class="page-header d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <h1><i class="fas fa-tooth me-2"></i> <?php echo __('settings_Page'); ?></h1>
            <p class="mb-0">
                <?php if ($isAdmin): ?>
                    <i class="fas fa-shield-alt me-1"></i> Full administrative access
                <?php elseif ($isDoctor): ?>
                    <i class="fas fa-user-md me-1"></i> Doctor access – limited settings
                <?php else: ?>
                    <i class="fas fa-user me-1"></i> Profile & language settings
                <?php endif; ?>
            </p>
        </div>
        <div class="mt-2 mt-sm-0">
            <span class="badge bg-light text-dark p-2">
                <i class="fas fa-calendar-alt me-1"></i> <?php echo date('F j, Y'); ?>
            </span>
        </div>
    </div>

    <div id="message"></div>

    <!-- Tabs -->
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
        <li class="nav-item">
            <a class="nav-link <?php echo $activeTab == 'subscription_plans' ? 'active' : ''; ?>" 
               href="<?php echo url('settings/index.php?tab=subscription_plans'); ?>">
                <i class="fas fa-crown"></i> Subscription Plans
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
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
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
                            <input type="text" class="form-control" value="Yes" disabled style="background-color: #ecfdf5; color: #065f46;">
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
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
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
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
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
                            <tr>
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
                            </tr>
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
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" style="display: inline-block;">
                                            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                            <button type="submit" name="toggle_user_status" class="btn btn-sm btn-warning" title="Toggle Status">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" style="display: inline-block;">
                                            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_admin" value="<?php echo $user['is_admin']; ?>">
                                            <button type="submit" name="toggle_admin_status" class="btn btn-sm btn-info" title="Toggle Admin Privileges">
                                                <i class="fas fa-crown"></i>
                                            </button>
                                        </form>
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" style="display: inline-block;" 
                                              onsubmit="return confirm('Reset password for <?php echo htmlspecialchars($user['full_name']); ?>?');">
                                            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" name="reset_user_password" class="btn btn-sm btn-secondary" title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </form>
                                        <?php if ($user['id'] != Auth::userId()): ?>
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" style="display: inline-block;" 
                                              onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user['full_name']); ?>?');">
                                            <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
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
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
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

                    <!-- Checkboxes for points, subscription, and referrals -->
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="allow_points" id="allow_points" value="1" <?php echo $allowPoints ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_points">
                            <i class="fas fa-star"></i> Allow patients to view points and rewards
                        </label>
                        <small class="d-block text-muted">When disabled, points page and all points displays will be hidden from patients.</small>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="allow_subscription" id="allow_subscription" value="1" <?php echo $allowSubscription ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_subscription">
                            <i class="fas fa-crown"></i> Allow patients to view subscription plans
                        </label>
                        <small class="d-block text-muted">When disabled, subscription page and related displays will be hidden from patients.</small>
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

    <?php elseif ($activeTab == 'subscription_plans' && $isAdmin): ?>
        <!-- Subscription Plans Management (Admin Only) -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-crown"></i> Manage Subscription Plans</h5>
                <small>Configure prices, features, and availability of subscription plans</small>
            </div>
            <div class="card-body">
                <?php if (isset($planSuccess)): ?>
                    <div class="alert alert-success"><?php echo $planSuccess; ?></div>
                <?php endif; ?>
                <?php if (isset($planError)): ?>
                    <div class="alert alert-danger"><?php echo $planError; ?></div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Plan Key</th>
                                <th>Plan Name</th>
                                <th>Monthly Price ($)</th>
                                <th>Annual Price ($)</th>
                                <th>Features</th>
                                <th>Active</th>
                                <th>Order</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $plans = $db->fetchAll("SELECT * FROM subscription_plans ORDER BY display_order, monthly_price");
                            foreach ($plans as $plan):
                            ?>
                            <tr>
                                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                                    <input type="hidden" name="plan_key" value="<?php echo $plan['plan_key']; ?>">
                                    <td><strong><?php echo htmlspecialchars($plan['plan_key']); ?></strong></td>
                                    <td><input type="text" name="plan_name" class="form-control" value="<?php echo htmlspecialchars($plan['plan_name']); ?>" required></td>
                                    <td><input type="number" step="0.01" name="monthly_price" class="form-control" value="<?php echo $plan['monthly_price']; ?>" required></td>
                                    <td><input type="number" step="0.01" name="annual_price" class="form-control" value="<?php echo $plan['annual_price']; ?>" required></td>
                                    <td><textarea name="features" class="form-control" rows="3"><?php echo htmlspecialchars($plan['features']); ?></textarea><small class="text-muted">Separate features with new lines</small></td>
                                    <td class="text-center"><input type="checkbox" name="is_active" value="1" <?php echo $plan['is_active'] ? 'checked' : ''; ?>></td>
                                    <td><input type="number" name="display_order" class="form-control" value="<?php echo $plan['display_order']; ?>" style="width:70px"></td>
                                    <td><button type="submit" name="update_plan" class="btn btn-primary btn-sm">Save</button></td>
                                </form>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> Note: Changing prices does not affect existing active subscriptions. New subscriptions will use updated prices.
                </div>
            </div>
        </div>

    <?php elseif ($activeTab == 'language'): ?>
        <!-- Language Switcher (All Users) -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-language"></i> <?php echo __('language_settings'); ?></h5>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($activeTab); ?>">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('select_language'); ?></label>
                        <select name="language" class="form-select">
                            <option value="en" <?php echo getLanguage() == 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="ar" <?php echo getLanguage() == 'ar' ? 'selected' : ''; ?>>Ø§Ù„Ø¹Ø±Ø¨ÙŠØ© (Arabic)</option>
                            <option value="fr" <?php echo getLanguage() == 'fr' ? 'selected' : ''; ?>>FranÃ§ais (French)</option>
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
// Show/hide passwords functionality (unchanged)
document.getElementById('showPasswords')?.addEventListener('change', function(e) {
    const passwordFields = document.querySelectorAll('input[type="password"]');
    passwordFields.forEach(field => {
        field.type = e.target.checked ? 'text' : 'password';
    });
});
</script>

<?php include '../layouts/footer.php'; ?>
