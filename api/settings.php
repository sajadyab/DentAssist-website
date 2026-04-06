<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');
api_require_login();

$db = Database::getInstance();
$isAdmin = Auth::isAdmin();
$userId = (int) Auth::userId();

$tab = trim((string) ($_POST['tab'] ?? 'profile'));
if ($tab === '') {
    $tab = 'profile';
}
$allowedTabs = ['profile', 'password', 'users', 'clinic', 'subscription_plans', 'language'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'profile';
}

$redirectToTab = static function (string $t) use ($allowedTabs): string {
    if (!in_array($t, $allowedTabs, true)) {
        $t = 'profile';
    }

    return url('settings/index.php?tab=' . urlencode($t));
};

$respondOkTab = static function (string $message, string $t) use ($redirectToTab): void {
    api_ok(['redirect' => $redirectToTab($t)], $message);
};

$upsertClinicSetting = static function (string $key, string $value) use ($db): bool {
    $existing = $db->fetchOne('SELECT id FROM clinic_settings WHERE setting_key = ?', [$key], 's');
    if ($existing) {
        $res = $db->execute('UPDATE clinic_settings SET setting_value = ? WHERE setting_key = ?', [$value, $key], 'ss');

        return $res !== false;
    }
    $res = $db->execute('INSERT INTO clinic_settings (setting_key, setting_value) VALUES (?, ?)', [$key, $value], 'ss');

    return $res !== false;
};

// Primary dispatch: hidden field always sent with fetch/FormData (submit button name is not).
$action = trim((string) ($_POST['settings_action'] ?? ''));
if ($action === '') {
    if (isset($_POST['update_profile'])) {
        $action = 'update_profile';
    } elseif (isset($_POST['change_password'])) {
        $action = 'change_password';
    } elseif (isset($_POST['change_language'])) {
        $action = 'change_language';
    } elseif (isset($_POST['update_clinic'])) {
        $action = 'update_clinic';
    } elseif (isset($_POST['update_plan'])) {
        $action = 'update_plan';
    } elseif (isset($_POST['add_user'])) {
        $action = 'add_user';
    } elseif (isset($_POST['toggle_user_status'])) {
        $action = 'toggle_user_status';
    } elseif (isset($_POST['toggle_admin_status'])) {
        $action = 'toggle_admin_status';
    } elseif (isset($_POST['delete_user'])) {
        $action = 'delete_user';
    } elseif (isset($_POST['reset_user_password'])) {
        $action = 'reset_user_password';
    }
}

$publicActions = ['update_profile', 'change_password', 'change_language'];
if ($action !== '' && !in_array($action, $publicActions, true) && !$isAdmin) {
    api_error('Forbidden.', 403);
}

if ($action === '') {
    api_error('Invalid action.', 400);
}

