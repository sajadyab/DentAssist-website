<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../includes/patient_cloud_repository.php';

api_require_method('POST');
api_require_login();

if (($_SESSION['role'] ?? '') !== 'patient') {
    api_error('Forbidden.', 403);
}

$db = Database::getInstance();
$userId = (int) Auth::userId();
$patientId = getPatientIdFromUserId($userId);
if (!$patientId) {
    api_error('Patient record not found. Please contact support.', 404);
}

if (isset($_POST['save_profile'])) {
    $patient = $db->fetchOne('SELECT * FROM patients WHERE id = ?', [(int) $patientId], 'i');
    if (!$patient) {
        api_error('Patient record not found. Please contact support.', 404);
    }

    $conn = $db->getConnection();
    $newUsername = trim((string) ($_POST['username'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($newUsername === '') {
        api_error('Username is required.', 422);
    }
    if ($phone === '' || $email === '') {
        api_error('Phone and email are required.', 422);
    }

    $newUsersEmail = $email; // Use the real email provided by the user
    $localUser = $db->fetchOne('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId], 'i');
    if (!$localUser) {
        api_error('User account not found.', 404);
    }
    $usernameTaken = $db->fetchOne('SELECT id FROM users WHERE username = ? AND id != ?', [$newUsername, $userId], 'si');
    if ($usernameTaken) {
        api_error('That username is already taken. Please choose another.', 409);
    }
    $emailTaken = $db->fetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$newUsersEmail, $userId], 'si');
    if ($emailTaken) {
        api_error('That email is already taken. Please choose another.', 409);
    }

    try {
        $genderInput = trim((string) ($_POST['gender'] ?? ''));
        $validGenders = ['male', 'female', 'other'];
        $gender = in_array($genderInput, $validGenders, true) ? $genderInput : null;

        $cloudPatientPayload = [
            'full_name' => $_POST['full_name'] ?? '',
            'date_of_birth' => $_POST['date_of_birth'] ?? null,
            'phone' => $phone,
            'email' => ($email !== '' ? $email : null),
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
            'emergency_contact_relation' => $_POST['emergency_contact_relation'] ?? null,
            'address' => $_POST['address'] ?? null,
            'address_line1' => $_POST['address'] ?? null,
            'country' => $_POST['country'] ?? 'LB',
        ];
        if ($gender !== null) {
            $cloudPatientPayload['gender'] = $gender;
        }
        patient_portal_cloud_upsert_by_local_id_first('patients', (int) $patientId, $cloudPatientPayload, []);

        $cloudUserPayload = [
            'username' => $newUsername,
            'email' => $newUsersEmail,
            'full_name' => $_POST['full_name'] ?? '',
            'phone' => $phone,
            'role' => 'patient',
            'password_hash' => (string) ($localUser['password_hash'] ?? ''),
            'is_active' => (int) ($localUser['is_active'] ?? 1),
            'is_admin' => (int) ($localUser['is_admin'] ?? 0),
        ];
        patient_portal_cloud_upsert_by_local_id_first('users', (int) $userId, $cloudUserPayload, [
            'email' => $newUsersEmail,
            'username' => $newUsername,
        ]);

        $conn->begin_transaction();

        $setParts = [
            'full_name = ?',
            'date_of_birth = ?',
        ];
        $values = [
            $_POST['full_name'] ?? '',
            $_POST['date_of_birth'] ?? null,
        ];
        $types = 'ss';
        if ($gender !== null) {
            $setParts[] = 'gender = ?';
            $values[] = $gender;
            $types .= 's';
        }
        $setParts = array_merge($setParts, [
            'phone = ?',
            'email = ?',
            'emergency_contact_name = ?',
            'emergency_contact_phone = ?',
            'emergency_contact_relation = ?',
        ]);
        $values = array_merge($values, [
            $phone,
            ($email !== '' ? $email : null),
            $_POST['emergency_contact_name'] ?? null,
            $_POST['emergency_contact_phone'] ?? null,
            $_POST['emergency_contact_relation'] ?? null,
        ]);
        $types .= 'sssss';

        if (dbColumnExists('patients', 'address')) {
            $setParts[] = 'address = ?';
            $values[] = $_POST['address'] ?? null;
            $types .= 's';
        } elseif (dbColumnExists('patients', 'address_line1')) {
            $setParts[] = 'address_line1 = ?';
            $values[] = $_POST['address'] ?? null;
            $types .= 's';
        }

        if (dbColumnExists('patients', 'country')) {
            $setParts[] = 'country = ?';
            $values[] = $_POST['country'] ?? 'LB';
            $types .= 's';
        }

        $setParts[] = "sync_status = 'pending'";
        $values[] = (int) $patientId;
        $types .= 'i';

        $sql = 'UPDATE patients SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $result = $db->execute($sql, $values, $types);
        if ($result === false) {
            throw new RuntimeException('Patient update failed.');
        }
        // Note: Cloud already updated via patient_portal_cloud_upsert_by_local_id_first above

        $setParts = [
            'username = ?',
            'email = ?',
            'full_name = ?',
            'phone = ?',
        ];
        $userValues = [$newUsername, $newUsersEmail, $_POST['full_name'] ?? '', $phone];
        $userTypes = 'ssss';
        if (dbColumnExists('users', 'sync_status')) {
            $setParts[] = "sync_status = 'synced'"; // Already synced via cloud upsert
        }
        $userValues[] = $userId;
        $userTypes .= 'i';

        $userResult = $db->execute(
            "UPDATE users SET " . implode(', ', $setParts) . " WHERE id = ? AND role = 'patient'",
            $userValues,
            $userTypes
        );
        if ($userResult === false) {
            throw new RuntimeException('User update failed.');
        }
        // Note: Cloud already updated via patient_portal_cloud_upsert_by_local_id_first above

        $conn->commit();

        logAction('UPDATE', 'patients', (int) $patientId, $patient, $_POST);
        $_SESSION['full_name'] = (string) ($_POST['full_name'] ?? '');
        $_SESSION['username'] = $newUsername;

        api_ok(['reload' => true], 'Profile updated successfully.');
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
        api_error('Error updating profile: ' . $e->getMessage(), 500);
    }
}

if (isset($_POST['change_password'])) {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        api_error('Fill in current password, new password, and confirmation to change your password.', 422);
    }

    $pwdRow = $db->fetchOne('SELECT password_hash FROM users WHERE id = ?', [$userId], 'i');
    $storedHash = (string) ($pwdRow['password_hash'] ?? '');
    if ($storedHash === '' || !password_verify($currentPassword, $storedHash)) {
        api_error((string) __('current_password_incorrect', 'Current password incorrect.'), 422);
    }
    if (strlen($newPassword) < 6) {
        api_error((string) __('password_too_short', 'Password too short.'), 422);
    }
    if ($newPassword !== $confirmPassword) {
        api_error((string) __('passwords_do_not_match', 'Passwords do not match.'), 422);
    }

    $newHash = Auth::hashPassword($newPassword);
    $localUser = $db->fetchOne('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId], 'i');
    if (!$localUser) {
        api_error('User account not found.', 404);
    }
    patient_portal_cloud_upsert_by_local_id_first('users', (int) $userId, [
        'username' => (string) ($localUser['username'] ?? ''),
        'email' => (string) ($localUser['email'] ?? ''),
        'full_name' => (string) ($localUser['full_name'] ?? ''),
        'phone' => (string) ($localUser['phone'] ?? ''),
        'role' => (string) ($localUser['role'] ?? 'patient'),
        'is_active' => (int) ($localUser['is_active'] ?? 1),
        'is_admin' => (int) ($localUser['is_admin'] ?? 0),
        'password_hash' => $newHash,
    ], [
        'email' => (string) ($localUser['email'] ?? ''),
        'username' => (string) ($localUser['username'] ?? ''),
    ]);

    $setParts = ['password_hash = ?'];
    $values = [$newHash];
    $types = 's';
    if (dbColumnExists('users', 'sync_status')) {
        $setParts[] = "sync_status = 'synced'"; // Already synced via cloud upsert
    }
    $values[] = $userId;
    $types .= 'i';
    $db->execute('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?', $values, $types);
    // Note: Cloud already updated via patient_portal_cloud_upsert_by_local_id_first above
    api_ok(['reload' => true], 'Password changed successfully.');
}

api_error('Invalid action.', 400);
