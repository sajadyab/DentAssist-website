<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$db = Database::getInstance();
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

$db->beginTransaction();

try {
    // Update patient subscription to active
    $db->execute(
        "UPDATE patients SET subscription_type = ?, subscription_start_date = ?, subscription_end_date = ?, subscription_status = 'active' WHERE id = ?",
        [$plan, $startDate, $endDate, $patientId],
        "sssi"
    );
    
    // Create invoice
    $invoiceNumber = generateInvoiceNumber();
    $invoiceId = $db->insert(
        "INSERT INTO invoices (patient_id, invoice_number, subtotal, total_amount, payment_status, invoice_date, due_date, notes, created_by) 
         VALUES (?, ?, ?, ?, 'paid', ?, ?, ?, ?)",
        [$patientId, $invoiceNumber, $amount, $amount, $startDate, $startDate, "Subscription: {$plan} plan (Annual) - Paid via OWO", $userId],
        "isddsssi"
    );
    
    // Record payment
    $db->insert(
        "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_reference, payment_date, status, processed_by) 
         VALUES (?, ?, ?, ?, ?, NOW(), 'completed', ?)",
        [$patientId, $plan, $amount, $paymentMethod, $reference, $userId],
        "isdssi"
    );
    
    $db->commit();
    
    // Clear session
    unset($_SESSION['pending_subscription']);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $db->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>