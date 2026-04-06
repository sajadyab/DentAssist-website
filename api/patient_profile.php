<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

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

    $newUsersEmail = $newUsername . '@patients.local';
    $usernameTaken = $db->fetchOne('SELECT id FROM users WHERE username = ? AND id != ?', [$newUsername, $userId], 'si');
    if ($usernameTaken) {
        api_error('That username is already taken. Please choose another.', 409);
    }
    $emailTaken = $db->fetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$newUsersEmail, $userId], 'si');
    if ($emailTaken) {
        api_error('That username is already taken. Please choose another.', 409);
    }

    try {
        $conn->begin_transaction();

        $result = $db->execute(
            "UPDATE patients SET
                full_name = ?, date_of_birth = ?, gender = ?, phone = ?, email = ?,
                emergency_contact_name = ?, emergency_contact_phone = ?, emergency_contact_relation = ?,
                address = ?, country = ?
             WHERE id = ?",
            [
                $_POST['full_name'] ?? '',
                $_POST['date_of_birth'] ?? null,
                $_POST['gender'] ?? null,
                $phone,
                ($email !== '' ? $email : null),
                $_POST['emergency_contact_name'] ?? null,
                $_POST['emergency_contact_phone'] ?? null,
                $_POST['emergency_contact_relation'] ?? null,
                $_POST['address'] ?? null,
                $_POST['country'] ?? 'LB',
                (int) $patientId,
            ],
            'ssssssssssi'
        );
        if ($result === false) {
            throw new RuntimeException('Patient update failed.');
        }

        $userResult = $db->execute(
            "UPDATE users SET username = ?, email = ?, full_name = ?, phone = ? WHERE id = ? AND role = 'patient'",
            [$newUsername, $newUsersEmail, $_POST['full_name'] ?? '', $phone, $userId],
            'ssssi'
        );
        if ($userResult === false) {
            throw new RuntimeException('User update failed.');
        }

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
    $db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$newHash, $userId], 'si');
    api_ok(['reload' => true], 'Password changed successfully.');
}

api_error('Invalid action.', 400);

