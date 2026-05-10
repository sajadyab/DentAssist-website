<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';
require_once $basePath . '/includes/patient_cloud_repository.php';

header('Content-Type: application/json');

// Token Authentication
$headers = getallheaders();
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

$user = $db->fetchOne("SELECT id, role FROM users WHERE id = ?", [$tokenRow['user_id']], 'i');
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

$patientId = getPatientIdFromUserId($user['id']);
if (!$patientId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient record not found']);
    exit;
}

// Get clinic phone
$clinicPhone = '';
$phoneRow = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_phone'");
if ($phoneRow && !empty($phoneRow['setting_value'])) {
    $clinicPhone = preg_replace('/[^0-9+]/', '', $phoneRow['setting_value']);
}

// Get invoices
$invoices = patient_portal_list_invoices_cloud_first((int) $patientId);
$formattedInvoices = [];
$totalDue = 0;
$totalPaid = 0;

foreach ($invoices as $inv) {
    $balance = max(0, (float)($inv['subtotal'] ?? 0) - (float)($inv['paid_amount'] ?? 0));
    $totalDue += $balance;
    $totalPaid += (float)($inv['paid_amount'] ?? 0);
    
    $formattedInvoices[] = [
        'id' => (int)($inv['id'] ?? 0),
        'invoice_number' => $inv['invoice_number'] ?? '',
        'invoice_date' => $inv['invoice_date'] ?? '',
        'due_date' => $inv['due_date'] ?? '',
        'subtotal' => (float)($inv['subtotal'] ?? 0),
        'paid_amount' => (float)($inv['paid_amount'] ?? 0),
        'balance_due' => $balance,
        'payment_status' => $inv['payment_status'] ?? 'pending',
        'payment_method' => $inv['payment_method'] ?? '',
    ];
}

// Get subscription payments
$subscriptions = patient_portal_list_subscription_payments_cloud_first((int) $patientId);
$formattedSubs = [];

foreach ($subscriptions as $sub) {
    $formattedSubs[] = [
        'id' => (int)($sub['id'] ?? 0),
        'subscription_type' => $sub['subscription_type'] ?? '',
        'amount' => (float)($sub['amount'] ?? 0),
        'payment_method' => $sub['payment_method'] ?? '',
        'payment_date' => $sub['payment_date'] ?? '',
        'status' => $sub['status'] ?? 'pending',
        'payment_reference' => $sub['payment_reference'] ?? '',
    ];
}

echo json_encode([
    'success' => true,
    'data' => [
        'stats' => [
            'total_invoices' => count($formattedInvoices),
            'total_paid' => round($totalPaid, 2),
            'balance_due' => round($totalDue, 2),
        ],
        'invoices' => $formattedInvoices,
        'subscriptions' => $formattedSubs,
        'clinic_phone' => $clinicPhone,
    ]
]);