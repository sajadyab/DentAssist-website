<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirm = (string) ($_POST['password_confirm'] ?? '');
$dateOfBirth = (string) ($_POST['date_of_birth'] ?? '');

$phoneCountry = $_POST['phone_country'] ?? null;
$phoneNumber = $_POST['phone_number'] ?? null;

$referralCode = strtoupper(trim((string) ($_POST['referral_code'] ?? '')));

function normalizeE164Phone(?string $countryCode, ?string $localNumber): array
{
    $code = preg_replace('/[^0-9]/', '', (string) $countryCode);
    $number = preg_replace('/[^0-9]/', '', (string) $localNumber);
    if ($code === '' || $number === '') {
        return ['ok' => false, 'value' => null, 'error' => 'Phone number is required.'];
    }
    $digitRules = [
        '961' => [8],
        '971' => [9],
        '966' => [9],
        '33' => [9],
        '1' => [10],
        '44' => [10],
        '49' => [10, 11],
    ];
    if (isset($digitRules[$code]) && !in_array(strlen($number), $digitRules[$code], true)) {
        $hint = implode(' or ', array_map('strval', $digitRules[$code]));
        return ['ok' => false, 'value' => null, 'error' => "Invalid phone digits for +{$code}. Expected {$hint} digits."];
    }

    return ['ok' => true, 'value' => '+' . $code . $number, 'error' => null];
}

$phoneParsed = normalizeE164Phone(
    is_string($phoneCountry) ? $phoneCountry : null,
    is_string($phoneNumber) ? $phoneNumber : null
);

if ($fullName === '' || $username === '' || $dateOfBirth === '' || $password === '' || $passwordConfirm === '' || $email === '') {
    api_error('Please fill in all required fields.', 422);
}
if (!$phoneParsed['ok']) {
    api_error((string) $phoneParsed['error'], 422);
}
if ($password !== $passwordConfirm) {
    api_error('Passwords do not match.', 422);
}

$db = Database::getInstance();
$conn = $db->getConnection();

if ($db->fetchOne('SELECT id FROM users WHERE username = ?', [$username], 's')) {
    api_error('Username already taken.', 409);
}

$phone = (string) $phoneParsed['value'];
$passwordHash = Auth::hashPassword($password);

// users.email is UNIQUE + NOT NULL; store patient email in patients.email
$usersEmail = $username . '@patients.local';

$referredBy = null;
if ($referralCode !== '') {
    $refRow = $db->fetchOne('SELECT id FROM patients WHERE referral_code = ? LIMIT 1', [$referralCode], 's');
    if (!$refRow) {
        api_error('Referral code not found.', 422);
    }
    $referredBy = (int) $refRow['id'];
}

$conn->begin_transaction();
try {
    $userId = $db->insert(
        "INSERT INTO users (username, email, password_hash, full_name, role, phone, is_active)
         VALUES (?, ?, ?, ?, 'patient', ?, 1)",
        [$username, $usersEmail, $passwordHash, $fullName, $phone],
        'sssss'
    );
    if (!$userId) {
        throw new RuntimeException('Error creating account. Please try again later.');
    }

    $patientId = $db->insert(
        "INSERT INTO patients (
            user_id, full_name, date_of_birth, phone, email,
            referred_by, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)",
        [
            (int) $userId,
            $fullName,
            $dateOfBirth,
            $phone,
            $email,
            $referredBy,
            (int) $userId,
        ],
        'issssii'
    );
    if (!$patientId) {
        throw new RuntimeException('Error creating patient record.');
    }

    if ($referredBy !== null) {
        $db->execute('UPDATE patients SET points = COALESCE(points,0) + 50 WHERE id = ? LIMIT 1', [$referredBy], 'i');
    }

    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }
    api_error($e->getMessage(), 500);
}

if (!Auth::login($username, $password)) {
    api_error('Account created, but auto-login failed. Please sign in.', 500, [
        'redirect' => 'login.php',
    ]);
}

api_ok([
    'redirect' => 'patient/index.php',
], 'Registration successful.');

