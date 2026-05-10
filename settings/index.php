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

// Prevent direct access to password as tab
if ($activeTab === 'password') {
    header('Location: ' . url('settings/index.php?tab=profile'));
    exit;
}

// Permission helper (already in functions.php, but define if missing)
if (!function_exists('hasPermission')) {
    function hasPermission($userId, $permissionKey) {
        global $db;
        static $cache = [];
        if (isAdmin($userId)) return true;
        if (!isset($cache[$userId])) {
            $rows = $db->fetchAll("SELECT permission_key, value FROM user_permissions WHERE user_id = ? AND value = 1", [$userId], "i");
            $cache[$userId] = [];
            foreach ($rows as $row) {
                $cache[$userId][$row['permission_key']] = true;
            }
        }
        return isset($cache[$userId][$permissionKey]);
    }
}
if (!function_exists('isAdmin')) {
    function isAdmin($userId) {
        global $db;
        static $adminCache = [];
        if (!isset($adminCache[$userId])) {
            $user = $db->fetchOne("SELECT is_admin FROM users WHERE id = ?", [$userId], "i");
            $adminCache[$userId] = $user && $user['is_admin'] == 1;
        }
        return $adminCache[$userId];
    }
}

// Define all settings tabs and their permission keys
$settingsTabs = [
    'profile' => 'access_settings_profile',          // my profile – always allowed
    'users' => 'access_settings_users',
    'clinic' => 'access_settings_clinic',
    'access_control' => 'access_settings_permissions', // only for admin
    'points_management' => 'access_settings_points_management',
    'subscription_plans' => 'access_settings_subscription_plans',
];

// Determine which tabs the current user can see
$allowedTabs = [];
foreach ($settingsTabs as $tab => $permKey) {
    if ($tab === 'profile') {
        $allowedTabs[] = 'profile';
    } elseif ($isAdmin) {
        $allowedTabs[] = $tab;
    } elseif (hasPermission(Auth::userId(), $permKey)) {
        $allowedTabs[] = $tab;
    }
}
// Ensure active tab is allowed, otherwise redirect to profile
if (!in_array($activeTab, $allowedTabs)) {
    header('Location: ' . url('settings/index.php?tab=profile'));
    exit;
}

// Helper to get clinic setting
function getClinicSetting($key, $default = '') {
    global $db;
    $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = ?", [$key]);
    return $result ? $result['setting_value'] : $default;
}

// Fetch users for admin panel (only if user has permission)
$users = [];
$staffUsers = [];
$patientUsers = [];
if (in_array('users', $allowedTabs)) {
    $users = $db->fetchAll(
        "SELECT id, username, email, full_name, role, phone, is_admin, is_active, last_login, created_at 
         FROM users ORDER BY role, is_admin DESC, full_name"
    );
    foreach ($users as $user) {
        if ($user['role'] === 'patient') {
            $patientUsers[] = $user;
        } else {
            $staffUsers[] = $user;
        }
    }
}

// Current user data
$currentUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", [Auth::userId()]);

// Clinic settings (only if allowed)
$clinicName = $clinicPhone = $clinicEmail = $clinicAddress = '';
$allowPoints = 1;
$allowReferrals = 1;
$allowSubscription = 1;
if (in_array('clinic', $allowedTabs)) {
    $clinicName = getClinicSetting('clinic_name', 'Dental Clinic');
    $clinicPhone = getClinicSetting('clinic_phone', '(555) 123-4567');
    $clinicEmail = getClinicSetting('clinic_email', 'info@dentalclinic.com');
    $clinicAddress = getClinicSetting('clinic_address', '123 Main St, Anytown, USA');
    $allowPoints = getClinicSetting('allow_points_view', '1');
    $allowReferrals = getClinicSetting('allow_referrals_view', '1');
    $allowSubscription = getClinicSetting('allow_subscription_view', '1');
}

$profileDoctorBookingHours = null;
$profileBookingSlotMinutes = null;
$profileCanEditBookingSlot = false;
if ($isDoctor && dbColumnExists('users', 'booking_hours_json')) {
    $profileDoctorBookingHours = getDoctorBookingHours($db, (int) Auth::userId());
    $profileBookingSlotMinutes = getClinicSlotMinutes($db);
    $profileCanEditBookingSlot = $isAdmin || (function_exists('hasPermission') && hasPermission(Auth::userId(), 'access_settings_clinic'));
}

$settings_ucfirst_label = static function (string $key): string {
    $s = (string) __($key);
    if ($s === '') {
        return $s;
    }
    return (function_exists('mb_substr') && function_exists('mb_strtoupper'))
        ? mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8')) . mb_substr($s, 1, null, 'UTF-8')
        : ucfirst($s);
};

include '../layouts/header.php';
?>

