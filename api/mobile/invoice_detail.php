<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';
require_once $basePath . '/includes/patient_cloud_repository.php';

header('Content-Type: application/json');

// Token Auth
$headers = function_exists('getallheaders') ? getallheaders() : [];
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

$invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
if ($invoiceId <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invoice ID required']);
    exit;
}

// Get invoice (cloud-first)
$invoice = patient_portal_find_invoice_for_patient_cloud_first($invoiceId, $patientId);
if (!$invoice) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Invoice not found']);
    exit;
}

// Normalize financials
$subtotal = (float)($invoice['subtotal'] ?? 0);
$paid = (float)($invoice['paid_amount'] ?? 0);
$total = max($subtotal, $subtotal - (float)($invoice['discount_amount'] ?? 0) + (float)($invoice['tax_amount'] ?? 0));
$balance = max(0, $total - $paid);

// Get payments
$payments = patient_portal_list_invoice_payments_cloud_first($invoiceId);
$formattedPayments = [];
foreach ($payments as $p) {
    $formattedPayments[] = [
        'date' => $p['payment_date'] ?? '',
        'method' => $p['payment_method'] ?? '',
        'reference' => $p['reference_number'] ?? '',
        'amount' => (float)($p['amount'] ?? 0),
        'notes' => $p['notes'] ?? '',
    ];
}

echo json_encode([
    'success' => true,
    'data' => [
        'invoice_number' => $invoice['invoice_number'] ?? '',
        'invoice_date' => $invoice['invoice_date'] ?? '',
        'due_date' => $invoice['due_date'] ?? '',
        'patient_name' => $invoice['patient_name'] ?? '',
        'treatment_type' => $invoice['treatment_type'] ?? null,
        'appointment_date' => $invoice['appointment_date'] ?? null,
        'subtotal' => $subtotal,
        'total' => $total,
        'paid_amount' => $paid,
        'balance_due' => $balance,
        'payment_status' => $invoice['payment_status'] ?? 'pending',
        'notes' => $invoice['notes'] ?? '',
        'payments' => $formattedPayments,
    ]
]);