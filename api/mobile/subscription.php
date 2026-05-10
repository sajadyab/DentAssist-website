<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/auth.php';
require_once $basePath . '/includes/functions.php';

header('Content-Type: application/json');

// Token Authentication
$headers = [];
if (function_exists('getallheaders')) {
    $headers = getallheaders();
}
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

$db = Database::getInstance();
$tokenRow = $db->fetchOne("SELECT user_id FROM api_tokens WHERE token = ?", [$token], 's');

if (!$tokenRow) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$userId = (int)$tokenRow['user_id'];
$patientId = getPatientIdFromUserId($userId);

if (!$patientId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient not found']);
    exit;
}

// GET request - load subscription data
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// If no POST body, treat as GET request
if (empty($input) || !isset($input['plan'])) {
    $patient = $db->fetchOne(
        "SELECT subscription_type, subscription_status, subscription_start_date, subscription_end_date 
         FROM patients WHERE id = ?",
        [$patientId], 'i'
    );

    $plans = $db->fetchAll("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY display_order");
    $formattedPlans = [];
    foreach ($plans as $plan) {
        $formattedPlans[] = [
            'plan_key' => $plan['plan_key'],
            'plan_name' => $plan['plan_name'],
            'monthly_price' => (float)$plan['monthly_price'],
            'annual_price' => (float)$plan['annual_price'],
            'features' => $plan['features'] ?? '',
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'current_plan' => $patient['subscription_type'] ?? 'none',
            'subscription_status' => $patient['subscription_status'] ?? 'none',
            'start_date' => $patient['subscription_start_date'] ?? null,
            'end_date' => $patient['subscription_end_date'] ?? null,
            'plans' => $formattedPlans,
        ]
    ]);
    exit;
}

// POST request - subscribe

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan = $input['plan'] ?? '';
    $action = $input['action'] ?? '';
    $billingCycle = $input['billing_cycle'] ?? 'monthly';

    if (!in_array($plan, ['basic', 'premium', 'family'])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid plan']);
        exit;
    }

    $planPrices = [
        'basic'   => ['monthly' => 29.00, 'yearly' => 348.00],
        'premium' => ['monthly' => 49.00, 'yearly' => 588.00],
        'family'  => ['monthly' => 79.00, 'yearly' => 948.00],
    ];
    $amount = $planPrices[$plan][$billingCycle] ?? $planPrices[$plan]['monthly'];

    $startDate = date('Y-m-d');
    $endDate = $billingCycle === 'yearly' 
        ? date('Y-m-d', strtotime('+1 year'))
        : date('Y-m-d', strtotime('+1 month'));

    if ($action === 'clinic_payment') {
        // Set subscription to pending and record the pending payment
        $db->execute(
            "UPDATE patients SET subscription_type = ?, subscription_status = 'pending', 
             subscription_start_date = ?, subscription_end_date = ? WHERE id = ?",
            [$plan, $startDate, $endDate, $patientId],
            'sssi'
        );

        // Insert pending payment record (gives assistant a timestamp)
        $hasSubscriptionPayments = dbTableExists('subscription_payments');
        if ($hasSubscriptionPayments) {
            $db->execute(
                "INSERT INTO subscription_payments 
                 (patient_id, subscription_type, amount, payment_method, payment_date, status)
                 VALUES (?, ?, ?, ?, NOW(), 'pending')",
                [$patientId, $plan, $amount, 'clinic'],
                'isds'
            );
        }

        echo json_encode([
            'success' => true,
            'message' => 'Subscription request created. Please visit the clinic to complete payment.',
            'amount' => $amount
        ]);
    } else {
        // Online payment – do nothing for now, just return info
        echo json_encode(['success' => true, 'message' => 'Proceed to online payment', 'amount' => $amount]);
    }
    exit;
}