<div class="container-fluid settings-page bills-page">
    <!-- Header -->
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-cog me-2 opacity-90" aria-hidden="true"></i><?php echo __('settings_page'); ?>
                </h2>
                <p class="mb-0 opacity-90">
                    <?php if ($isAdmin): ?>
                        <?php echo __('settings_hero_admin'); ?>
                    <?php elseif ($isDoctor): ?>
                        <?php echo __('settings_hero_doctor'); ?>
                    <?php else: ?>
                        <?php echo __('settings_hero_default'); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <div id="message"></div>

    <div class="settings-route-layout">
        <div class="settings-route-layout__inner">
            <!-- Mobile tabs (no outer “Settings Options” shell — tabs + content use page background) -->
            <div class="settings-route-layout__mobile-tabs-wrap d-md-none">
                <div class="settings-route-layout__mobile-tabs-inner">
                    <ul class="nav nav-tabs settings-nav-tabs border-bottom-0 mb-0" role="tablist">
                        <?php if (in_array('profile', $allowedTabs)): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'profile' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=profile'); ?>">
                                <i class="fas fa-user"></i> <?php echo __('my_profile'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array('users', $allowedTabs)): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'users' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=users'); ?>">
                                <i class="fas fa-users"></i> <?php echo __('user_management'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array('clinic', $allowedTabs)): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'clinic' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=clinic'); ?>">
                                <i class="fas fa-hospital"></i> <?php echo __('clinic_info'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array('access_control', $allowedTabs)): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'access_control' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=access_control'); ?>">
                                <i class="fas fa-lock"></i> <?php echo __('Access Control'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array('points_management', $allowedTabs)): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'points_management' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=points_management'); ?>">
                                <i class="fas fa-coins"></i> <?php echo __('Points Management'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (in_array('subscription_plans', $allowedTabs)): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'subscription_plans' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=subscription_plans'); ?>">
                                <i class="fas fa-crown"></i> <?php echo __('subscription_plans_tab'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <!-- Desktop tabs -->
            <div class="settings-route-layout__desktop-tabs d-none d-md-block">
                <ul class="nav nav-tabs settings-nav-tabs border-bottom-0 mb-4" role="tablist">
                    <?php if (in_array('profile', $allowedTabs)): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'profile' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=profile'); ?>">
                            <i class="fas fa-user"></i> <?php echo __('my_profile'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('users', $allowedTabs)): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'users' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=users'); ?>">
                            <i class="fas fa-users"></i> <?php echo __('user_management'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('clinic', $allowedTabs)): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'clinic' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=clinic'); ?>">
                            <i class="fas fa-hospital"></i> <?php echo __('clinic_info'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('access_control', $allowedTabs)): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'access_control' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=access_control'); ?>">
                            <i class="fas fa-lock"></i> <?php echo __('Access Control'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('points_management', $allowedTabs)): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'points_management' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=points_management'); ?>">
                            <i class="fas fa-coins"></i> <?php echo __('Points Management'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (in_array('subscription_plans', $allowedTabs)): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'subscription_plans' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=subscription_plans'); ?>">
                            <i class="fas fa-crown"></i> <?php echo __('subscription_plans_tab'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <!-- Tab Content -->
            <div class="settings-route-layout__content">
            <?php if ($activeTab == 'profile'): ?>
                <!-- Profile Tab (always allowed) -->
                <div class="card bills-dash-section-card">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-user-edit me-2" aria-hidden="true"></i><?php echo __('my_profile'); ?></h5>
                            </div>
                        </div>
                    </div>
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
                                    <label class="form-label"><?php echo __('username'); ?> *</label>
                                    <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($currentUser['username']); ?>" required autocomplete="username">
                                </div>
                            </div>
                            <?php if ($profileDoctorBookingHours !== null): ?>
                                <?php
                                $wh = $profileDoctorBookingHours;
                                $px = 'wh_';
                                $bookingSlotMinutes = $profileBookingSlotMinutes;
                                $bookingSlotCanEdit = $profileCanEditBookingSlot;
                                include __DIR__ . '/../includes/doctor_booking_hours_form_fragment.php';
                                ?>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-save" aria-hidden="true"></i> <?php echo __('save_changes'); ?></button>
                        </form>
                    </div>
                </div>

                <div class="settings-change-password-desktop-width mt-4">
                    <div class="card bills-dash-section-card">
                        <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                            <div class="bills-arrivals-section-header__inner align-items-center">
                                <div>
                                    <h5 class="card-title mb-0"><i class="fas fa-key me-2" aria-hidden="true"></i><?php echo __('change_password'); ?></h5>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                                <input type="hidden" name="tab" value="password">
                                <input type="hidden" name="settings_action" value="change_password">
                                <div class="settings-change-password-fields">
                                    <div class="row g-3">
                                        <div class="col-12 col-lg-6">
                                            <div class="mb-0 mb-lg-0">
                                                <label class="form-label" for="current_password_field"><?php echo __('current_password'); ?> *</label>
                                                <input type="password" class="form-control" name="current_password" id="current_password_field" required>
                                            </div>
                                        </div>
                                        <div class="col-12 col-lg-6">
                                            <div class="mb-0">
                                                <label class="form-label" for="new_password"><?php echo __('new_password'); ?> *</label>
                                                <input type="password" class="form-control" name="new_password" id="new_password" pattern=".{6,}" title="<?php echo htmlspecialchars(__('settings_min_password_chars')); ?>" required>
                                                <small class="text-muted d-block mt-1"><?php echo __('settings_min_password_chars'); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3 mt-3 settings-change-password-confirm-wrap">
                                        <label class="form-label" for="confirm_password_field"><?php echo __('confirm_password'); ?> *</label>
                                        <input type="password" class="form-control" name="confirm_password" id="confirm_password_field" required>
                                    </div>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="showPasswords">
                                    <label class="form-check-label" for="showPasswords"><?php echo __('show_passwords'); ?></label>
                                </div>
                                <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-sync-alt" aria-hidden="true"></i> <?php echo __('update_password'); ?></button>
                            </form>
                        </div>
                    </div>
                </div>

            <?php elseif ($activeTab == 'users' && in_array('users', $allowedTabs)): ?>
                <!-- User Management Tab -->
                <?php
                $doctorUsers = array_values(array_filter($staffUsers, static function ($u) {
                    return ($u['role'] ?? '') === 'doctor';
                }));
                $assistantUsers = array_values(array_filter($staffUsers, static function ($u) {
                    return ($u['role'] ?? '') === 'assistant';
                }));
                ?>
                <div class="card bills-dash-section-card mb-4">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-user-plus me-2" aria-hidden="true"></i><?php echo __('add_new_user'); ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                            <input type="hidden" name="tab" value="users">
                            <input type="hidden" name="settings_action" value="add_user">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo htmlspecialchars($settings_ucfirst_label('username')); ?> *</label>
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
                                    <label class="form-label"><?php echo htmlspecialchars($settings_ucfirst_label('role')); ?> *</label>
                                    <select class="form-select" name="role" id="settingsNewUserRole" required>
                                        <option value="assistant"><?php echo __('settings_role_assistant'); ?></option>
                                        <option value="patient"><?php echo __('settings_role_patient'); ?></option>
                                        <option value="doctor"><?php echo __('settings_role_doctor'); ?></option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo __('phone'); ?></label>
                                    <input type="text" class="form-control" name="phone">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label"><?php echo __('password'); ?></label>
                                    <input type="text" class="form-control" name="password" autocomplete="new-password">
                                </div>
                                <div class="col-md-12 mb-3" id="settingsGrantAdminRow" style="display:none;">
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="is_admin" id="is_admin" disabled>
                                        <label class="form-check-label" for="is_admin"><i class="fas fa-crown" aria-hidden="true"></i> <?php echo __('settings_grant_admin'); ?></label>
                                        <br><small class="text-muted"><?php echo __('settings_grant_admin_hint'); ?></small>
                                    </div>
                                </div>
                                <?php if (dbColumnExists('users', 'booking_hours_json')): ?>
                                <div class="col-12 mb-3 settings-add-user-doctor-hours" id="settingsAddUserDoctorHours" style="display:none;">
                                    <?php
                                    $wh = defaultBookingCalendarHours();
                                    $px = 'add_wh_';
                                    $bookingSlotMinutes = getClinicSlotMinutes($db);
                                    $bookingSlotCanEdit = $isAdmin || (function_exists('hasPermission') && hasPermission(Auth::userId(), 'access_settings_clinic'));
                                    include __DIR__ . '/../includes/doctor_booking_hours_form_fragment.php';
                                    ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-plus" aria-hidden="true"></i> <?php echo __('add_user'); ?></button>
                        </form>
                    </div>
                </div>

                <div class="card bills-dash-section-card mb-4">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-user-md me-2" aria-hidden="true"></i>Doctor Users</h5>
                            </div>
                            <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                                <table class="table table-hover settings-users-doctor-table">
                                    <thead>
                                        <tr><th><?php echo __('full_name'); ?></th><th><?php echo __('username'); ?></th><th><?php echo __('email'); ?></th><th><?php echo __('settings_table_admin'); ?></th><th><?php echo __('phone'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('last_login'); ?></th><th><?php echo __('actions'); ?></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($doctorUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <?php if ($user['is_admin']): ?>
                                                    <span class="badge settings-user-admin-yes"><?php echo htmlspecialchars(__('yes')); ?></span>
                                                <?php else: ?>
                                                    <span class="badge settings-user-admin-no"><?php echo htmlspecialchars(__('no')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge settings-user-status-active"><?php echo htmlspecialchars(__('settings_status_active')); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars(__('settings_status_inactive')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : htmlspecialchars(__('settings_never')); ?></td>
                                            <td>
                                                <div class="table-card-actions settings-user-row-actions" role="group">
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="settings-user-action-form">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="toggle_user_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-yellow" title="<?php echo htmlspecialchars(__('settings_action_toggle_status')); ?>"><i class="fas fa-power-off" aria-hidden="true"></i></button>
                                                    </form>
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="settings-user-action-form">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="toggle_admin_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="current_admin" value="<?php echo $user['is_admin']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-blue" title="<?php echo htmlspecialchars(__('settings_action_toggle_admin')); ?>"><i class="fas fa-crown" aria-hidden="true"></i></button>
                                                    </form>
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" class="reset-pwd-form settings-user-action-form" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="reset_user_password">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-green" title="<?php echo htmlspecialchars(__('settings_action_reset_password')); ?>"><i class="fas fa-key" aria-hidden="true"></i></button>
                                                    </form>
                                                    <?php if ($user['id'] != Auth::userId()): ?>
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="settings-user-action-form" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>?');">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-red" title="<?php echo htmlspecialchars(__('delete')); ?>"><i class="fas fa-trash" aria-hidden="true"></i></button>
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

                <?php if (!empty($assistantUsers)): ?>
                <div class="card bills-dash-section-card mb-4">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-user-nurse me-2" aria-hidden="true"></i>Assistant Users</h5>
                            </div>
                            <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                                <table class="table table-hover settings-users-assistant-table">
                                    <thead>
                                        <tr><th><?php echo __('full_name'); ?></th><th><?php echo __('username'); ?></th><th><?php echo __('email'); ?></th><th><?php echo __('phone'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('last_login'); ?></th><th><?php echo __('actions'); ?></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assistantUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge settings-user-status-active"><?php echo htmlspecialchars(__('settings_status_active')); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars(__('settings_status_inactive')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : htmlspecialchars(__('settings_never')); ?></td>
                                            <td>
                                                <div class="table-card-actions settings-user-row-actions" role="group">
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="settings-user-action-form">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="toggle_user_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-yellow" title="<?php echo htmlspecialchars(__('settings_action_toggle_status')); ?>"><i class="fas fa-power-off" aria-hidden="true"></i></button>
                                                    </form>
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" class="reset-pwd-form settings-user-action-form" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="reset_user_password">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-green" title="<?php echo htmlspecialchars(__('settings_action_reset_password')); ?>"><i class="fas fa-key" aria-hidden="true"></i></button>
                                                    </form>
                                                    <?php if ($user['id'] != Auth::userId()): ?>
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="settings-user-action-form" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>?');">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-red" title="<?php echo htmlspecialchars(__('delete')); ?>"><i class="fas fa-trash" aria-hidden="true"></i></button>
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
                <?php endif; ?>

                <div class="card bills-dash-section-card">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-user me-2" aria-hidden="true"></i>Patient Users</h5>
                            </div>
                            <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                                <table class="table table-hover settings-users-patient-table">
                                    <thead>
                                        <tr><th><?php echo __('full_name'); ?></th><th><?php echo __('username'); ?></th><th><?php echo __('email'); ?></th><th><?php echo __('phone'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('last_login'); ?></th><th><?php echo __('actions'); ?></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patientUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td>
                                                <?php if ($user['is_active']): ?>
                                                    <span class="badge settings-user-status-active"><?php echo htmlspecialchars(__('settings_status_active')); ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars(__('settings_status_inactive')); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : htmlspecialchars(__('settings_never')); ?></td>
                                            <td>
                                                <div class="table-card-actions settings-user-row-actions" role="group">
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="settings-user-action-form">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="toggle_user_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-yellow" title="<?php echo htmlspecialchars(__('settings_action_toggle_status')); ?>"><i class="fas fa-power-off" aria-hidden="true"></i></button>
                                                    </form>
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" class="reset-pwd-form settings-user-action-form" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="reset_user_password">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-green" title="<?php echo htmlspecialchars(__('settings_action_reset_password')); ?>"><i class="fas fa-key" aria-hidden="true"></i></button>
                                                    </form>
                                                    <?php if ($user['id'] != Auth::userId()): ?>
                                                    <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="settings-user-action-form" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8'); ?>?');">
                                                        <input type="hidden" name="tab" value="users">
                                                        <input type="hidden" name="settings_action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-sm table-action-btn action-red" title="<?php echo htmlspecialchars(__('delete')); ?>"><i class="fas fa-trash" aria-hidden="true"></i></button>
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

            <?php elseif ($activeTab == 'clinic' && in_array('clinic', $allowedTabs)): ?>
                <!-- Clinic Info Tab -->
                <div class="card bills-dash-section-card">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-hospital me-2" aria-hidden="true"></i><?php echo __('clinic_info'); ?></h5>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                            <input type="hidden" name="tab" value="clinic">
                            <input type="hidden" name="settings_action" value="update_clinic">
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('clinic_name'); ?></label>
                                <input type="text" class="form-control" name="clinic_name" value="<?php echo htmlspecialchars($clinicName); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('clinic_phone'); ?></label>
                                <input type="text" class="form-control" name="clinic_phone" value="<?php echo htmlspecialchars($clinicPhone); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('clinic_email'); ?></label>
                                <input type="email" class="form-control" name="clinic_email" value="<?php echo htmlspecialchars($clinicEmail); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold"><?php echo __('clinic_address'); ?></label>
                                <textarea class="form-control" name="clinic_address" rows="3" required><?php echo htmlspecialchars($clinicAddress); ?></textarea>
                            </div>
                            <div class="settings-clinic-permissions border-top pt-3 mt-4">
                                <h6 class="fw-bold mb-3"><?php echo __('settings_permissions_section'); ?></h6>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" name="allow_points" id="allow_points" value="1" <?php echo $allowPoints ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="allow_points"><i class="fas fa-star"></i> <?php echo __('settings_portal_points'); ?></label>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" name="allow_subscription" id="allow_subscription" value="1" <?php echo $allowSubscription ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="allow_subscription"><i class="fas fa-crown"></i> <?php echo __('settings_portal_subscription'); ?></label>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" name="allow_referrals" id="allow_referrals" value="1" <?php echo $allowReferrals ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="allow_referrals"><i class="fas fa-share-alt"></i> <?php echo __('settings_portal_referrals'); ?></label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-save" aria-hidden="true"></i> <?php echo __('save_changes'); ?></button>
                        </form>
                    </div>
                </div>

            <?php elseif ($activeTab == 'points_management' && in_array('points_management', $allowedTabs)): ?>
                <?php
                $earningRules = $db->fetchAll("SELECT * FROM points_earning_rules ORDER BY display_order");
                $rewardsList = $db->fetchAll("SELECT * FROM points_rewards ORDER BY display_order");
                ?>
                <div class="settings-points-management">
                    <div class="card bills-dash-section-card mb-4">
                        <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                            <div class="bills-arrivals-section-header__inner align-items-center">
                                <div>
                                    <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2" aria-hidden="true"></i>How Patients Earn Points</h5>
                                </div>
                                <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                                <input type="hidden" name="tab" value="points_management">
                                <input type="hidden" name="settings_action" value="update_earning_rules">
                                <div class="table-responsive settings-points-table-responsive">
                                    <table class="table table-bordered settings-points-table settings-points-table--earning">
                                        <thead>
                                            <tr><th>Rule Key</th><th>Title</th><th>Description</th><th>Points</th><th>Order</th><th>Active</th><th>Delete</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($earningRules as $rule): ?>
                                            <tr>
                                                <td><input type="hidden" name="icon[<?php echo $rule['id']; ?>]" value="0"><input type="text" name="rule_key[<?php echo $rule['id']; ?>]" value="<?php echo htmlspecialchars($rule['rule_key']); ?>" class="form-control form-control-sm" required></td>
                                                <td><input type="text" name="title[<?php echo $rule['id']; ?>]" value="<?php echo htmlspecialchars($rule['title']); ?>" class="form-control form-control-sm" required></td>
                                                <td><input type="text" name="description[<?php echo $rule['id']; ?>]" value="<?php echo htmlspecialchars($rule['description']); ?>" class="form-control form-control-sm"></td>
                                                <td><input type="number" name="points[<?php echo $rule['id']; ?>]" value="<?php echo $rule['points']; ?>" class="form-control form-control-sm" required></td>
                                                <td><input type="number" name="display_order[<?php echo $rule['id']; ?>]" value="<?php echo $rule['display_order']; ?>" class="form-control form-control-sm"></td>
                                                <td class="text-center"><input type="checkbox" name="is_active[<?php echo $rule['id']; ?>]" value="1" <?php echo $rule['is_active'] ? 'checked' : ''; ?>></td>
                                                <td class="text-center"><input type="checkbox" name="delete_rule[<?php echo $rule['id']; ?>]" value="1"></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <tr class="table-secondary">
                                                <td><input type="hidden" name="new_icon" value="0"><input type="text" name="new_rule_key" class="form-control form-control-sm" placeholder="new_key"></td>
                                                <td><input type="text" name="new_title" class="form-control form-control-sm" placeholder="Title"></td>
                                                <td><input type="text" name="new_description" class="form-control form-control-sm" placeholder="Description"></td>
                                                <td><input type="number" name="new_points" class="form-control form-control-sm" placeholder="Points"></td>
                                                <td><input type="number" name="new_display_order" class="form-control form-control-sm" placeholder="Order"></td>
                                                <td class="text-center"><input type="checkbox" name="new_is_active" value="1" checked></td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="btn btn-green settings-save-btn mt-2"><i class="fas fa-save" aria-hidden="true"></i> Save Earning Rules</button>
                            </form>
                        </div>
                    </div>

                    <div class="card bills-dash-section-card">
                        <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                            <div class="bills-arrivals-section-header__inner align-items-center">
                                <div>
                                    <h5 class="card-title mb-0"><i class="fas fa-gift me-2" aria-hidden="true"></i>Available Rewards</h5>
                                </div>
                                <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                                <input type="hidden" name="tab" value="points_management">
                                <input type="hidden" name="settings_action" value="update_rewards">
                                <div class="table-responsive settings-points-table-responsive">
                                    <table class="table table-bordered settings-points-table settings-points-table--rewards">
                                        <thead>
                                            <tr><th>Name</th><th>Description</th><th>Points</th><th>Order</th><th>Active</th><th>Delete</th></tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rewardsList as $reward): ?>
                                            <tr>
                                                <td><input type="hidden" name="icon[<?php echo $reward['id']; ?>]" value="0"><input type="text" name="name[<?php echo $reward['id']; ?>]" value="<?php echo htmlspecialchars($reward['name']); ?>" class="form-control form-control-sm" required></td>
                                                <td><input type="text" name="description[<?php echo $reward['id']; ?>]" value="<?php echo htmlspecialchars($reward['description']); ?>" class="form-control form-control-sm"></td>
                                                <td><input type="number" name="points_required[<?php echo $reward['id']; ?>]" value="<?php echo $reward['points_required']; ?>" class="form-control form-control-sm" required></td>
                                                <td><input type="number" name="display_order[<?php echo $reward['id']; ?>]" value="<?php echo $reward['display_order']; ?>" class="form-control form-control-sm"></td>
                                                <td class="text-center"><input type="checkbox" name="is_active[<?php echo $reward['id']; ?>]" value="1" <?php echo $reward['is_active'] ? 'checked' : ''; ?>></td>
                                                <td class="text-center"><input type="checkbox" name="delete_reward[<?php echo $reward['id']; ?>]" value="1"></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <tr class="table-secondary">
                                                <td><input type="hidden" name="new_icon" value="0"><input type="text" name="new_name" class="form-control form-control-sm" placeholder="Reward name"></td>
                                                <td><input type="text" name="new_description" class="form-control form-control-sm" placeholder="Description"></td>
                                                <td><input type="number" name="new_points_required" class="form-control form-control-sm" placeholder="Points"></td>
                                                <td><input type="number" name="new_display_order" class="form-control form-control-sm" placeholder="Order"></td>
                                                <td class="text-center"><input type="checkbox" name="new_is_active" value="1" checked></td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="btn btn-green settings-save-btn mt-2"><i class="fas fa-save" aria-hidden="true"></i> Save Rewards</button>
                            </form>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i> The earning guide and rewards will automatically update in the staff and patient portals.
                    </div>
                </div>

            <?php elseif ($activeTab == 'subscription_plans' && in_array('subscription_plans', $allowedTabs)): ?>
                <?php
                $subscriptionPlans = $db->fetchAll('SELECT * FROM subscription_plans ORDER BY display_order, monthly_price');
                ?>
                <div class="settings-subscription-plans">
                    <div class="card bills-dash-section-card settings-sub-plans-intro border-0 mb-4">
                        <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                            <div class="bills-arrivals-section-header__inner align-items-center">
                                <div>
                                    <h5 class="card-title mb-0"><i class="fas fa-crown me-2" aria-hidden="true"></i><?php echo __('settings_sub_plans_title'); ?></h5>
                                    <p class="mb-0 mt-1 small"><?php echo __('settings_sub_plans_subtitle'); ?></p>
                                </div>
                                <div class="flex-shrink-0 d-none d-md-block text-end">
                                    <span class="badge rounded-pill bg-white text-dark border"><?php echo count($subscriptionPlans); ?> plan<?php echo count($subscriptionPlans) === 1 ? '' : 's'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4 settings-sub-plans-grid">
                        <?php foreach ($subscriptionPlans as $plan): ?>
                            <div class="col-12 col-lg-4">
                                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="subscription-plan-form h-100">
                                    <input type="hidden" name="tab" value="subscription_plans">
                                    <input type="hidden" name="settings_action" value="update_plan">
                                    <input type="hidden" name="plan_key" value="<?php echo htmlspecialchars($plan['plan_key']); ?>">
                                    <div class="card bills-dash-section-card subscription-plan-card border-0 h-100">
                                        <div class="subscription-plan-card__head d-flex flex-wrap align-items-center justify-content-between gap-3">
                                            <div class="min-w-0">
                                                <span class="subscription-plan-card__key"><?php echo htmlspecialchars($plan['plan_key']); ?></span>
                                            </div>
                                            <div class="form-check form-switch subscription-plan-card__switch mb-0">
                                                <?php $planSwitchId = 'sub_plan_active_' . (int) ($plan['id'] ?? 0); ?>
                                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="<?php echo htmlspecialchars($planSwitchId); ?>"
                                                    <?php echo $plan['is_active'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="<?php echo htmlspecialchars($planSwitchId); ?>"><?php echo __('settings_sub_plan_active'); ?></label>
                                            </div>
                                        </div>
                                        <div class="card-body pt-0">
                                            <div class="row g-3">
                                                <div class="col-12">
                                                    <label class="form-label small mb-1"><?php echo __('settings_sub_plan_label_name'); ?></label>
                                                    <input type="text" name="plan_name" class="form-control" value="<?php echo htmlspecialchars($plan['plan_name']); ?>" required>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small mb-1"><?php echo __('settings_sub_plan_label_monthly'); ?></label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" step="0.01" name="monthly_price" class="form-control" value="<?php echo htmlspecialchars($plan['monthly_price']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small mb-1"><?php echo __('settings_sub_plan_label_annual'); ?></label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" step="0.01" name="annual_price" class="form-control" value="<?php echo htmlspecialchars($plan['annual_price']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small mb-1"><?php echo __('settings_sub_plan_label_order'); ?></label>
                                                    <input type="number" name="display_order" class="form-control" value="<?php echo (int) $plan['display_order']; ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small mb-1"><?php echo __('settings_sub_plan_label_features'); ?></label>
                                                    <textarea name="features" class="form-control" rows="3"><?php echo htmlspecialchars($plan['features']); ?></textarea>
                                                    <small class="text-muted"><?php echo __('settings_sub_plan_features_hint'); ?></small>
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 pt-3">
                                                <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-save me-1"></i><?php echo __('settings_sub_plan_save'); ?></button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="alert alert-info mt-4 d-flex align-items-start gap-2">
                        <i class="fas fa-info-circle mt-1"></i>
                        <span><?php echo __('settings_sub_plan_note'); ?></span>
                    </div>
                </div>

            <?php elseif ($activeTab == 'access_control' && in_array('access_control', $allowedTabs)): ?>
                <?php
                // Only super admin can access this tab
                if (!$isAdmin) {
                    header('Location: ' . url('settings/index.php?tab=profile'));
                    exit;
                }
                $allUsers = $db->fetchAll("SELECT id, username, full_name, role FROM users WHERE is_admin = 0 AND role != ? ORDER BY role, full_name", ['patient'], "s");
                $selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : ($allUsers[0]['id'] ?? 0);
                $currentPerms = [];
                if ($selectedUserId) {
                    $rows = $db->fetchAll("SELECT permission_key, value FROM user_permissions WHERE user_id = ?", [$selectedUserId], "i");
                    foreach ($rows as $row) {
                        $currentPerms[$row['permission_key']] = $row['value'];
                    }
                }
                $allPermissions = getAllPermissions();
                $permissionGroups = [
                    'Settings' => array_filter(
                        $allPermissions,
                        static fn($label, $key) => str_starts_with((string) $key, 'access_settings_'),
                        ARRAY_FILTER_USE_BOTH
                    ),
                    'Application' => [
                        'manage_billing' => $allPermissions['manage_billing'] ?? 'Create/Edit Invoices, Record Payments',
                    ],
                ];
                ?>
                <div class="card bills-dash-section-card">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-lock me-2" aria-hidden="true"></i>Assign Permissions</h5>
                            </div>
                            <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                            <input type="hidden" name="tab" value="access_control">
                            <input type="hidden" name="settings_action" value="update_permissions">
                            <input type="hidden" name="permission_presence[view_billing]" value="1">
                            <input type="hidden" name="permission_presence[manage_billing]" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label">Select User</label>
                                <select name="user_id" class="form-select" onchange="location.href='?tab=access_control&user_id='+this.value">
                                    <?php foreach ($allUsers as $u): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo $selectedUserId == $u['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['full_name'] . ' (' . $u['role'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <input type="hidden" name="target_user_id" value="<?php echo $selectedUserId; ?>">
                            
                            <?php foreach ($permissionGroups as $groupLabel => $groupPermissions): ?>
                                <?php if (!empty($groupPermissions)): ?>
                                <div class="mb-4">
                                    <h6 class="fw-bold mb-3"><?php echo htmlspecialchars($groupLabel); ?></h6>
                                    <div class="row">
                                        <?php $colIndex = 0; foreach ($groupPermissions as $permKey => $permLabel): ?>
                                            <?php if ($permKey === 'access_settings_permissions') { continue; } ?>
                                            <?php $inputId = 'perm_' . preg_replace('/[^a-z0-9_]+/i', '_', (string) $permKey); ?>
                                            <div class="col-md-6">
                                                <div class="form-check mb-2">
                                                    <input
                                                        type="checkbox"
                                                        name="permissions[<?php echo htmlspecialchars($permKey); ?>]"
                                                        value="1"
                                                        id="<?php echo htmlspecialchars($inputId); ?>"
                                                        <?php echo !empty($currentPerms[$permKey]) ? 'checked' : ''; ?>
                                                    >
                                                    <label for="<?php echo htmlspecialchars($inputId); ?>"><?php echo htmlspecialchars($permLabel); ?></label>
                                                </div>
                                            </div>
                                        <?php $colIndex++; endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <button type="submit" class="btn btn-primary mt-3">Save Permissions</button>
                        </form>
                    </div>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i> Only users who are NOT super admin appear here. Super admins automatically have all permissions.
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Password show/hide toggle -->
<script>
document.getElementById('showPasswords')?.addEventListener('change', function(e) {
    document.querySelectorAll('input[type="password"]').forEach(field => {
        field.type = e.target.checked ? 'text' : 'password';
    });
});
</script>

<!-- AJAX form handler (same as before) -->
<script>
(function() {
    function showMessage(msg, type = 'success') {
        const msgDiv = document.getElementById('message');
        if (msgDiv) {
            msgDiv.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                                    ${msg}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>`;
            msgDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function wireBookingDayClosedToggles(scope) {
        const root = scope || document;
        root.querySelectorAll('.js-wh-day-closed').forEach(function(cb) {
            const row = cb.closest('tr.js-wh-dow-row');
            if (!row) return;
            const fields = row.querySelector('.js-wh-day-fields');
            const off = row.querySelector('.js-wh-day-off');
            function sync() {
                const closed = cb.checked;
                if (fields) fields.style.display = closed ? 'none' : '';
                if (off) off.style.display = closed ? '' : 'none';
            }
            cb.addEventListener('change', sync);
            sync();
        });
    }
    wireBookingDayClosedToggles(document);

    const newUserRole = document.getElementById('settingsNewUserRole');
    const addUserDoctorHours = document.getElementById('settingsAddUserDoctorHours');
    const grantAdminRow = document.getElementById('settingsGrantAdminRow');
    function syncAddUserDoctorRoleUi() {
        if (!newUserRole) return;
        const isDoc = newUserRole.value === 'doctor';
        if (grantAdminRow) {
            grantAdminRow.style.display = isDoc ? '' : 'none';
            grantAdminRow.querySelectorAll('input').forEach(function(el) {
                if (isDoc) {
                    el.removeAttribute('disabled');
                } else {
                    el.setAttribute('disabled', 'disabled');
                    if (el.type === 'checkbox') {
                        el.checked = false;
                    }
                }
            });
        }
        if (addUserDoctorHours) {
            addUserDoctorHours.style.display = isDoc ? '' : 'none';
            addUserDoctorHours.querySelectorAll('input').forEach(function(el) {
                if (isDoc) el.removeAttribute('disabled');
                else el.setAttribute('disabled', 'disabled');
            });
        }
    }
    if (newUserRole) {
        newUserRole.addEventListener('change', syncAddUserDoctorRoleUi);
        syncAddUserDoctorRoleUi();
    }

    document.querySelectorAll('form[data-api]').forEach(form => {
        if (form.classList.contains('reset-pwd-form')) return;
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const apiUrl = this.getAttribute('data-api');
            const formData = new FormData(this);
            try {
                const response = await fetch(apiUrl, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success) {
                    showMessage(result.message || 'Operation successful.', 'success');
                    if (result.reload) setTimeout(() => location.reload(), 1000);
                    if (result.reset_form) this.reset();
                } else {
                    showMessage(result.message || 'An error occurred.', 'danger');
                }
            } catch (error) {
                showMessage('Network error: ' + error.message, 'danger');
            }
        });
    });

    document.querySelectorAll('form.reset-pwd-form').forEach(form => {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const apiUrl = this.action;
            const formData = new FormData(this);
            const userName = this.getAttribute('data-user-name') || 'User';
            try {
                const response = await fetch(apiUrl, { method: 'POST', body: formData });
                const result = await response.json();
                if (result.success && result.new_password) {
                    const modalHtml = `
                        <div class="modal fade" id="passwordResetModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header bg-primary text-white">
                                        <h5 class="modal-title"><i class="fas fa-key"></i> Password Reset for ${escapeHtml(userName)}</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>The password has been reset successfully.</p>
                                        <div class="alert alert-info">
                                            <strong>New password:</strong> <code style="font-size:1.2rem">${escapeHtml(result.new_password)}</code>
                                        </div>
                                        <p class="text-muted small">Please copy this password and share it securely.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-primary" id="copyPasswordBtn">Copy</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    const existingModal = document.getElementById('passwordResetModal');
                    if (existingModal) existingModal.remove();
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    const modalEl = document.getElementById('passwordResetModal');
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                    document.getElementById('copyPasswordBtn')?.addEventListener('click', () => {
                        navigator.clipboard.writeText(result.new_password);
                        const btn = document.getElementById('copyPasswordBtn');
                        btn.textContent = 'Copied!';
                        setTimeout(() => { btn.textContent = 'Copy'; }, 2000);
                    });
                    showMessage(`Password for ${userName} has been reset.`, 'success');
                } else {
                    showMessage(result.message || 'Password reset failed.', 'danger');
                }
            } catch (error) {
                showMessage('Network error: ' + error.message, 'danger');
            }
        });
    });

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
})();
</script>

<?php include '../layouts/footer.php'; ?>
