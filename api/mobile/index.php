<?php
// /api/mobile/index.php

// 1. Start session and load core PHP files (config, auth, DB, etc.)
$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/Database.php';
require_once $basePath . '/includes/auth.php'; // For Auth::requireLogin()
require_once $basePath . '/includes/functions.php'; // For helper functions

header('Content-Type: application/json');

// 2. CRITICAL: Session-less Authentication via API Key or Token
// Since standard PHP sessions don't work perfectly for mobile apps, your web project's login
// system should be updated to issue a simple token on login. For now, we'll simulate a session start.
// A more robust method for production is to create a simple token-based authentication.

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// --- Simplified Authentication ---
// For this example, we accept user_id and password in a login action,
// and for other actions, we require credentials in every request or an API key.
// A better approach: Issue a token upon login and require it in subsequent requests.
$user = null;
if ($action !== 'login') {
    // Temporary weak auth: Assume a POST var 'user_id'. NOT FOR PRODUCTION.
    $userId = $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required.']);
        exit;
    }
    // Simulate authentication by loading the user
    $db = Database::getInstance();
    $user = $db->fetchOne('SELECT * FROM users WHERE id = ? AND role = "patient"', [$userId], 'i');
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials.']);
        exit;
    }
    // Set a global variable for other functions to use, like the web session does.
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
} else {
    // Handle login action specifically
}

// 3. Route Requests to the Correct Logic
try {
    $response = ['success' => false, 'error' => 'Unknown action'];
    switch ($action) {
        case 'login':
            if ($method === 'POST') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $loginResult = Auth::login($username, $password);
                if ($loginResult) {
                    $db = Database::getInstance();
                    $user = $db->fetchOne('SELECT id FROM users WHERE username = ?', [$username], 's');
                    $patient = $db->fetchOne('SELECT id FROM patients WHERE user_id = ?', [$user['id']], 'i');
                    $response = ['success' => true, 'user_id' => $user['id'], 'patient_id' => $patient['id'], 'message' => 'Login successful'];
                } else {
                    $response = ['success' => false, 'error' => 'Login failed!'];
                }
            }
            break;

        case 'dashboard':
            // Fetches the same data as /patient/index.php
            require_once $basePath . '/includes/patient_cloud_repository.php';
            $patientId = $db->fetchOne('SELECT id FROM patients WHERE user_id = ?', [$_SESSION['user_id']], 'i')['id'];
            $nextAppointment = getNextAppointment($patientId); // Your existing function
            $response = ['success' => true, 'data' => ['next_appointment' => $nextAppointment]];
            break;

        case 'appointments':
            // Fetch list of appointments
            require_once $basePath . '/includes/patient_cloud_repository.php';
            $patientId = $db->fetchOne('SELECT id FROM patients WHERE user_id = ?', [$_SESSION['user_id']], 'i')['id'];
            $appointments = patient_portal_list_appointments_cloud_first($patientId);
            $response = ['success' => true, 'data' => $appointments];
            break;
            
        // ... Cases for 'queue', 'profile', 'bills', etc., each calling existing PHP functions ...
        // Example: case 'bills': call patient_portal_list_invoices_cloud_first(...);
    }
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}