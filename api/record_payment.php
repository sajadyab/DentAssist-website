<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['invoice_id']) || !isset($input['amount']) || !isset($input['payment_method'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = Database::getInstance();

// Insert payment
$paymentId = $db->insert(
    "INSERT INTO payments (invoice_id, amount, payment_method, reference_number, notes, received_by)
     VALUES (?, ?, ?, ?, ?, ?)",
    [
        $input['invoice_id'],
        $input['amount'],
        $input['payment_method'],
        $input['reference_number'] ?? null,
        $input['notes'] ?? null,
        Auth::userId()
    ],
    "idsssi"
);

if (!$paymentId) {
    echo json_encode(['success' => false, 'message' => 'Failed to record payment']);
    exit;
}

// Update invoice paid_amount and status
$invoice = $db->fetchOne("SELECT total_amount, paid_amount FROM invoices WHERE id = ?", [$input['invoice_id']], "i");
$newPaid = $invoice['paid_amount'] + $input['amount'];
$newStatus = 'pending';
if ($newPaid >= $invoice['total_amount']) {
    $newStatus = 'paid';
} elseif ($newPaid > 0) {
    $newStatus = 'partial';
}

$db->execute(
    "UPDATE invoices SET paid_amount = ?, payment_status = ?, paid_at = IF(? = 'paid', NOW(), paid_at) WHERE id = ?",
    [$newPaid, $newStatus, $newStatus, $input['invoice_id']],
    "dssi"
);

logAction('CREATE', 'payments', $paymentId, null, $input);
echo json_encode(['success' => true, 'message' => 'Payment recorded']);
?>