<?php
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireLogin();

$db = Database::getInstance();
$invoiceId = (int) ($_GET['id'] ?? 0);
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

$statusColors = [
    'paid' => 'success',
    'partial' => 'warning',
    'pending' => 'secondary',
    'overdue' => 'danger',
    'cancelled' => 'dark',
];
$statusColor = $statusColors[$invoice['payment_status']] ?? 'secondary';

// Get payments
$payments = $db->fetchAll(
    "SELECT * FROM payments WHERE invoice_id = ? ORDER BY payment_date",
    [$invoiceId],
    "i"
);

$pageTitle = 'Invoice: ' . $invoice['invoice_number'];

include '../layouts/header.php';
?>

<div class="container-fluid bills-page billing-invoice-view-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-file-invoice-dollar me-2 opacity-90" aria-hidden="true"></i>Invoice
                    #<?php echo htmlspecialchars((string) $invoice['invoice_number']); ?>
                </h2>
                <p class="mb-0 opacity-90">
                    <?php echo htmlspecialchars((string) $invoice['patient_name']); ?>
                    · <span class="badge bg-<?php echo $statusColor; ?>"><?php echo ucfirst((string) $invoice['payment_status']); ?></span>
                    · <?php echo formatCurrency($invoiceBalance); ?> due
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex flex-column align-items-stretch align-items-lg-end justify-content-center billing-invoice-view-hero-actions-wrap">
                <div class="billing-invoice-view-hero-actions" role="group" aria-label="Invoice actions">
                    <a href="invoices.php" class="btn billing-invoice-view-hero-btn">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">Back to Invoices</span><span class="d-sm-none">Back</span>
                    </a>
                    <a href="edit_invoice.php?id=<?php echo $invoiceId; ?>" class="btn billing-invoice-view-hero-btn">
                        <i class="fas fa-edit" aria-hidden="true"></i> Edit
                    </a>
                    <button type="button" class="btn billing-invoice-view-hero-btn" onclick="recordPayment()">
                        <i class="fas fa-dollar-sign" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">Record Payment</span><span class="d-sm-none">Pay</span>
                    </button>
                    <button type="button" class="btn billing-invoice-view-hero-btn" onclick="printInvoice()">
                        <i class="fas fa-print" aria-hidden="true"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row appointment-view-cols g-3">
        <div class="col-md-8 appointment-view-main">
            <!-- Invoice Details -->
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-file-invoice me-2" aria-hidden="true"></i>Invoice details</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <label class="fw-bold">Patient</label>
                            <p class="mb-0">
                                <a href="../patients/view.php?id=<?php echo (int) $invoice['patient_id']; ?>">
                                    <?php echo htmlspecialchars((string) $invoice['patient_name']); ?>
                                </a>
                            </p>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="fw-bold">Status</label>
                            <p class="mb-0">
                                <span class="badge bg-<?php echo $statusColor; ?>"><?php echo ucfirst((string) $invoice['payment_status']); ?></span>
                            </p>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="fw-bold">Invoice Date</label>
                            <p class="mb-0"><?php echo formatDate($invoice['invoice_date']); ?></p>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <label class="fw-bold">Due Date</label>
                            <p class="mb-0"><?php echo formatDate($invoice['due_date']); ?></p>
                        </div>
                        <?php if (!empty($invoice['appointment_id'])): ?>
                        <div class="col-12 mb-3">
                            <label class="fw-bold">Related Appointment</label>
                            <p class="mb-0">
                                <a href="../appointments/view.php?id=<?php echo (int) $invoice['appointment_id']; ?>">
                                    <?php echo formatDate($invoice['appointment_date']); ?>
                                    — <?php echo htmlspecialchars((string) ($invoice['treatment_type'] ?? '')); ?>
                                </a>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <h6 class="mb-3">Financial summary</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td>Subtotal</td>
                                    <td class="text-end"><?php echo formatCurrency($invoiceSubtotal); ?></td>
                                </tr>
                                <tr>
                                    <td>Discount (<?php echo ($invoice['discount_type'] ?? 'fixed') === 'percentage' ? ($invoice['discount_value'] ?? 0) . '%' : 'fixed'; ?>)</td>
                                    <td class="text-end">-<?php echo formatCurrency($invoiceDiscount); ?></td>
                                </tr>
                                <tr>
                                    <td>Tax (<?php echo (float) ($invoice['tax_rate'] ?? 0); ?>%)</td>
                                    <td class="text-end">+<?php echo formatCurrency($invoiceTax); ?></td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Total</td>
                                    <td class="text-end"><?php echo formatCurrency($invoiceTotal); ?></td>
                                </tr>
                                <tr>
                                    <td>Paid</td>
                                    <td class="text-end"><?php echo formatCurrency($invoicePaid); ?></td>
                                </tr>
                                <tr class="fw-bold <?php echo $invoiceBalance > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <td>Balance Due</td>
                                    <td class="text-end"><?php echo formatCurrency($invoiceBalance); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($invoice['notes'])): ?>
                    <hr>
                    <h6>Notes</h6>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars((string) $invoice['notes'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment History -->
            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-receipt me-2" aria-hidden="true"></i>Payment history</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($payments)): ?>
                        <p class="text-muted mb-0">No payments recorded yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
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
                                        <td><?php echo ucfirst((string) $payment['payment_method']); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($payment['reference_number'] ?? '')); ?></td>
                                        <td class="text-end"><?php echo formatCurrency((float) $payment['amount']); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($payment['notes'] ?? '')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4 appointment-view-side">
            <!-- Billing Address -->
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-map-marker-alt me-2" aria-hidden="true"></i>Billing address</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong><?php echo htmlspecialchars((string) $invoice['patient_name']); ?></strong></p>
                    <p class="mb-2">
                        <?php
                        $addr = trim((string) ($invoice['address'] ?? ''));
                        $country = trim((string) ($invoice['country'] ?? 'LB'));
                        $parts = array_filter([$addr, $country]);
                        echo htmlspecialchars(implode(', ', $parts) ?: 'LB');
                        ?>
                    </p>
                    <p class="mb-0 small">
                        Phone: <?php echo htmlspecialchars((string) ($invoice['phone'] ?? '')); ?><br>
                        Email: <?php echo htmlspecialchars((string) ($invoice['email'] ?? '')); ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($invoice['insurance_type'])): ?>
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-shield-alt me-2" aria-hidden="true"></i>Insurance</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p><strong>Type:</strong> <?php echo htmlspecialchars((string) $invoice['insurance_type']); ?></p>
                    <p><strong>Claim ID:</strong> <?php echo htmlspecialchars((string) ($invoice['insurance_claim_id'] ?? '')); ?></p>
                    <p><strong>Coverage:</strong> <?php echo formatCurrency((float) ($invoice['insurance_coverage'] ?? 0)); ?></p>
                    <p class="mb-0"><strong>Status:</strong>
                        <span class="badge bg-<?php echo ($invoice['insurance_status'] ?? '') === 'paid' ? 'success' : (($invoice['insurance_status'] ?? '') === 'approved' ? 'info' : 'warning'); ?>">
                            <?php echo ucfirst((string) ($invoice['insurance_status'] ?? '')); ?>
                        </span>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2" aria-hidden="true"></i>Metadata</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="small mb-2"><strong>Created:</strong> <?php echo formatDate($invoice['created_at'], 'M d, Y g:i A'); ?></p>
                    <p class="small mb-2"><strong>Created by:</strong> <?php echo htmlspecialchars((string) ($invoice['created_by_name'] ?? '')); ?></p>
                    <?php if (!empty($invoice['paid_at'])): ?>
                    <p class="small mb-0"><strong>Paid at:</strong> <?php echo formatDate($invoice['paid_at'], 'M d, Y g:i A'); ?></p>
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" name="invoice_id" value="<?php echo $invoiceId; ?>">
                    <div class="mb-3">
                        <label class="form-label" for="payAmount">Amount</label>
                        <input type="number" step="0.01" class="form-control" id="payAmount" name="amount" max="<?php echo htmlspecialchars((string) $invoiceBalance); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payMethod">Payment Method</label>
                        <select class="form-select" id="payMethod" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                            <option value="online">Online</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payRef">Reference Number</label>
                        <input type="text" class="form-control" id="payRef" name="reference_number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payNotes">Notes</label>
                        <textarea class="form-control" id="payNotes" name="notes" rows="2"></textarea>
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
