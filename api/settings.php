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
$allowedTabs = ['profile', 'password', 'users', 'clinic', 'points_management', 'subscription_plans', 'language', 'access_control'];
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

$touchUserSync = static function (int $uid): void {
    if ($uid <= 0) {
        return;
    }
    try {
        sync_push_row_now('users', $uid);
    } catch (Throwable $ignored) {
    }
};

$touchSubscriptionPlanSync = static function (int $planId): void {
    if ($planId <= 0) {
        return;
    }
    try {
        sync_push_row_now('subscription_plans', $planId);
    } catch (Throwable $ignored) {
    }
};

$upsertClinicSetting = static function (string $key, string $value) use ($db): ?int {
    $existing = $db->fetchOne('SELECT id FROM clinic_settings WHERE setting_key = ?', [$key], 's');
    if ($existing) {
        $settingId = (int) ($existing['id'] ?? 0);
        if ($settingId <= 0) {
            return null;
        }
        $res = $db->execute(
            "UPDATE clinic_settings
             SET setting_value = ?, sync_status = 'pending'
             WHERE id = ?",
            [$value, $settingId],
            'si'
        );
        if ($res === false) {
            return null;
        }
        sync_push_row_now('clinic_settings', $settingId);

        return $settingId;
    }
    $settingId = (int) $db->insert(
        'INSERT INTO clinic_settings (setting_key, setting_value, sync_status) VALUES (?, ?, ?)',
        [$key, $value, 'pending'],
        'sss'
    );
    if ($settingId <= 0) {
        return null;
    }
    sync_push_row_now('clinic_settings', $settingId);

    return $settingId;
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
$actionPermissions = [
    'add_user' => 'access_settings_users',
    'toggle_user_status' => 'access_settings_users',
    'toggle_admin_status' => 'access_settings_users',
    'delete_user' => 'access_settings_users',
    'reset_user_password' => 'access_settings_users',
    'update_clinic' => 'access_settings_clinic',
    'update_earning_rules' => 'access_settings_points_management',
    'update_rewards' => 'access_settings_points_management',
    'update_plan' => 'access_settings_subscription_plans',
    'update_permissions' => 'access_settings_permissions',
];

if ($action !== '' && !in_array($action, $publicActions, true)) {
    $requiredPermission = $actionPermissions[$action] ?? null;
    if (!$isAdmin) {
        if ($requiredPermission === null || !function_exists('hasPermission') || !hasPermission($userId, $requiredPermission)) {
            api_error('Forbidden.', 403);
        }
    }
}

if ($action === '') {
    api_error('Invalid action.', 400);
}

switch ($action) {
    case 'update_profile':
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $username = trim((string) ($_POST['username'] ?? ''));

        if ($fullName === '' || $email === '' || $username === '') {
            api_error((string) __('settings_profile_required_fields', 'Full name, email, and username are required.'), 422);
        }

        $existing = $db->fetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $userId], 'si');
        if ($existing) {
            api_error((string) __('email_exists', 'Email already exists.'), 409);
        }

        $existingUsername = $db->fetchOne('SELECT id FROM users WHERE username = ? AND id != ?', [$username, $userId], 'si');
        if ($existingUsername) {
            api_error((string) __('username_taken', 'This username is already taken.'), 409);
        }

        $roleRow = $db->fetchOne('SELECT role FROM users WHERE id = ?', [$userId], 'i');
        $myRole = (string) ($roleRow['role'] ?? '');

        $setParts = ['full_name = ?', 'phone = ?', 'email = ?', 'username = ?'];
        $values = [$fullName, $phone, $email, $username];
        $types = 'ssss';

        if ($myRole === 'doctor' && dbColumnExists('users', 'booking_hours_json')) {
            try {
                $hoursJson = buildBookingHoursJsonFromPost($_POST, 'wh_');
                $setParts[] = 'booking_hours_json = ?';
                $values[] = $hoursJson;
                $types .= 's';
            } catch (InvalidArgumentException $e) {
                api_error($e->getMessage(), 422);
            }
        }

        $canEditGlobalSlot = $isAdmin || (function_exists('hasPermission') && hasPermission($userId, 'access_settings_clinic'));
        if ($myRole === 'doctor' && $canEditGlobalSlot && isset($_POST['patient_slot_minutes'])) {
            $slotMin = (int) ($_POST['patient_slot_minutes'] ?? 0);
            if ($slotMin < 10 || $slotMin > 120) {
                api_error((string) __('settings_slot_minutes_invalid', 'Slot length must be between 10 and 120 minutes.'), 422);
            }
            if ($upsertClinicSetting('patient_slot_minutes', (string) $slotMin) === null) {
                api_error('Could not update slot length.', 500);
            }
        }

        if (dbColumnExists('users', 'sync_status')) {
            $setParts[] = "sync_status = 'pending'";
        }
        $values[] = $userId;
        $types .= 'i';
        $db->execute('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?', $values, $types);
        $touchUserSync($userId);
        $_SESSION['full_name'] = $fullName;
        $_SESSION['username'] = $username;
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
        $setParts = ['password_hash = ?'];
        $values = [$newHash];
        $types = 's';
        if (dbColumnExists('users', 'sync_status')) {
            $setParts[] = "sync_status = 'pending'";
        }
        $values[] = $userId;
        $types .= 'i';
        $db->execute('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?', $values, $types);
        $touchUserSync($userId);
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

        $ok =
            $upsertClinicSetting('clinic_name', $clinicName) !== null
            && $upsertClinicSetting('clinic_phone', $clinicPhone) !== null
            && $upsertClinicSetting('clinic_email', $clinicEmail) !== null
            && $upsertClinicSetting('clinic_address', $clinicAddress) !== null;

        if (isset($_POST['patient_slot_minutes'])) {
            $slotMin = (int) $_POST['patient_slot_minutes'];
            if ($slotMin < 10 || $slotMin > 120) {
                api_error((string) __('settings_slot_minutes_invalid', 'Slot length must be between 10 and 120 minutes.'), 422);
            }
            $ok = $ok && $upsertClinicSetting('patient_slot_minutes', (string) $slotMin) !== null;
        }

        $allowPoints = isset($_POST['allow_points']) ? '1' : '0';
        $allowReferrals = isset($_POST['allow_referrals']) ? '1' : '0';
        $allowSubscription = isset($_POST['allow_subscription']) ? '1' : '0';

        $ok = $ok
            && $upsertClinicSetting('allow_points_view', $allowPoints) !== null
            && $upsertClinicSetting('allow_referrals_view', $allowReferrals) !== null
            && $upsertClinicSetting('allow_subscription_view', $allowSubscription) !== null;

        if (!$ok) {
            api_error('Error updating clinic info. Please try again.', 500);
        }

        $respondOkTab((string) __('clinic_info_updated', 'Clinic info updated.'), 'clinic');
        break;
 case 'update_permissions':
    $targetUserId = (int)$_POST['target_user_id'];
    $permissions = $_POST['permissions'] ?? [];
    $permissionPresence = $_POST['permission_presence'] ?? [];
    if ($targetUserId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid user.']);
        break;
    }
    
    // Delete all existing permissions for this user (simple replace)
    $db->query("DELETE FROM user_permissions WHERE user_id = ?", [$targetUserId], "i");
    
    // Insert new ones
    foreach ($permissions as $key => $value) {
        $db->query("INSERT INTO user_permissions (user_id, permission_key, value) VALUES (?, ?, 1)", [$targetUserId, $key], "is");
    }
    if (is_array($permissionPresence) && array_key_exists('view_billing', $permissionPresence) && !isset($permissions['view_billing'])) {
        $db->query("INSERT INTO user_permissions (user_id, permission_key, value) VALUES (?, ?, 0)", [$targetUserId, 'view_billing'], "is");
    }
    if (is_array($permissionPresence) && array_key_exists('manage_billing', $permissionPresence) && !isset($permissions['manage_billing'])) {
        $db->query("INSERT INTO user_permissions (user_id, permission_key, value) VALUES (?, ?, 0)", [$targetUserId, 'manage_billing'], "is");
    }
    
    echo json_encode(['success' => true, 'message' => 'Permissions updated successfully.']);
    break;
    case 'update_earning_rules':
        $ruleKeys = is_array($_POST['rule_key']) ? $_POST['rule_key'] : [];
        foreach ($ruleKeys as $id => $ruleKey) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            if (isset($_POST['delete_rule'][$id]) && $_POST['delete_rule'][$id]) {
                $deleted = (int) $db->execute('DELETE FROM points_earning_rules WHERE id = ?', [$id], 'i');
                if ($deleted > 0 && function_exists('queueCloudDeletion')) {
                    queueCloudDeletion('points_earning_rules', $id, 'local_id');
                    if (function_exists('sync_process_delete_queue_now')) {
                        sync_process_delete_queue_now(1);
                    }
                }
                continue;
            }

            $title = trim((string) ($_POST['title'][$id] ?? ''));
            $description = trim((string) ($_POST['description'][$id] ?? ''));
            $points = (int) ($_POST['points'][$id] ?? 0);
            $icon = trim((string) ($_POST['icon'][$id] ?? ''));
            $displayOrder = (int) ($_POST['display_order'][$id] ?? 0);
            $isActive = isset($_POST['is_active'][$id]) ? 1 : 0;

            $setParts = [
                'rule_key = ?',
                'title = ?',
                'description = ?',
                'points = ?',
                'icon = ?',
                'display_order = ?',
                'is_active = ?',
            ];
            $values = [$ruleKey, $title, $description, $points, $icon, $displayOrder, $isActive];
            $types = 'sssisii';
            if (dbColumnExists('points_earning_rules', 'sync_status')) {
                $setParts[] = "sync_status = 'pending'";
                $values[] = 'pending';
                $types .= 's';
            }
            $values[] = $id;
            $types .= 'i';
            $db->execute('UPDATE points_earning_rules SET ' . implode(', ', $setParts) . ' WHERE id = ?', $values, $types);
            if (function_exists('sync_push_row_now')) {
                try {
                    sync_push_row_now('points_earning_rules', $id);
                } catch (Throwable $ignored) {
                }
            }
        }

        $newRuleKey = trim((string) ($_POST['new_rule_key'] ?? ''));
        $newTitle = trim((string) ($_POST['new_title'] ?? ''));
        if ($newRuleKey !== '' && $newTitle !== '' && isset($_POST['new_points']) && $_POST['new_points'] !== '') {
            $newDescription = trim((string) ($_POST['new_description'] ?? ''));
            $newPoints = (int) ($_POST['new_points'] ?? 0);
            $newIcon = trim((string) ($_POST['new_icon'] ?? ''));
            $newDisplayOrder = (int) ($_POST['new_display_order'] ?? 0);
            $newIsActive = isset($_POST['new_is_active']) ? 1 : 0;

            $columns = ['rule_key', 'title', 'description', 'points', 'icon', 'display_order', 'is_active'];
            $placeholders = array_fill(0, count($columns), '?');
            $values = [$newRuleKey, $newTitle, $newDescription, $newPoints, $newIcon, $newDisplayOrder, $newIsActive];
            $types = 'sssisii';
            if (dbColumnExists('points_earning_rules', 'sync_status')) {
                $columns[] = 'sync_status';
                $placeholders[] = '?';
                $values[] = 'pending';
                $types .= 's';
            }

            $newId = (int) $db->insert(
                'INSERT INTO points_earning_rules (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
                $values,
                $types
            );
            if ($newId > 0 && function_exists('sync_push_row_now')) {
                try {
                    sync_push_row_now('points_earning_rules', $newId);
                } catch (Throwable $ignored) {
                }
            }
        }

        $respondOkTab('Earning rules updated successfully.', 'points_management');
        break;

    case 'update_rewards':
        $rewardNames = is_array($_POST['name']) ? $_POST['name'] : [];
        foreach ($rewardNames as $id => $name) {
            $id = (int) $id;
            if ($id <= 0) {
                continue;
            }
            if (isset($_POST['delete_reward'][$id]) && $_POST['delete_reward'][$id]) {
                $deleted = (int) $db->execute('DELETE FROM points_rewards WHERE id = ?', [$id], 'i');
                if ($deleted > 0 && function_exists('queueCloudDeletion')) {
                    queueCloudDeletion('points_rewards', $id, 'local_id');
                    if (function_exists('sync_process_delete_queue_now')) {
                        sync_process_delete_queue_now(1);
                    }
                }
                continue;
            }

            $description = trim((string) ($_POST['description'][$id] ?? ''));
            $pointsRequired = (int) ($_POST['points_required'][$id] ?? 0);
            $icon = trim((string) ($_POST['icon'][$id] ?? ''));
            $displayOrder = (int) ($_POST['display_order'][$id] ?? 0);
            $isActive = isset($_POST['is_active'][$id]) ? 1 : 0;

            $setParts = [
                'name = ?',
                'description = ?',
                'points_required = ?',
                'icon = ?',
                'display_order = ?',
                'is_active = ?',
            ];
            $values = [$name, $description, $pointsRequired, $icon, $displayOrder, $isActive];
            $types = 'ssisii';
            if (dbColumnExists('points_rewards', 'sync_status')) {
                $setParts[] = "sync_status = 'pending'";
                $values[] = 'pending';
                $types .= 's';
            }
            $values[] = $id;
            $types .= 'i';
            $db->execute('UPDATE points_rewards SET ' . implode(', ', $setParts) . ' WHERE id = ?', $values, $types);
            if (function_exists('sync_push_row_now')) {
                try {
                    sync_push_row_now('points_rewards', $id);
                } catch (Throwable $ignored) {
                }
            }
        }

        $newName = trim((string) ($_POST['new_name'] ?? ''));
        if ($newName !== '' && isset($_POST['new_points_required']) && $_POST['new_points_required'] !== '') {
            $newDescription = trim((string) ($_POST['new_description'] ?? ''));
            $newPointsRequired = (int) ($_POST['new_points_required'] ?? 0);
            $newIcon = trim((string) ($_POST['new_icon'] ?? ''));
            $newDisplayOrder = (int) ($_POST['new_display_order'] ?? 0);
            $newIsActive = isset($_POST['new_is_active']) ? 1 : 0;

            $columns = ['name', 'description', 'points_required', 'icon', 'display_order', 'is_active'];
            $placeholders = array_fill(0, count($columns), '?');
            $values = [$newName, $newDescription, $newPointsRequired, $newIcon, $newDisplayOrder, $newIsActive];
            $types = 'ssisii';
            if (dbColumnExists('points_rewards', 'sync_status')) {
                $columns[] = 'sync_status';
                $placeholders[] = '?';
                $values[] = 'pending';
                $types .= 's';
            }

            $newId = (int) $db->insert(
                'INSERT INTO points_rewards (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
                $values,
                $types
            );
            if ($newId > 0 && function_exists('sync_push_row_now')) {
                try {
                    sync_push_row_now('points_rewards', $newId);
                } catch (Throwable $ignored) {
                }
            }
        }

        $respondOkTab('Rewards updated successfully.', 'points_management');
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
            'UPDATE subscription_plans SET plan_name = ?, monthly_price = ?, annual_price = ?, features = ?, is_active = ?, display_order = ?'
            . (dbColumnExists('subscription_plans', 'sync_status') ? ", sync_status = 'pending'" : '')
            . ' WHERE plan_key = ?',
            [$planName, $monthlyPrice, $annualPrice, $features, $isActive, $displayOrder, $planKey],
            'sddsiss'
        );
        if ($res === false) {
            api_error('Error updating plan.', 500);
        }
        $planRow = $db->fetchOne('SELECT id FROM subscription_plans WHERE plan_key = ? LIMIT 1', [$planKey], 's');
        $touchSubscriptionPlanSync((int) ($planRow['id'] ?? 0));
        $respondOkTab('Plan updated successfully.', 'subscription_plans');
        break;

    case 'add_user':
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $isAdminUser = ($role === 'doctor' && isset($_POST['is_admin'])) ? 1 : 0;
        $password = (string) ($_POST['password'] ?? '');
        if ($password === '') {
            $password = (string) generateRandomPassword();
        }

        if ($username === '' || $email === '' || $fullName === '' || $role === '') {
            api_error('Missing required fields.', 422);
        }

        $doctorHoursJson = null;
        if ($role === 'doctor') {
            if (!dbColumnExists('users', 'booking_hours_json')) {
                api_error('Database is missing users.booking_hours_json. Run the migration SQL, then retry.', 500);
            }
            try {
                $doctorHoursJson = buildBookingHoursJsonFromPost($_POST, 'add_wh_');
            } catch (InvalidArgumentException $e) {
                api_error($e->getMessage(), 422);
            }
        }

        $existing = $db->fetchOne('SELECT id FROM users WHERE username = ? OR email = ?', [$username, $email], 'ss');
        if ($existing) {
            api_error((string) __('username_email_exists', 'Username or email already exists.'), 409);
        }

        $conn = $db->getConnection();
        $conn->begin_transaction();
        $newPatientId = 0;

        try {
            $passwordHash = Auth::hashPassword($password);
            $columns = ['username', 'email', 'password_hash', 'full_name', 'role', 'phone', 'is_admin', 'is_active'];
            $values = [$username, $email, $passwordHash, $fullName, $role, $phone, $isAdminUser, 1];
            $types = 'ssssssii';
            if ($doctorHoursJson !== null && dbColumnExists('users', 'booking_hours_json')) {
                $columns[] = 'booking_hours_json';
                $values[] = $doctorHoursJson;
                $types .= 's';
            }
            if (dbColumnExists('users', 'sync_status')) {
                $columns[] = 'sync_status';
                $values[] = 'pending';
                $types .= 's';
            }
            $newUserId = (int) $db->insert(
                'INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                $values,
                $types
            );
            if ($newUserId <= 0) {
                throw new RuntimeException('Error creating user account.');
            }

            if ($role === 'patient') {
                $patientColumns = ['user_id', 'full_name', 'phone', 'email'];
                $patientValues = [(int) $newUserId, $fullName, $phone, $email];
                $patientTypes = 'isss';

                if (dbColumnExists('patients', 'created_by')) {
                    $patientColumns[] = 'created_by';
                    $patientValues[] = $userId;
                    $patientTypes .= 'i';
                }
                if (dbColumnExists('patients', 'sync_status')) {
                    $patientColumns[] = 'sync_status';
                    $patientValues[] = 'pending';
                    $patientTypes .= 's';
                }

                $newPatientId = (int) $db->insert(
                    'INSERT INTO patients (' . implode(', ', $patientColumns) . ') VALUES (' . implode(', ', array_fill(0, count($patientColumns), '?')) . ')',
                    $patientValues,
                    $patientTypes
                );
                if ($newPatientId <= 0) {
                    throw new RuntimeException('Error creating patient record.');
                }
            }

            $conn->commit();
        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
            }
            api_error($e->getMessage(), 500);
        }

        $canEditGlobalSlot = $isAdmin || (function_exists('hasPermission') && hasPermission($userId, 'access_settings_clinic'));
        if ($role === 'doctor' && $canEditGlobalSlot && isset($_POST['patient_slot_minutes'])) {
            $slotMinAdd = (int) ($_POST['patient_slot_minutes'] ?? 0);
            if ($slotMinAdd >= 10 && $slotMinAdd <= 120) {
                $upsertClinicSetting('patient_slot_minutes', (string) $slotMinAdd);
            }
        }

        $touchUserSync($newUserId);
        if ($newPatientId > 0) {
            sync_push_row_now('patients', $newPatientId);
        }

        $respondOkTab((string) __('user_added', 'User added.') . ' - Password: ' . $password, 'users');
        break;

    case 'toggle_user_status':
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $currentStatus = (int) ($_POST['current_status'] ?? 0);
        if ($targetUserId <= 0) {
            api_error('Invalid user.', 422);
        }
        $newStatus = $currentStatus === 1 ? 0 : 1;
        $setParts = ['is_active = ?'];
        $values = [$newStatus];
        $types = 'i';
        if (dbColumnExists('users', 'sync_status')) {
            $setParts[] = "sync_status = 'pending'";
        }
        $values[] = $targetUserId;
        $types .= 'i';
        $db->execute('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?', $values, $types);
        $touchUserSync($targetUserId);
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
        $setParts = ['is_admin = ?'];
        $values = [$newAdmin];
        $types = 'i';
        if (dbColumnExists('users', 'sync_status')) {
            $setParts[] = "sync_status = 'pending'";
        }
        $values[] = $targetUserId;
        $types .= 'i';
        $db->execute('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?', $values, $types);
        $touchUserSync($targetUserId);
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
        $deleted = (int) $db->execute('DELETE FROM users WHERE id = ?', [$targetUserId], 'i');
        if ($deleted > 0) {
            queueCloudDeletion('users', $targetUserId, 'local_id');
            sync_process_delete_queue_now(1);
        }
        $respondOkTab((string) __('user_deleted', 'User deleted.'), 'users');
        break;
