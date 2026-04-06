<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($username === '' || $password === '') {
    api_error('Username and password are required.', 422);
}

function getClinicPhoneForLogin(): string
{
    $db = Database::getInstance();
    $row = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_phone' LIMIT 1");
    return $row ? (string) ($row['setting_value'] ?? 'the clinic') : 'the clinic';
}

if (!Auth::login($username, $password)) {
    $errorType = Auth::getLastError();
    if ($errorType === 'inactive') {
        $clinicPhone = getClinicPhoneForLogin();
        api_error("Your account is inactive. Please contact the clinic at {$clinicPhone} to reactivate your account.", 403);
    }

    api_error('Invalid username or password!', 401);
}

$redirect = ($_SESSION['role'] ?? '') === 'patient' ? 'patient/index.php' : 'dashboard.php';
api_ok(['redirect' => $redirect], 'Signed in.');

