<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/auth.php';
require_once $basePath . '/includes/functions.php';

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$fullName = trim((string) ($input['full_name'] ?? ''));
$username = trim((string) ($input['username'] ?? ''));
$email = trim((string) ($input['email'] ?? ''));
$password = (string) ($input['password'] ?? '');
$passwordConfirm = (string) ($input['password_confirm'] ?? '');
$dateOfBirth = (string) ($input['date_of_birth'] ?? '');
$phone = trim((string) ($input['phone'] ?? ''));
$referralCode = strtoupper(trim((string) ($input['referral_code'] ?? '')));

// Validation
if ($fullName === '' || $username === '' || $email === '' || $password === '' || $dateOfBirth === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

if ($password !== $passwordConfirm) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
    exit;
}

if (strlen($password) < 6) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if username exists
if ($db->fetchOne('SELECT id FROM users WHERE username = ?', [$username], 's')) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Username already taken.']);
    exit;
}

// Check if email exists
if ($db->fetchOne('SELECT id FROM users WHERE email = ?', [$email], 's')) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Email already registered.']);
    exit;
}

// Handle referral code
$referredBy = null;
if ($referralCode !== '') {
    $refRow = $db->fetchOne('SELECT id FROM patients WHERE referral_code = ? LIMIT 1', [$referralCode], 's');
    if ($refRow) {
        $referredBy = (int) $refRow['id'];
    }
}

$passwordHash = Auth::hashPassword($password);

// Begin transaction
$conn->begin_transaction();
try {
    // Insert user
    $userColumns = ['username', 'email', 'password_hash', 'full_name', 'role', 'phone', 'is_active'];
    $userValues = [$username, $email, $passwordHash, $fullName, 'patient', $phone, 1];
    $userTypes = 'ssssssi';
    
    if (dbColumnExists('users', 'sync_status')) {
        $userColumns[] = 'sync_status';
        $userValues[] = 'pending';
        $userTypes .= 's';
    }
    
    $userId = $db->insert(
        'INSERT INTO users (' . implode(', ', $userColumns) . ') VALUES (' . implode(', ', array_fill(0, count($userColumns), '?')) . ')',
        $userValues,
        $userTypes
    );

    if (!$userId) {
        throw new RuntimeException('Error creating account.');
    }

    // Insert patient
    $patientColumns = ['user_id', 'full_name', 'date_of_birth', 'phone', 'email'];
    $patientValues = [(int) $userId, $fullName, $dateOfBirth, $phone, $email];
    $patientTypes = 'issss';
    
    if ($referredBy !== null) {
        $patientColumns[] = 'referred_by';
        $patientValues[] = $referredBy;
        $patientTypes .= 'i';
    }
    
    $patientColumns[] = 'created_by';
    $patientValues[] = (int) $userId;
    $patientTypes .= 'i';
    
    $patientColumns[] = 'sync_status';
    $patientValues[] = 'pending';
    $patientTypes .= 's';

    $patientId = $db->insert(
        'INSERT INTO patients (' . implode(', ', $patientColumns) . ') VALUES (' . implode(', ', array_fill(0, count($patientColumns), '?')) . ')',
        $patientValues,
        $patientTypes
    );

    if (!$patientId) {
        throw new RuntimeException('Error creating patient record.');
    }

    // Award referral points
    if ($referredBy !== null) {
        $db->execute("UPDATE patients SET points = COALESCE(points,0) + 50, sync_status = 'pending' WHERE id = ? LIMIT 1", [$referredBy], 'i');
        sync_push_row_now('patients', (int) $referredBy);
    }

    $conn->commit();
    
    // Sync to cloud
    if ($userId) sync_push_row_now('users', (int) $userId);
    if ($patientId) sync_push_row_now('patients', (int) $patientId);
    
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Auto-login after registration
if (!Auth::login($username, $password)) {
    http_response_code(200); // Registration succeeded but auto-login failed
    echo json_encode([
        'success' => true,
        'message' => 'Registration successful. Please login.',
        'redirect_to_login' => true
    ]);
    exit;
}

// Generate token for auto-login
$token = bin2hex(random_bytes(32));
$db->execute("INSERT INTO api_tokens (user_id, token) VALUES (?, ?)", [$userId, $token], 'is');

// Get user data
$user = Auth::user();

// Return success with token
echo json_encode([
    'success' => true,
    'message' => 'Registration successful',
    'token' => $token,
    'user_id' => $userId,
    'patient_id' => $patientId,
    'username' => $username,
    'full_name' => $fullName,
    'role' => 'patient'
]);