case 'update_earning_rules':
    // Process existing rules
    if (isset($_POST['rule_key']) && is_array($_POST['rule_key'])) {
        foreach ($_POST['rule_key'] as $id => $ruleKey) {
            $id = (int)$id;
            $title = trim($_POST['title'][$id] ?? '');
            $description = trim($_POST['description'][$id] ?? '');
            $points = (int)($_POST['points'][$id] ?? 0);
            $icon = trim($_POST['icon'][$id] ?? 'fa-star');
            $displayOrder = (int)($_POST['display_order'][$id] ?? 0);
            $isActive = isset($_POST['is_active'][$id]) ? 1 : 0;
            $delete = isset($_POST['delete_rule'][$id]) ? 1 : 0;
            
            if ($delete) {
                $db->query("DELETE FROM points_earning_rules WHERE id = ?", [$id], "i");
            } else {
                $db->query(
                    "UPDATE points_earning_rules SET rule_key = ?, title = ?, description = ?, points = ?, icon = ?, display_order = ?, is_active = ? WHERE id = ?",
                    [$ruleKey, $title, $description, $points, $icon, $displayOrder, $isActive, $id],
                    "sssiisii"
                );
            }
        }
    }
    
    // Add new rule
    if (!empty($_POST['new_rule_key'])) {
        $newRuleKey = trim($_POST['new_rule_key']);
        $newTitle = trim($_POST['new_title'] ?? '');
        $newDescription = trim($_POST['new_description'] ?? '');
        $newPoints = (int)($_POST['new_points'] ?? 0);
        $newIcon = trim($_POST['new_icon'] ?? 'fa-star');
        $newDisplayOrder = (int)($_POST['new_display_order'] ?? 0);
        $newIsActive = isset($_POST['new_is_active']) ? 1 : 0;
        
        // Check if rule_key already exists
        $exists = $db->fetchOne("SELECT id FROM points_earning_rules WHERE rule_key = ?", [$newRuleKey], "s");
        if (!$exists) {
            $db->query(
                "INSERT INTO points_earning_rules (rule_key, title, description, points, icon, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$newRuleKey, $newTitle, $newDescription, $newPoints, $newIcon, $newDisplayOrder, $newIsActive],
                "sssiisi"
            );
        }
    }
    
    echo json_encode(['success' => true, 'message' => 'Earning rules updated successfully.']);
    break;