switch ($action) {
    case 'update_profile':
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($fullName === '' || $email === '') {
            api_error('Full name and email are required.', 422);
        }

        $existing = $db->fetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $userId], 'si');
        if ($existing) {
            api_error((string) __('email_exists', 'Email already exists.'), 409);
        }

        $db->execute('UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?', [$fullName, $phone, $email, $userId], 'sssi');
        $_SESSION['full_name'] = $fullName;
        $respondOkTab((string) __('profile_updated', 'Profile updated.'), 'profile');
        break;

    case 'change_password':
        $currentPassword = (string) ($_POST['current_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $user = $db->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$userId], 'i');
        if (!$user) {
            api_error('User not found.', 404);
        }

        if (!password_verify($currentPassword, (string) ($user['password_hash'] ?? ''))) {
            api_error((string) __('current_password_incorrect', 'Current password is incorrect.'), 422);
        }
        if (strlen($newPassword) < 6) {
            api_error((string) __('password_too_short', 'Password too short.'), 422);
        }
        if ($newPassword !== $confirmPassword) {
            api_error((string) __('passwords_do_not_match', 'Passwords do not match.'), 422);
        }

        $newHash = Auth::hashPassword($newPassword);
        $db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $userId], 'si');
        $respondOkTab((string) __('password_updated', 'Password updated.'), 'password');
        break;

    case 'change_language':
        $newLang = (string) ($_POST['language'] ?? 'en');
        setLanguage($newLang);
        $respondOkTab((string) __('language_updated', 'Language updated.'), 'language');
        break;

    case 'update_clinic':
        $clinicName = (string) ($_POST['clinic_name'] ?? '');
        $clinicPhone = (string) ($_POST['clinic_phone'] ?? '');
        $clinicEmail = (string) ($_POST['clinic_email'] ?? '');
        $clinicAddress = (string) ($_POST['clinic_address'] ?? '');
        $openingHours = (string) ($_POST['opening_hours'] ?? '');

        $ok =
            $upsertClinicSetting('clinic_name', $clinicName)
            && $upsertClinicSetting('clinic_phone', $clinicPhone)
            && $upsertClinicSetting('clinic_email', $clinicEmail)
            && $upsertClinicSetting('clinic_address', $clinicAddress)
            && $upsertClinicSetting('opening_hours', $openingHours);

        $allowPoints = isset($_POST['allow_points']) ? '1' : '0';
        $allowReferrals = isset($_POST['allow_referrals']) ? '1' : '0';
        $allowSubscription = isset($_POST['allow_subscription']) ? '1' : '0';

        $ok = $ok
            && $upsertClinicSetting('allow_points_view', $allowPoints)
            && $upsertClinicSetting('allow_referrals_view', $allowReferrals)
            && $upsertClinicSetting('allow_subscription_view', $allowSubscription);

        if (!$ok) {
            api_error('Error updating clinic info. Please try again.', 500);
        }

        $respondOkTab((string) __('clinic_info_updated', 'Clinic info updated.'), 'clinic');
        break;

    case 'update_plan':
        $planKey = trim((string) ($_POST['plan_key'] ?? ''));
        if ($planKey === '') {
            api_error('Missing plan key.', 422);
        }

        $planName = trim((string) ($_POST['plan_name'] ?? ''));
        $monthlyPrice = (float) ($_POST['monthly_price'] ?? 0);
        $annualPrice = (float) ($_POST['annual_price'] ?? 0);
        $features = (string) ($_POST['features'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $displayOrder = (int) ($_POST['display_order'] ?? 0);

        $res = $db->execute(
            'UPDATE subscription_plans SET plan_name = ?, monthly_price = ?, annual_price = ?, features = ?, is_active = ?, display_order = ? WHERE plan_key = ?',
            [$planName, $monthlyPrice, $annualPrice, $features, $isActive, $displayOrder, $planKey],
            'sddsiss'
        );
        if ($res === false) {
            api_error('Error updating plan.', 500);
        }
        $respondOkTab('Plan updated successfully.', 'subscription_plans');
        break;

    case 'add_user':
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $isAdminUser = isset($_POST['is_admin']) ? 1 : 0;
        $password = (string) ($_POST['password'] ?? '');
        if ($password === '') {
            $password = (string) generateRandomPassword();
        }

        if ($username === '' || $email === '' || $fullName === '' || $role === '') {
            api_error('Missing required fields.', 422);
        }

        $existing = $db->fetchOne('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email], 'ss');
        if ($existing) {
            api_error((string) __('username_email_exists', 'Username or email already exists.'), 409);
        }

        $passwordHash = Auth::hashPassword($password);
        $db->execute(
            "INSERT INTO users (username, email, password_hash, full_name, role, phone, is_admin, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [$username, $email, $passwordHash, $fullName, $role, $phone, $isAdminUser],
            'ssssssi'
        );

        $respondOkTab((string) __('user_added', 'User added.') . ' - Password: ' . $password, 'users');
        break;

    case 'toggle_user_status':
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $currentStatus = (int) ($_POST['current_status'] ?? 0);
        if ($targetUserId <= 0) {
            api_error('Invalid user.', 422);
        }
        $newStatus = $currentStatus === 1 ? 0 : 1;
        $db->execute('UPDATE users SET is_active = ? WHERE id = ?', [$newStatus, $targetUserId], 'ii');
        $respondOkTab((string) __('user_status_updated', 'User status updated.'), 'users');
        break;

    case 'toggle_admin_status':
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $currentAdmin = (int) ($_POST['current_admin'] ?? 0);
        if ($targetUserId <= 0) {
            api_error('Invalid user.', 422);
        }
        $newAdmin = $currentAdmin === 1 ? 0 : 1;
        if ($targetUserId === $userId && $newAdmin === 0) {
            api_error((string) __('cannot_remove_own_admin', 'Cannot remove your own admin.'), 422);
        }
        $db->execute('UPDATE users SET is_admin = ? WHERE id = ?', [$newAdmin, $targetUserId], 'ii');
        $respondOkTab((string) __('admin_status_updated', 'Admin status updated.'), 'users');
        break;

    case 'delete_user':
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            api_error('Invalid user.', 422);
        }
        if ($targetUserId === $userId) {
            api_error((string) __('cannot_delete_self', 'Cannot delete yourself.'), 422);
        }
        $db->execute('DELETE FROM users WHERE id = ?', [$targetUserId], 'i');
        $respondOkTab((string) __('user_deleted', 'User deleted.'), 'users');
        break;

    case 'reset_user_password':
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            api_error('Invalid user.', 422);
        }
        $newPassword = (string) generateRandomPassword();
        $passwordHash = Auth::hashPassword($newPassword);
        $db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$passwordHash, $targetUserId], 'si');
        $respondOkTab((string) __('password_reset', 'Password reset.') . ' - New Password: ' . $newPassword, 'users');
        break;

    default:
        api_error('Invalid action.', 400);
}
