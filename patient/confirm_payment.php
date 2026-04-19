<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();
$userId = Auth::userId();
$patientId = getPatientIdFromUserId($userId);
$data = json_decode(file_get_contents('php://input'), true);

if (!$patientId) {
    echo json_encode(['success' => false, 'error' => 'Patient not found']);
    exit;
}

$plan = $data['plan'] ?? '';
$amount = $data['amount'] ?? 0;
$reference = $data['reference'] ?? '';
$paymentMethod = $data['payment_method'] ?? 'owo';

if (!$plan || !$amount) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime('+1 year'));

// Some installs may not have subscription_status yet (older schema).
$hasSubscriptionStatus = (bool) $db->fetchOne(
    "SELECT 1
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'patients'
       AND COLUMN_NAME = 'subscription_status'
     LIMIT 1"
);

$hasSubscriptionPaymentsTable = (bool) $db->fetchOne(
    "SELECT 1
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'subscription_payments'
     LIMIT 1"
);

$conn->begin_transaction();

try {
    // Update patient subscription to active
    if ($hasSubscriptionStatus) {
        $db->execute(
            "UPDATE patients
             SET subscription_type = ?, subscription_start_date = ?, subscription_end_date = ?, subscription_status = 'active'
             WHERE id = ?",
            [$plan, $startDate, $endDate, $patientId],
            "sssi"
        );
    } else {
        $db->execute(
            "UPDATE patients
             SET subscription_type = ?, subscription_start_date = ?, subscription_end_date = ?
             WHERE id = ?",
            [$plan, $startDate, $endDate, $patientId],
            "sssi"
        );
    }
    
    // Create invoice
    $invoiceNumber = generateInvoiceNumber();
    $invoiceColumns = ['patient_id', 'invoice_number', 'subtotal', 'payment_status', 'invoice_date', 'due_date', 'notes', 'created_by'];
    $invoiceParams = [$patientId, $invoiceNumber, $amount, 'paid', $startDate, $startDate, "Subscription: {$plan} plan (Annual) - Paid via OWO", $userId];
    $invoiceTypes = 'isdssssi';
    if (dbColumnExists('invoices', 'total_amount')) {
        array_splice($invoiceColumns, 3, 0, 'total_amount');
        array_splice($invoiceParams, 3, 0, $amount);
        $invoiceTypes = 'isddssssi';
    }

    $invoiceId = $db->insert(
        'INSERT INTO invoices (' . implode(', ', $invoiceColumns) . ')'
        . ' VALUES (' . implode(', ', array_fill(0, count($invoiceColumns), '?')) . ')',
        $invoiceParams,
        $invoiceTypes
    );
    
    sync_push_row_now('invoices', $invoiceId);
    
    // Record payment
    if ($hasSubscriptionPaymentsTable) {
        $db->insert(
            "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_reference, payment_date, status, processed_by) 
             VALUES (?, ?, ?, ?, ?, NOW(), 'completed', ?)",
            [$patientId, $plan, $amount, $paymentMethod, $reference, $userId],
            "isdssi"
        );
    }
    
    $conn->commit();
    
    // Clear session
    unset($_SESSION['pending_subscription']);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    try {
        $conn->rollback();
    } catch (Exception $ex) {
        // ignore rollback errors
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>