case 'update_rewards':
    // Process existing rewards
    if (isset($_POST['name']) && is_array($_POST['name'])) {
        foreach ($_POST['name'] as $id => $name) {
            $id = (int)$id;
            $description = trim($_POST['description'][$id] ?? '');
            $pointsRequired = (int)($_POST['points_required'][$id] ?? 0);
            $icon = trim($_POST['icon'][$id] ?? 'fa-gift');
            $displayOrder = (int)($_POST['display_order'][$id] ?? 0);
            $isActive = isset($_POST['is_active'][$id]) ? 1 : 0;
            $delete = isset($_POST['delete_reward'][$id]) ? 1 : 0;
            
            if ($delete) {
                $db->query("DELETE FROM points_rewards WHERE id = ?", [$id], "i");
            } else {
                $db->query(
                    "UPDATE points_rewards SET name = ?, description = ?, points_required = ?, icon = ?, display_order = ?, is_active = ? WHERE id = ?",
                    [$name, $description, $pointsRequired, $icon, $displayOrder, $isActive, $id],
                    "ssiisii"
                );
            }
        }
    }
    
    // Add new reward
    if (!empty($_POST['new_name'])) {
        $newName = trim($_POST['new_name']);
        $newDescription = trim($_POST['new_description'] ?? '');
        $newPointsRequired = (int)($_POST['new_points_required'] ?? 0);
        $newIcon = trim($_POST['new_icon'] ?? 'fa-gift');
        $newDisplayOrder = (int)($_POST['new_display_order'] ?? 0);
        $newIsActive = isset($_POST['new_is_active']) ? 1 : 0;
        
        $db->query(
            "INSERT INTO points_rewards (name, description, points_required, icon, display_order, is_active) VALUES (?, ?, ?, ?, ?, ?)",
            [$newName, $newDescription, $newPointsRequired, $newIcon, $newDisplayOrder, $newIsActive],
            "ssiisi"
        );
    }
    
    echo json_encode(['success' => true, 'message' => 'Rewards updated successfully.']);
    break;
case 'reset_user_password':
    $targetUserId = (int) ($_POST['user_id'] ?? 0);
    if ($targetUserId <= 0) {
        api_error('Invalid user.', 422);
    }
    $newPassword = (string) generateRandomPassword();
    $passwordHash = Auth::hashPassword($newPassword);
    $setParts = ['password_hash = ?'];
    $values = [$passwordHash];
    $types = 's';
    if (dbColumnExists('users', 'sync_status')) {
        $setParts[] = "sync_status = 'pending'";
    }
    $values[] = $targetUserId;
    $types .= 'i';
    $db->execute('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?', $values, $types);
    $touchUserSync($targetUserId);
    
    // Instead of redirecting, return JSON with new_password
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully.',
        'new_password' => $newPassword
    ]);
    exit;
    break;

    default:
        api_error('Invalid action.', 400);
}
