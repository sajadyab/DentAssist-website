<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$isAdmin = Auth::isAdmin();
$userRole = $_SESSION['role'] ?? '';
$isDoctor = ($userRole === 'doctor');

$db = Database::getInstance();
$activeTab = $_GET['tab'] ?? 'profile';

// Non‑admin users cannot access admin tabs
if (!$isAdmin && in_array($activeTab, ['users', 'clinic', 'subscription_plans'])) {
    header('Location: ' . url('settings/index.php?tab=profile'));
    exit;
}

// Helper to get clinic setting
function getClinicSetting($key, $default = '') {
    global $db;
    $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = ?", [$key]);
    return $result ? $result['setting_value'] : $default;
}

// Fetch users for admin panel
$users = [];
if ($isAdmin) {
    $users = $db->fetchAll(
        "SELECT id, username, email, full_name, role, phone, is_admin, is_active, last_login, created_at 
         FROM users ORDER BY role, is_admin DESC, full_name"
    );
}

// Current user data
$currentUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [Auth::userId()]);

// Clinic settings (admin only)
$clinicName = $clinicPhone = $clinicEmail = $clinicAddress = $openingHours = '';
$allowPoints = 1;
$allowReferrals = 1;
$allowSubscription = 1;

if ($isAdmin) {
    $clinicName = getClinicSetting('clinic_name', 'Dental Clinic');
    $clinicPhone = getClinicSetting('clinic_phone', '(555) 123-4567');
    $clinicEmail = getClinicSetting('clinic_email', 'info@dentalclinic.com');
    $clinicAddress = getClinicSetting('clinic_address', '123 Main St, Anytown, USA');
    $openingHours = getClinicSetting('opening_hours', "Monday-Friday: 9am - 5pm\nSaturday: 9am - 1pm\nSunday: Closed");
    $allowPoints = getClinicSetting('allow_points_view', '1');
    $allowReferrals = getClinicSetting('allow_referrals_view', '1');
    $allowSubscription = getClinicSetting('allow_subscription_view', '1');
}

include '../layouts/header.php';
?>

