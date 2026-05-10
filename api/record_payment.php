<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
if (!Auth::isAdmin() && !hasPermission((int) Auth::userId(), 'manage_billing')) {
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['invoice_id']) || !isset($input['amount']) || !isset($input['payment_method'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$db = Database::getInstance();
$paymentMethod = trim((string) $input['payment_method']);
$allowedPaymentMethods = ['cash', 'card', 'insurance', 'online', 'check'];

if ($paymentMethod === '' || !in_array($paymentMethod, $allowedPaymentMethods, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment method']);
    exit;
}

// Insert payment with optional sync metadata.
$paymentColumns = ['invoice_id', 'amount', 'payment_method', 'reference_number', 'notes', 'received_by'];
$paymentPlaceholders = ['?', '?', '?', '?', '?', '?'];
$paymentValues = [
    $input['invoice_id'],
    $input['amount'],
    $paymentMethod,
    $input['reference_number'] ?? null,
    $input['notes'] ?? null,
    Auth::userId(),
];
$paymentTypes = 'idsssi';

if (function_exists('dbColumnExists') && dbColumnExists('payments', 'sync_status')) {
    $paymentColumns[] = 'sync_status';
    $paymentPlaceholders[] = '?';
    $paymentValues[] = 'pending';
    $paymentTypes .= 's';
}

$paymentId = $db->insert(
    'INSERT INTO payments (' . implode(', ', $paymentColumns) . ') VALUES (' . implode(', ', $paymentPlaceholders) . ')',
    $paymentValues,
    $paymentTypes
);

if (!$paymentId) {
    echo json_encode(['success' => false, 'message' => 'Failed to record payment']);
    exit;
}

if (function_exists('dbColumnExists') && dbColumnExists('payments', 'local_id')) {
    $db->execute("UPDATE payments SET local_id = ? WHERE id = ?", [$paymentId, $paymentId], 'ii');
}

// Update invoice paid_amount and status
$invoice = $db->fetchOne(
    dbColumnExists('invoices', 'total_amount')
        ? "SELECT total_amount, paid_amount FROM invoices WHERE id = ?"
        : "SELECT subtotal AS total_amount, paid_amount FROM invoices WHERE id = ?",
    [$input['invoice_id']],
    "i"
);
$newPaid = $invoice['paid_amount'] + $input['amount'];
$newStatus = 'pending';
if ($newPaid >= $invoice['total_amount']) {
    $newStatus = 'paid';
} elseif ($newPaid > 0) {
    $newStatus = 'partial';
}

$db->execute(
    "UPDATE invoices
     SET paid_amount = ?,
         payment_status = ?,
         payment_method = ?,
         paid_at = IF(? = 'paid', NOW(), paid_at)
     WHERE id = ?",
    [$newPaid, $newStatus, $paymentMethod, $newStatus, $input['invoice_id']],
    "dsssi"
);

sync_push_row_now('invoices', $input['invoice_id']);
sync_push_row_now('payments', $paymentId);

logAction('CREATE', 'payments', $paymentId, null, $input);
echo json_encode(['success' => true, 'message' => 'Payment recorded']);
?>
