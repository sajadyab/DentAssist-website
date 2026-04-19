<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');
api_require_login();

$db = Database::getInstance();
$userId = (int) Auth::userId();
$user = $db->fetchOne('SELECT * FROM users WHERE id = ?', [$userId], 'i');
if (!$user) {
    api_error('User not found.', 404);
}

if (isset($_POST['update_profile'])) {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));

    if ($fullName === '' || $email === '') {
        api_error('Full name and email are required.', 422);
    }

    $setParts = ['full_name = ?', 'email = ?', 'phone = ?'];
    $values = [$fullName, $email, $phone];
    $types = 'sss';
    if (dbColumnExists('users', 'sync_status')) {
        $setParts[] = "sync_status = 'pending'";
    }
    $values[] = $userId;
    $types .= 'i';
    $db->execute(
        'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?',
        $values,
        $types
    );
    sync_push_row_now('users', (int) $userId);
    $_SESSION['full_name'] = $fullName;
    api_ok(['reload' => true], 'Profile updated.');
}

if (isset($_POST['change_password'])) {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($current === '' || $new === '' || $confirm === '') {
        api_error('All password fields are required.', 422);
    }
    if (!password_verify($current, (string) ($user['password_hash'] ?? ''))) {
        api_error('Current password is incorrect.', 422);
    }
    if ($new !== $confirm) {
        api_error('New passwords do not match.', 422);
    }
    if (strlen($new) < 6) {
        api_error('Password must be at least 6 characters.', 422);
    }

    $newHash = Auth::hashPassword($new);
    $setParts = ['password_hash = ?'];
    $values = [$newHash];
    $types = 's';
    if (dbColumnExists('users', 'sync_status')) {
        $setParts[] = "sync_status = 'pending'";
    }
    $values[] = $userId;
    $types .= 'i';
    $db->execute('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?', $values, $types);
    sync_push_row_now('users', (int) $userId);
    api_ok(['reload' => true], 'Password changed successfully.');
}

api_error('Invalid action.', 400);
