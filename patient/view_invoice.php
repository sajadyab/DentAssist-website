<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/patient_cloud_repository.php';

Auth::requireLogin();

// Check if user is patient
if ($_SESSION['role'] != 'patient') {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$invoiceId = $_GET['id'] ?? 0;
$userId = Auth::userId();
$patientId = getPatientIdFromUserId($userId);

if (!$patientId) {
    die("Patient record not found.");
}

// Get invoice - cloud-first, local fallback
$invoice = patient_portal_find_invoice_for_patient_cloud_first((int) $invoiceId, (int) $patientId);

// If invoice not found or doesn't belong to patient, redirect to bills
if (!$invoice) {
    header('Location: bills.php');
    exit;
}

$invoice['subtotal'] = isset($invoice['subtotal']) ? (float) $invoice['subtotal'] : 0.0;
$invoice['discount_amount'] = isset($invoice['discount_amount']) ? (float) $invoice['discount_amount'] : 0.0;
$invoice['tax_amount'] = isset($invoice['tax_amount']) ? (float) $invoice['tax_amount'] : 0.0;
$invoice['tax_rate'] = isset($invoice['tax_rate']) ? (float) $invoice['tax_rate'] : 0.0;
$invoice['total_amount'] = isset($invoice['total_amount'])
    ? (float) $invoice['total_amount']
    : max(0.0, $invoice['subtotal'] - $invoice['discount_amount'] + $invoice['tax_amount']);
$invoice['paid_amount'] = isset($invoice['paid_amount']) ? (float) $invoice['paid_amount'] : 0.0;
$invoice['balance_due'] = isset($invoice['balance_due'])
    ? (float) $invoice['balance_due']
    : max(0.0, $invoice['total_amount'] - $invoice['paid_amount']);
$invoice['appointment_id'] = isset($invoice['appointment_id']) ? (int) $invoice['appointment_id'] : 0;

$statusColors = [
    'paid' => 'success',
    'partial' => 'warning',
    'pending' => 'secondary',
    'overdue' => 'danger',
    'cancelled' => 'dark',
];
$statusColor = $statusColors[$invoice['payment_status']] ?? 'secondary';

// Get payments for this invoice - cloud-first, local fallback
$payments = patient_portal_list_invoice_payments_cloud_first((int) $invoiceId);

$pageTitle = 'Invoice #' . $invoice['invoice_number'];

include '../layouts/header.php';
?>


<div class="container-fluid bills-page patient-portal patient-invoice-print-root appointments-add-page patient-view-invoice-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-file-invoice-dollar me-2 opacity-90" aria-hidden="true"></i>Invoice
                    #<?php echo htmlspecialchars($invoice['invoice_number']); ?>
                </h2>
                <p class="mb-0 opacity-90 patient-invoice-hero-meta">
                    <span class="patient-invoice-hero-status"><?php echo ucfirst($invoice['payment_status']); ?></span>
                    · <?php echo htmlspecialchars(formatCurrency($invoice['balance_due'])); ?> balance
                    · Due <?php echo htmlspecialchars(formatDate($invoice['due_date'])); ?>
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 appointments-add-top-actions">
                <a href="bills.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Bills</span><span class="d-sm-none">Back</span>
                </a>
                <button type="button" class="btn btn-light text-dark appointments-add-top-btn patient-invoice-print-btn d-none d-md-inline-flex align-items-center justify-content-center" onclick="window.print()">
                    <i class="fas fa-print" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Print</span>
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-md-8">
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
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Invoice Number:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Status:</label>
                            <p class="mb-0">
                                <span class="badge bg-<?php echo $statusColor; ?>"><?php echo ucfirst($invoice['payment_status']); ?></span>
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
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Patient Name:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($invoice['patient_name']); ?></p>
                        </div>
                        <?php if ($invoice['appointment_id']): ?>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Treatment:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($invoice['treatment_type'] ?? 'N/A'); ?></p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Appointment Date:</label>
                            <p class="mb-0"><?php echo formatDate($invoice['appointment_date']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <!-- Financial Summary -->
                    <h6 class="mb-3">Financial summary</h6>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <tbody>
                            <tr>
                                <td>Subtotal</td>
                                <td class="text-end"><?php echo formatCurrency($invoice['subtotal']); ?></td>
                            </tr>
                            <?php if ($invoice['discount_amount'] > 0): ?>
                            <tr>
                                <td>Discount</td>
                                <td class="text-end text-danger">-<?php echo formatCurrency($invoice['discount_amount']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($invoice['tax_amount'] > 0): ?>
                            <tr>
                                <td>Tax (<?php echo $invoice['tax_rate']; ?>%)</td>
                                <td class="text-end">+<?php echo formatCurrency($invoice['tax_amount']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr class="fw-bold">
                                <td>Total</td>
                                <td class="text-end"><?php echo formatCurrency($invoice['total_amount']); ?></td>
                            </tr>
                            <tr>
                                <td>Paid amount</td>
                                <td class="text-end"><?php echo formatCurrency($invoice['paid_amount']); ?></td>
                            </tr>
                            <tr class="fw-bold <?php echo $invoice['balance_due'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                <td>Balance due</td>
                                <td class="text-end"><?php echo formatCurrency($invoice['balance_due']); ?></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($invoice['notes']): ?>
                    <hr>
                    <h6>Notes</h6>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
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
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['reference_number'] ?? '-'); ?></td>
                                        <td class="text-end"><?php echo formatCurrency($payment['amount']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['notes'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Billing Address -->
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-user me-2" aria-hidden="true"></i>Billing information</h5>
                        </div>
                    </div>
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
                    <p>
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($invoice['phone']); ?><br>
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($invoice['email']); ?>
                    </p>
                </div>
            </div>

            <!-- Payment Instructions -->
            <?php if ($invoice['payment_status'] != 'paid' && $invoice['balance_due'] > 0): ?>
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-wallet me-2" aria-hidden="true"></i>Payment instructions</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p><strong>Amount Due:</strong> <?php echo formatCurrency($invoice['balance_due']); ?></p>
                    <p><strong>Due Date:</strong> <?php echo formatDate($invoice['due_date']); ?></p>
                    <hr>
                    <p><strong>Payment Methods Accepted:</strong></p>
                    <ul>
                        <li>Cash at clinic</li>
                        <li>Credit/Debit Card</li>
                        <li>Bank Transfer</li>
                    </ul>
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i> Please make payment by the due date to avoid late fees.
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