<div class="container-fluid settings-page">
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

    <!-- Tab Contents -->
    <?php if ($activeTab == 'profile'): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-user-edit"></i> <?php echo __('my_profile'); ?></h5></div>
            <div class="card-body">
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="profile">
                    <input type="hidden" name="settings_action" value="update_profile">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo __('full_name'); ?> *</label>
                            <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($currentUser['full_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo __('email'); ?> *</label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?php echo __('phone'); ?></label>
                            <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($currentUser['phone']); ?>">
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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save_changes'); ?></button>
                </form>
            </div>
        </div>

    <?php elseif ($activeTab == 'password'): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-key"></i> <?php echo __('change_password'); ?></h5></div>
            <div class="card-body">
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="password">
                    <input type="hidden" name="settings_action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('current_password'); ?> *</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('new_password'); ?> *</label>
                        <input type="password" class="form-control" name="new_password" id="new_password" pattern=".{6,}" title="Minimum 6 characters" required>
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
                    <button type="submit" class="btn btn-primary"><i class="fas fa-sync-alt"></i> <?php echo __('update_password'); ?></button>
                </form>
            </div>
        </div>

    <?php elseif ($activeTab == 'users' && $isAdmin): ?>
        <!-- Add User Form -->
        <div class="card mb-4">
            <div class="card-header"><h5><i class="fas fa-user-plus"></i> <?php echo __('add_new_user'); ?></h5></div>
            <div class="card-body">
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="users">
                    <input type="hidden" name="settings_action" value="add_user">
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
                            <input type="text" class="form-control" name="password" placeholder="Auto-generated if left empty">
                            <small class="text-muted">Leave empty to auto-generate</small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_admin" id="is_admin">
                                <label class="form-check-label" for="is_admin"><i class="fas fa-crown"></i> Grant Admin Privileges</label>
                                <br><small class="text-muted">Admins can manage users, clinic info, and all system settings</small>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success"><i class="fas fa-plus"></i> <?php echo __('add_user'); ?></button>
                </form>
            </div>
        </div>

        <!-- Users List -->
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-users"></i> <?php echo __('system_users'); ?></h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th>ID</th><th><?php echo __('username'); ?></th><th><?php echo __('full_name'); ?></th><th><?php echo __('email'); ?></th><th><?php echo __('role'); ?></th><th>Admin</th><th><?php echo __('phone'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('last_login'); ?></th><th><?php echo __('actions'); ?></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge bg-<?php echo $user['role'] == 'doctor' ? 'danger' : ($user['role'] == 'assistant' ? 'warning' : 'info'); ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><?php echo $user['is_admin'] ? '<span class="badge bg-primary"><i class="fas fa-crown"></i> Admin</span>' : '<span class="badge bg-secondary">No</span>'; ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo $user['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>'; ?></td>
                                <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" style="display:inline-block;">
                                            <input type="hidden" name="tab" value="users">
                                            <input type="hidden" name="settings_action" value="toggle_user_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" title="Toggle Status"><i class="fas fa-power-off"></i></button>
                                        </form>
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" style="display:inline-block;">
                                            <input type="hidden" name="tab" value="users">
                                            <input type="hidden" name="settings_action" value="toggle_admin_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_admin" value="<?php echo $user['is_admin']; ?>">
                                            <button type="submit" class="btn btn-sm btn-info" title="Toggle Admin Privileges"><i class="fas fa-crown"></i></button>
                                        </form>
                                        <!-- Reset password form – custom handler will show the new password -->
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" class="reset-pwd-form" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>" style="display:inline-block;">
                                            <input type="hidden" name="tab" value="users">
                                            <input type="hidden" name="settings_action" value="reset_user_password">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary" title="Reset Password"><i class="fas fa-key"></i></button>
                                        </form>
                                        <?php if ($user['id'] != Auth::userId()): ?>
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" style="display:inline-block;" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user['full_name']); ?>?');">
                                            <input type="hidden" name="tab" value="users">
                                            <input type="hidden" name="settings_action" value="delete_user">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
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
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-hospital"></i> <?php echo __('clinic_info'); ?></h5></div>
            <div class="card-body">
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="clinic">
                    <input type="hidden" name="settings_action" value="update_clinic">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('clinic_name'); ?></label>
                        <input type="text" class="form-control" name="clinic_name" value="<?php echo htmlspecialchars($clinicName); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('clinic_phone'); ?></label>
                        <input type="text" class="form-control" name="clinic_phone" value="<?php echo htmlspecialchars($clinicPhone); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('clinic_email'); ?></label>
                        <input type="email" class="form-control" name="clinic_email" value="<?php echo htmlspecialchars($clinicEmail); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('clinic_address'); ?></label>
                        <textarea class="form-control" name="clinic_address" rows="3" required><?php echo htmlspecialchars($clinicAddress); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('working_hours'); ?></label>
                        <textarea class="form-control" name="opening_hours" rows="4" required><?php echo htmlspecialchars($openingHours); ?></textarea>
                        <small class="text-muted">Enter each day on a new line</small>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="allow_points" id="allow_points" value="1" <?php echo $allowPoints ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_points"><i class="fas fa-star"></i> Allow patients to view points and rewards</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="allow_subscription" id="allow_subscription" value="1" <?php echo $allowSubscription ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_subscription"><i class="fas fa-crown"></i> Allow patients to view subscription plans</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="allow_referrals" id="allow_referrals" value="1" <?php echo $allowReferrals ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_referrals"><i class="fas fa-share-alt"></i> Allow patients to view referrals and referral code</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save_changes'); ?></button>
                </form>
            </div>
        </div>

    <?php elseif ($activeTab == 'subscription_plans' && $isAdmin): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-crown"></i> Manage Subscription Plans</h5></div>
            <div class="card-body">
                <?php
                $subscriptionPlans = $db->fetchAll('SELECT * FROM subscription_plans ORDER BY display_order, monthly_price');
                ?>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr><th>Plan Key</th><th>Plan Name</th><th>Monthly Price ($)</th><th>Annual Price ($)</th><th>Features</th><th>Active</th><th>Order</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subscriptionPlans as $plan): ?>
                            <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="mb-2">
                                <input type="hidden" name="tab" value="subscription_plans">
                                <input type="hidden" name="settings_action" value="update_plan">
                                <input type="hidden" name="plan_key" value="<?php echo htmlspecialchars($plan['plan_key']); ?>">
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($plan['plan_key']); ?></strong></td>
                                    <td><input type="text" name="plan_name" class="form-control" value="<?php echo htmlspecialchars($plan['plan_name']); ?>" required></td>
                                    <td><input type="number" step="0.01" name="monthly_price" class="form-control" value="<?php echo htmlspecialchars($plan['monthly_price']); ?>" required></td>
                                    <td><input type="number" step="0.01" name="annual_price" class="form-control" value="<?php echo htmlspecialchars($plan['annual_price']); ?>" required></td>
                                    <td><textarea name="features" class="form-control" rows="2"><?php echo htmlspecialchars($plan['features']); ?></textarea><small class="text-muted">Separate with new lines</small></td>
                                    <td class="text-center"><input type="checkbox" name="is_active" value="1" <?php echo $plan['is_active'] ? 'checked' : ''; ?>></td>
                                    <td><input type="number" name="display_order" class="form-control" value="<?php echo (int)$plan['display_order']; ?>" style="width:70px"></td>
                                    <td><button type="submit" class="btn btn-primary btn-sm">Save</button></td>
                                </tr>
                            </form>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> Note: Changing prices does not affect existing active subscriptions.
                </div>
            </div>
        </div>

    <?php elseif ($activeTab == 'language'): ?>
        <div class="card">
            <div class="card-header"><h5><i class="fas fa-language"></i> <?php echo __('language_settings'); ?></h5></div>
            <div class="card-body">
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="language">
                    <input type="hidden" name="settings_action" value="change_language">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('select_language'); ?></label>
                        <select name="language" class="form-select">
                            <option value="en" <?php echo getLanguage() == 'en' ? 'selected' : ''; ?>>English</option>
                            <option value="ar" <?php echo getLanguage() == 'ar' ? 'selected' : ''; ?>>العربية (Arabic)</option>
                            <option value="fr" <?php echo getLanguage() == 'fr' ? 'selected' : ''; ?>>Français (French)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save_changes'); ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Password show/hide toggle -->
<script>
document.getElementById('showPasswords')?.addEventListener('change', function(e) {
    document.querySelectorAll('input[type="password"]').forEach(field => {
        field.type = e.target.checked ? 'text' : 'password';
    });
});
</script>

<!-- Global AJAX handler for all forms with data-api, plus special handling for password reset -->
<script>
(function() {
    // Helper to show messages in the #message div
    function showMessage(msg, type = 'success') {
        const msgDiv = document.getElementById('message');
        if (msgDiv) {
            msgDiv.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                                    ${msg}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>`;
            // Auto-scroll to message
            msgDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // Handle all regular forms (with data-api attribute)
    document.querySelectorAll('form[data-api]').forEach(form => {
        // Skip reset-pwd forms – they have their own handler
        if (form.classList.contains('reset-pwd-form')) return;

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const apiUrl = this.getAttribute('data-api');
            const messageTarget = this.getAttribute('data-message-target') || '#message';
            const formData = new FormData(this);

            try {
                const response = await fetch(apiUrl, { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    showMessage(result.message || 'Operation successful.', 'success');
                    // If the response indicates a page reload is needed (e.g., language change), reload after 1 sec
                    if (result.reload) setTimeout(() => location.reload(), 1000);
                    // Optionally reset form if needed
                    if (result.reset_form) this.reset();
                } else {
                    showMessage(result.message || 'An error occurred.', 'danger');
                }
            } catch (error) {
                showMessage('Network error: ' + error.message, 'danger');
            }
        });
    });

    // Special handler for password reset – shows the new password in a modal
    document.querySelectorAll('form.reset-pwd-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const apiUrl = this.action; // action points to api/settings.php
            const formData = new FormData(this);
            const userName = this.getAttribute('data-user-name') || 'User';

            try {
                const response = await fetch(apiUrl, { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success && result.new_password) {
                    // Display the new password in a modal (Bootstrap)
                    const modalHtml = `
                        <div class="modal fade" id="passwordResetModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title"><i class="fas fa-key"></i> Password Reset for ${escapeHtml(userName)}</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>The password has been reset successfully.</p>
                                        <div class="alert alert-info">
                                            <strong>New password:</strong> <code style="font-size:1.2rem">${escapeHtml(result.new_password)}</code>
                                        </div>
                                        <p class="text-muted small">Please copy this password and share it securely with the user. It will not be shown again.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-primary" id="copyPasswordBtn">Copy to Clipboard</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    // Remove any existing modal
                    const existingModal = document.getElementById('passwordResetModal');
                    if (existingModal) existingModal.remove();

                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    const modalElement = document.getElementById('passwordResetModal');
                    const modal = new bootstrap.Modal(modalElement);
                    modal.show();

                    // Copy button functionality
                    document.getElementById('copyPasswordBtn')?.addEventListener('click', () => {
                        navigator.clipboard.writeText(result.new_password);
                        const btn = document.getElementById('copyPasswordBtn');
                        btn.textContent = 'Copied!';
                        setTimeout(() => { btn.textContent = 'Copy to Clipboard'; }, 2000);
                    });

                    // Also show a success message in the main message area
                    showMessage(`Password for ${userName} has been reset.`, 'success');
                } else {
                    showMessage(result.message || 'Password reset failed.', 'danger');
                }
            } catch (error) {
                showMessage('Network error: ' + error.message, 'danger');
            }
        });
    });

    // Simple escape function to prevent XSS
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        }).replace(/[\uD800-\uDBFF][\uDC00-\uDFFF]/g, function(c) {
            return c;
        });
    }
})();
</script>

<?php include '../layouts/footer.php'; ?>