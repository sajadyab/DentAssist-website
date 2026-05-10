<?php
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$db = Database::getInstance();

// Mobile flow: patient_id provided directly
if (isset($data['patient_id']) && (int)$data['patient_id'] > 0) {
    $patientId = (int)$data['patient_id'];
    $patient = $db->fetchOne("SELECT id FROM patients WHERE id = ?", [$patientId], 'i');
    if (!$patient) {
        echo json_encode(['success' => false, 'error' => 'Patient not found']);
        exit;
    }
    $userId = null;
} else {
    // Web flow: session-based
    if (!Auth::isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    $userId = Auth::userId();
    $patientId = getPatientIdFromUserId($userId);
    if (!$patientId) {
        echo json_encode(['success' => false, 'error' => 'Patient not found']);
        exit;
    }
}

$plan     = $data['plan'] ?? '';
$amount   = (float)($data['amount'] ?? 0);
$reference = $data['reference'] ?? '';
$paymentMethod = $data['payment_method'] ?? 'owo';

if (!$plan || !$amount) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$startDate = date('Y-m-d');
$endDate   = date('Y-m-d', strtotime('+1 year'));

// Update patient subscription to active
$db->execute(
    "UPDATE patients SET subscription_type = ?, subscription_status = 'active', 
     subscription_start_date = ?, subscription_end_date = ? WHERE id = ?",
    [$plan, $startDate, $endDate, $patientId],
    'sssi'
);

// Create invoice
$invoiceNumber = generateInvoiceNumber();
$db->execute(
    "INSERT INTO invoices (patient_id, invoice_number, subtotal, payment_status, invoice_date, due_date, notes, created_by) 
     VALUES (?, ?, ?, 'paid', ?, ?, ?, ?)",
    [$patientId, $invoiceNumber, $amount, $startDate, $startDate, "Subscription: {$plan} plan (Annual) - Paid via OWO", $userId ?? $patientId],
    'isdsssi'
);

// Record payment
$hasSubscriptionPayments = dbTableExists('subscription_payments');
if ($hasSubscriptionPayments) {
    $db->execute(
        "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_reference, payment_date, status, processed_by) 
         VALUES (?, ?, ?, ?, ?, NOW(), 'completed', ?)",
        [$patientId, $plan, $amount, $paymentMethod, $reference, $userId ?? $patientId],
        'isdssi'
    );
}

// Clear session pending subscription if web
if (Auth::isLoggedIn()) {
    unset($_SESSION['pending_subscription']);
}

echo json_encode(['success' => true]);