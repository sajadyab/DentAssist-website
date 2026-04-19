<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$invoiceId = $_GET['id'] ?? 0;
$patientAddressColumn = dbColumnExists('patients', 'address') ? 'p.address' : (dbColumnExists('patients', 'address_line1') ? 'p.address_line1' : 'NULL');

$invoice = $db->fetchOne(
    "SELECT i.*, p.full_name as patient_name, p.phone, p.email, {$patientAddressColumn} AS address, p.country,
            a.appointment_date, a.treatment_type,
            u.full_name as created_by_name
     FROM invoices i
     JOIN patients p ON i.patient_id = p.id
     LEFT JOIN appointments a ON i.appointment_id = a.id
     LEFT JOIN users u ON i.created_by = u.id
     WHERE i.id = ?",
    [$invoiceId],
    "i"
);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

$invoiceSubtotal = (float) ($invoice['subtotal'] ?? 0);
$invoiceDiscount = (float) ($invoice['discount_amount'] ?? 0);
$invoiceTax = (float) ($invoice['tax_amount'] ?? 0);
$invoiceTotal = (float) ($invoice['total_amount'] ?? max(0, $invoiceSubtotal - $invoiceDiscount + $invoiceTax));
$invoicePaid = (float) ($invoice['paid_amount'] ?? 0);
$invoiceBalance = (float) ($invoice['balance_due'] ?? max(0, $invoiceTotal - $invoicePaid));

// Get payments
$payments = $db->fetchAll(
    "SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date",
    [$invoiceId],
    "i"
);

$pageTitle = 'Invoice: ' . $invoice['invoice_number'];

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="fas fa-file-invoice"></i> Invoice #<?php echo $invoice['invoice_number']; ?>
        </h1>
        <div>
            <button class="btn btn-success" onclick="recordPayment()">
                <i class="fas fa-dollar-sign"></i> Record Payment
            </button>
            <button class="btn btn-info" onclick="printInvoice()">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="invoices.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Invoice Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Invoice Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Patient:</label>
                            <p class="mb-0">
                                <a href="../patients/view.php?id=<?php echo $invoice['patient_id']; ?>">
                                    <?php echo htmlspecialchars($invoice['patient_name']); ?>
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Status:</label>
                            <p class="mb-0">
                                <?php
                                $statusColors = [
                                    'paid' => 'success',
                                    'partial' => 'warning',
                                    'pending' => 'secondary',
                                    'overdue' => 'danger',
                                    'cancelled' => 'dark'
                                ];
                                $color = $statusColors[$invoice['payment_status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($invoice['payment_status']); ?></span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Invoice Date:</label>
                            <p class="mb-0"><?php echo formatDate($invoice['invoice_date']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Due Date:</label>
                            <p class="mb-0"><?php echo formatDate($invoice['due_date']); ?></p>
                        </div>
                        <?php if ($invoice['appointment_id']): ?>
                        <div class="col-12 mb-3">
                            <label class="fw-bold">Related Appointment:</label>
                            <p class="mb-0">
                                <a href="../appointments/view.php?id=<?php echo $invoice['appointment_id']; ?>">
                                    <?php echo formatDate($invoice['appointment_date']); ?> - <?php echo $invoice['treatment_type']; ?>
                                </a>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <!-- Financial Summary -->
                    <h6>Financial Summary</h6>
                    <table class="table table-sm">
                        <tr>
                            <td>Subtotal:</td>
                            <td class="text-end"><?php echo formatCurrency($invoiceSubtotal); ?></td>
                        </tr>
                        <tr>
                            <td>Discount (<?php echo ($invoice['discount_type'] ?? 'fixed') == 'percentage' ? ($invoice['discount_value'] ?? 0) . '%' : 'fixed'; ?>):</td>
                            <td class="text-end">-<?php echo formatCurrency($invoiceDiscount); ?></td>
                        </tr>
                        <tr>
                            <td>Tax (<?php echo (float) ($invoice['tax_rate'] ?? 0); ?>%):</td>
                            <td class="text-end">+<?php echo formatCurrency($invoiceTax); ?></td>
                        </tr>
                        <tr class="fw-bold">
                            <td>Total:</td>
                            <td class="text-end"><?php echo formatCurrency($invoiceTotal); ?></td>
                        </tr>
                        <tr>
                            <td>Paid:</td>
                            <td class="text-end"><?php echo formatCurrency($invoicePaid); ?></td>
                        </tr>
                        <tr class="fw-bold <?php echo $invoiceBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                            <td>Balance Due:</td>
                            <td class="text-end"><?php echo formatCurrency($invoiceBalance); ?></td>
                        </tr>
                    </table>

                    <?php if ($invoice['notes']): ?>
                    <hr>
                    <h6>Notes</h6>
                    <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Payment History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <p class="text-muted">No payments recorded yet.</p>
                    <?php else: ?>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Method</th>
                                    <th>Reference</th>
                                    <th class="text-end">Amount</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo formatDate($payment['payment_date']); ?></td>
                                    <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                    <td><?php echo $payment['reference_number']; ?></td>
                                    <td class="text-end"><?php echo formatCurrency($payment['amount']); ?></td>
                                    <td><?php echo $payment['notes']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Patient Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Billing Address</h5>
                </div>
                <div class="card-body">
                    <p><strong><?php echo htmlspecialchars($invoice['patient_name']); ?></strong></p>
                    <p>
                        <?php
                        $addr = trim((string) ($invoice['address'] ?? ''));
                        $country = trim((string) ($invoice['country'] ?? 'LB'));
                        $parts = array_filter([$addr, $country]);
                        echo htmlspecialchars(implode(', ', $parts) ?: 'LB');
                        ?>
                    </p>
                    <p>Phone: <?php echo $invoice['phone']; ?><br>
                       Email: <?php echo $invoice['email']; ?></p>
                </div>
            </div>

            <!-- Insurance Info (if any) -->
            <?php if ($invoice['insurance_type']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Insurance</h5>
                </div>
                <div class="card-body">
                    <p><strong>Type:</strong> <?php echo $invoice['insurance_type']; ?></p>
                    <p><strong>Claim ID:</strong> <?php echo $invoice['insurance_claim_id']; ?></p>
                    <p><strong>Coverage:</strong> <?php echo formatCurrency($invoice['insurance_coverage']); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="badge bg-<?php echo $invoice['insurance_status'] == 'paid' ? 'success' : ($invoice['insurance_status'] == 'approved' ? 'info' : 'warning'); ?>">
                            <?php echo ucfirst($invoice['insurance_status']); ?>
                        </span>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Metadata -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Metadata</h5>
                </div>
                <div class="card-body">
                    <p><small><strong>Created:</strong> <?php echo formatDate($invoice['created_at'], 'M d, Y g:i A'); ?></small></p>
                    <p><small><strong>Created by:</strong> <?php echo $invoice['created_by_name']; ?></small></p>
                    <?php if ($invoice['paid_at']): ?>
                    <p><small><strong>Paid at:</strong> <?php echo formatDate($invoice['paid_at'], 'M d, Y g:i A'); ?></small></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" name="invoice_id" value="<?php echo $invoiceId; ?>">
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" class="form-control" name="amount" max="<?php echo $invoiceBalance; ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                            <option value="online">Online</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number</label>
                        <input type="text" class="form-control" name="reference_number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePayment()">Record Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
function recordPayment() {
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function savePayment() {
    const form = document.getElementById('paymentForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    fetch('../api/record_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            location.reload();
        } else {
            alert('Error recording payment');
        }
    });
}

function printInvoice() {
    window.print();
}
</script>

<?php include '../layouts/footer.php'; ?>
