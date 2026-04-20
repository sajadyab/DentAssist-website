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

if ($activeTab === 'password') {
    header('Location: ' . url('settings/index.php?tab=profile'));
    exit;
}

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

<div class="container-fluid settings-page bills-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-md-8">
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
            <div class="col-12 col-md-4 mt-3 mt-md-0 text-md-end">
                <span class="badge bg-light text-dark p-2">
                    <i class="fas fa-calendar-alt me-1"></i> <?php echo date('F j, Y'); ?>
                </span>
            </div>
        </div>
    </div>

    <div id="message"></div>

    <div class="settings-route-layout">
        <div class="settings-route-layout__inner">
            <div class="settings-route-layout__mobile-head d-md-none">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-folder-open me-2 opacity-90" aria-hidden="true"></i><?php echo __('settings_tabs_card'); ?></h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="settings-route-layout__mobile-tabs-wrap d-md-none">
                <div class="card-body pt-3 pb-2">
                    <ul class="nav nav-tabs settings-nav-tabs border-bottom-0 mb-0" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'profile' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=profile'); ?>"
                               role="tab">
                                <i class="fas fa-user" aria-hidden="true"></i> <?php echo __('my_profile'); ?>
                            </a>
                        </li>
                        <?php if ($isAdmin): ?>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'users' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=users'); ?>"
                               role="tab">
                                <i class="fas fa-users" aria-hidden="true"></i> <?php echo __('user_management'); ?>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'clinic' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=clinic'); ?>"
                               role="tab">
                                <i class="fas fa-hospital" aria-hidden="true"></i> <?php echo __('clinic_info'); ?>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?php echo $activeTab == 'subscription_plans' ? 'active' : ''; ?>"
                               href="<?php echo url('settings/index.php?tab=subscription_plans'); ?>"
                               role="tab">
                                <i class="fas fa-crown" aria-hidden="true"></i> <?php echo __('subscription_plans_tab'); ?>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="settings-route-layout__desktop-tabs d-none d-md-block">
                <ul class="nav nav-tabs settings-nav-tabs border-bottom-0 mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'profile' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=profile'); ?>"
                           role="tab">
                            <i class="fas fa-user" aria-hidden="true"></i> <?php echo __('my_profile'); ?>
                        </a>
                    </li>
                    <?php if ($isAdmin): ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'users' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=users'); ?>"
                           role="tab">
                            <i class="fas fa-users" aria-hidden="true"></i> <?php echo __('user_management'); ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'clinic' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=clinic'); ?>"
                           role="tab">
                            <i class="fas fa-hospital" aria-hidden="true"></i> <?php echo __('clinic_info'); ?>
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $activeTab == 'subscription_plans' ? 'active' : ''; ?>"
                           href="<?php echo url('settings/index.php?tab=subscription_plans'); ?>"
                           role="tab">
                            <i class="fas fa-crown" aria-hidden="true"></i> <?php echo __('subscription_plans_tab'); ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="settings-route-layout__content">
    <?php if ($activeTab == 'profile'): ?>
        <div class="card bills-dash-section-card">
            <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                <div class="bills-arrivals-section-header__inner align-items-center">
                    <div>
                        <h5 class="card-title mb-0"><i class="fas fa-user-edit me-2" aria-hidden="true"></i><?php echo __('my_profile'); ?></h5>
                    </div>
                    <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
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
                        <div class="col-12 mb-3">
                            <div class="row g-2 g-md-3 settings-profile-identity">
                                <div class="col-12 col-md-6">
                                    <div class="settings-profile-identity__tile">
                                        <span class="settings-profile-identity__accent" aria-hidden="true"></span>
                                        <span class="settings-profile-identity__glyph" aria-hidden="true"><i class="fas fa-user-tag"></i></span>
                                        <div class="settings-profile-identity__body">
                                            <span class="settings-profile-identity__label"><?php echo __('role'); ?></span>
                                            <span class="settings-profile-identity__value"><?php echo htmlspecialchars(ucfirst((string) $currentUser['role'])); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="settings-profile-identity__tile settings-profile-identity__tile--admin-<?php echo !empty($currentUser['is_admin']) ? 'yes' : 'no'; ?>">
                                        <span class="settings-profile-identity__accent" aria-hidden="true"></span>
                                        <span class="settings-profile-identity__glyph" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
                                        <div class="settings-profile-identity__body">
                                            <span class="settings-profile-identity__label"><?php echo __('admin_privileges'); ?></span>
                                            <span class="settings-profile-identity__value">
                                                <?php if (!empty($currentUser['is_admin'])): ?>
                                                    <?php echo htmlspecialchars(__('settings_yes')); ?>
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars(__('no')); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-save" aria-hidden="true"></i> <?php echo __('save_changes'); ?></button>
                </form>
            </div>
        </div>

        <div class="card bills-dash-section-card mt-4">
            <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                <div class="bills-arrivals-section-header__inner align-items-center">
                    <div>
                        <h5 class="card-title mb-0"><i class="fas fa-key me-2" aria-hidden="true"></i><?php echo __('change_password'); ?></h5>
                    </div>
                    <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                </div>
            </div>
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
                        <input type="password" class="form-control" name="new_password" id="new_password" pattern=".{6,}" title="<?php echo htmlspecialchars(__('settings_min_password_chars')); ?>" required>
                        <small class="text-muted"><?php echo __('settings_min_password_chars'); ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('confirm_password'); ?> *</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="showPasswords">
                        <label class="form-check-label" for="showPasswords"><?php echo __('show_passwords'); ?></label>
                    </div>
                    <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-sync-alt" aria-hidden="true"></i> <?php echo __('update_password'); ?></button>
                </form>
            </div>
        </div>

    <?php elseif ($activeTab == 'users' && $isAdmin): ?>
        <!-- Add User Form -->
        <div class="card bills-dash-section-card mb-4">
            <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                <div class="bills-arrivals-section-header__inner align-items-center">
                    <div>
                        <h5 class="card-title mb-0"><i class="fas fa-user-plus me-2" aria-hidden="true"></i><?php echo __('add_new_user'); ?></h5>
                    </div>
                    <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                </div>
            </div>
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
                                <option value="doctor"><?php echo __('settings_role_doctor'); ?></option>
                                <option value="assistant"><?php echo __('settings_role_assistant'); ?></option>
                                <option value="patient"><?php echo __('settings_role_patient'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo __('phone'); ?></label>
                            <input type="text" class="form-control" name="phone">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?php echo __('password'); ?></label>
                            <input type="text" class="form-control" name="password" placeholder="<?php echo htmlspecialchars(__('settings_password_placeholder_hint')); ?>">
                            <small class="text-muted"><?php echo __('settings_password_autogen_hint'); ?></small>
                        </div>
                        <div class="col-md-12 mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="is_admin" id="is_admin">
                                <label class="form-check-label" for="is_admin"><i class="fas fa-crown" aria-hidden="true"></i> <?php echo __('settings_grant_admin'); ?></label>
                                <br><small class="text-muted"><?php echo __('settings_grant_admin_hint'); ?></small>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-plus" aria-hidden="true"></i> <?php echo __('add_user'); ?></button>
                </form>
            </div>
        </div>

        <!-- Users List -->
        <div class="card bills-dash-section-card">
            <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                <div class="bills-arrivals-section-header__inner align-items-center">
                    <div>
                        <h5 class="card-title mb-0"><i class="fas fa-users me-2" aria-hidden="true"></i><?php echo __('system_users'); ?></h5>
                    </div>
                    <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th><?php echo __('settings_table_id'); ?></th><th><?php echo __('username'); ?></th><th><?php echo __('full_name'); ?></th><th><?php echo __('email'); ?></th><th><?php echo __('role'); ?></th><th><?php echo __('settings_table_admin'); ?></th><th><?php echo __('phone'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('last_login'); ?></th><th><?php echo __('actions'); ?></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="badge bg-<?php echo $user['role'] == 'doctor' ? 'danger' : ($user['role'] == 'assistant' ? 'warning' : 'info'); ?>"><?php echo ucfirst($user['role']); ?></span></td>
                                <td><?php echo $user['is_admin'] ? '<span class="badge bg-primary"><i class="fas fa-crown"></i> ' . htmlspecialchars(__('admin')) . '</span>' : '<span class="badge bg-secondary">' . htmlspecialchars(__('no')) . '</span>'; ?></td>
                                <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                <td><?php echo $user['is_active'] ? '<span class="badge bg-success">' . htmlspecialchars(__('settings_status_active')) . '</span>' : '<span class="badge bg-secondary">' . htmlspecialchars(__('settings_status_inactive')) . '</span>'; ?></td>
                                <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : htmlspecialchars(__('settings_never')); ?></td>
                                <td>
                                    <div class="table-card-actions settings-user-row-actions" role="group" aria-label="<?php echo htmlspecialchars(__('actions')); ?>">
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="settings-user-action-form">
                                            <input type="hidden" name="tab" value="users">
                                            <input type="hidden" name="settings_action" value="toggle_user_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $user['is_active']; ?>">
                                            <button type="submit" class="btn btn-sm table-action-btn action-yellow" title="<?php echo htmlspecialchars(__('settings_action_toggle_status')); ?>"><i class="fas fa-power-off" aria-hidden="true"></i></button>
                                        </form>
                                        <?php if ($user['role'] !== 'patient'): ?>
                                        <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message" class="settings-user-action-form">
                                            <input type="hidden" name="tab" value="users">
                                            <input type="hidden" name="settings_action" value="toggle_admin_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="current_admin" value="<?php echo $user['is_admin']; ?>">
                                            <button type="submit" class="btn btn-sm table-action-btn action-blue" title="<?php echo htmlspecialchars(__('settings_action_toggle_admin')); ?>"><i class="fas fa-crown" aria-hidden="true"></i></button>
                                        </form>
                                        <?php endif; ?>
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

    <?php elseif ($activeTab == 'clinic' && $isAdmin): ?>
        <div class="card bills-dash-section-card">
            <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                <div class="bills-arrivals-section-header__inner align-items-center">
                    <div>
                        <h5 class="card-title mb-0"><i class="fas fa-hospital me-2" aria-hidden="true"></i><?php echo __('clinic_info'); ?></h5>
                    </div>
                    <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                </div>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo url('api/settings.php'); ?>" data-api="<?php echo url('api/settings.php'); ?>" data-message-target="#message">
                    <input type="hidden" name="tab" value="clinic">
                    <input type="hidden" name="settings_action" value="update_clinic">
                    <div class="mb-3">
                        <label class="form-label fw-bold settings-clinic-label"><?php echo __('clinic_name'); ?></label>
                        <input type="text" class="form-control" name="clinic_name" value="<?php echo htmlspecialchars($clinicName); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold settings-clinic-label"><?php echo __('clinic_phone'); ?></label>
                        <input type="text" class="form-control" name="clinic_phone" value="<?php echo htmlspecialchars($clinicPhone); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold settings-clinic-label"><?php echo __('clinic_email'); ?></label>
                        <input type="email" class="form-control" name="clinic_email" value="<?php echo htmlspecialchars($clinicEmail); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold settings-clinic-label"><?php echo __('clinic_address'); ?></label>
                        <textarea class="form-control" name="clinic_address" rows="3" required><?php echo htmlspecialchars($clinicAddress); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold settings-clinic-label"><?php echo __('working_hours'); ?></label>
                        <textarea class="form-control" name="opening_hours" rows="4" required><?php echo htmlspecialchars($openingHours); ?></textarea>
                        <small class="text-muted"><?php echo __('settings_clinic_hours_hint'); ?></small>
                    </div>
                    <div class="settings-clinic-permissions border-top pt-3 mt-4">
                        <h6 class="fw-bold mb-3"><?php echo __('settings_permissions_section'); ?></h6>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="allow_points" id="allow_points" value="1" <?php echo $allowPoints ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_points"><i class="fas fa-star" aria-hidden="true"></i> <?php echo __('settings_portal_points'); ?></label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="allow_subscription" id="allow_subscription" value="1" <?php echo $allowSubscription ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_subscription"><i class="fas fa-crown" aria-hidden="true"></i> <?php echo __('settings_portal_subscription'); ?></label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="allow_referrals" id="allow_referrals" value="1" <?php echo $allowReferrals ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="allow_referrals"><i class="fas fa-share-alt" aria-hidden="true"></i> <?php echo __('settings_portal_referrals'); ?></label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-save" aria-hidden="true"></i> <?php echo __('save_changes'); ?></button>
                </form>
            </div>
        </div>

    <?php elseif ($activeTab == 'subscription_plans' && $isAdmin): ?>
        <?php
        $subscriptionPlans = $db->fetchAll('SELECT * FROM subscription_plans ORDER BY display_order, monthly_price');
        ?>
        <div class="settings-subscription-plans">
            <div class="card bills-dash-section-card settings-sub-plans-intro border-0 mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-crown me-2" aria-hidden="true"></i><?php echo __('settings_sub_plans_title'); ?></h5>
                            <p class="mb-0 mt-1 small settings-sub-plans-intro__muted"><?php echo __('settings_sub_plans_subtitle'); ?></p>
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
                                            <label class="form-label small subscription-plan-field-label mb-1"><?php echo __('settings_sub_plan_label_name'); ?></label>
                                            <input type="text" name="plan_name" class="form-control" value="<?php echo htmlspecialchars($plan['plan_name']); ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small subscription-plan-field-label mb-1"><?php echo __('settings_sub_plan_label_monthly'); ?></label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="monthly_price" class="form-control" value="<?php echo htmlspecialchars($plan['monthly_price']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small subscription-plan-field-label mb-1"><?php echo __('settings_sub_plan_label_annual'); ?></label>
                                            <div class="input-group">
                                                <span class="input-group-text">$</span>
                                                <input type="number" step="0.01" name="annual_price" class="form-control" value="<?php echo htmlspecialchars($plan['annual_price']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small subscription-plan-field-label mb-1"><?php echo __('settings_sub_plan_label_order'); ?></label>
                                            <input type="number" name="display_order" class="form-control" value="<?php echo (int) $plan['display_order']; ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small subscription-plan-field-label mb-1"><?php echo __('settings_sub_plan_label_features'); ?></label>
                                            <textarea name="features" class="form-control" rows="3"><?php echo htmlspecialchars($plan['features']); ?></textarea>
                                            <small class="text-muted"><?php echo __('settings_sub_plan_features_hint'); ?></small>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-end gap-2 mt-4 pt-3 subscription-plan-card__actions">
                                        <button type="submit" class="btn btn-green settings-save-btn"><i class="fas fa-save me-1" aria-hidden="true"></i><?php echo __('settings_sub_plan_save'); ?></button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="alert alert-info mt-4 settings-sub-plans-footnote d-flex align-items-start gap-2">
                <i class="fas fa-info-circle mt-1 flex-shrink-0" aria-hidden="true"></i>
                <span><?php echo __('settings_sub_plan_note'); ?></span>
            </div>
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