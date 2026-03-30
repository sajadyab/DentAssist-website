<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
if ($_SESSION['role'] != 'patient') {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$userId = Auth::userId();
$patientId = getPatientIdFromUserId($userId);

if (!$patientId) {
    die("Patient record not found.");
}

// Get invoices
$invoices = $db->fetchAll(
    "SELECT * FROM invoices WHERE patient_id = ? ORDER BY invoice_date DESC",
    [$patientId],
    "i"
);

// Get subscription payments
$subscriptions = $db->fetchAll(
    "SELECT * FROM subscription_payments WHERE patient_id = ? ORDER BY payment_date DESC",
    [$patientId],
    "i"
);

// Calculate totals
$totalDue = 0;
$totalPaid = 0;
foreach ($invoices as $inv) {
    $totalDue += $inv['balance_due'];
    $totalPaid += $inv['paid_amount'];
}

$pageTitle = 'My Bills';
include '../layouts/header.php';
?>

<style>
.billing-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
    position: relative;
    overflow: hidden;
}

.billing-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
    background-size: 50px 50px;
    animation: moveBackground 30s linear infinite;
}

@keyframes moveBackground {
    0% { transform: translate(0, 0); }
    100% { transform: translate(50px, 50px); }
}

.summary-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    height: 100%;
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.summary-number {
    font-size: 36px;
    font-weight: bold;
    margin-bottom: 5px;
}

.summary-label {
    font-size: 14px;
    color: #6c757d;
}

.bill-card {
    border-radius: 15px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.bill-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.bill-header {
    background: #f8f9fa;
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
}

.table-modern {
    margin-bottom: 0;
}

.table-modern thead {
    background: #f8f9fa;
}

.table-modern tbody tr:hover {
    background: #f8f9fa;
    transition: background 0.3s ease;
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}

.status-paid { background: #d4edda; color: #155724; }
.status-partial { background: #fff3cd; color: #856404; }
.status-pending { background: #e2e3e5; color: #383d41; }
.status-overdue { background: #f8d7da; color: #721c24; }

.btn-view {
    background: #667eea;
    color: white;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    transition: all 0.3s ease;
}

.btn-view:hover {
    background: #5a67d8;
    color: white;
    transform: translateY(-2px);
}

.payment-method-icon {
    width: 30px;
    height: 30px;
    background: #f8f9fa;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-right: 8px;
}
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Billing Header -->
    <div class="billing-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="fas fa-file-invoice-dollar"></i> My Bills & Payments
                </h2>
                <p class="mb-0">View and manage all your financial transactions</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white text-dark rounded p-2">
                    <small>Total Balance Due</small>
                    <h3 class="mb-0 <?php echo $totalDue > 0 ? 'text-danger' : 'text-success'; ?>">
                        <?php echo formatCurrency($totalDue); ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="summary-card">
                <div class="summary-number text-primary"><?php echo count($invoices); ?></div>
                <div class="summary-label">Total Invoices</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="summary-card">
                <div class="summary-number text-success"><?php echo formatCurrency($totalPaid); ?></div>
                <div class="summary-label">Total Paid</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="summary-card">
                <div class="summary-number text-danger"><?php echo formatCurrency($totalDue); ?></div>
                <div class="summary-label">Balance Due</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="summary-card">
                <div class="summary-number text-info"><?php echo count($subscriptions); ?></div>
                <div class="summary-label">Subscriptions</div>
            </div>
        </div>
    </div>

    <!-- Treatment Invoices Section -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="card-title mb-0">
                <i class="fas fa-stethoscope text-primary"></i> Treatment Invoices
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($invoices)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                    <p class="text-muted">No invoices yet.</p>
                    <a href="book.php" class="btn btn-primary">Book an Appointment</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            32
                                <th>Invoice #</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong>
                                </td>
                                <td><?php echo formatDate($inv['invoice_date']); ?></td>
                                <td><?php echo formatDate($inv['due_date']); ?></td>
                                <td><?php echo formatCurrency($inv['total_amount']); ?></td>
                                <td><?php echo formatCurrency($inv['paid_amount']); ?></td>
                                <td class="<?php echo $inv['balance_due'] > 0 ? 'text-danger fw-bold' : 'text-success'; ?>">
                                    <?php echo formatCurrency($inv['balance_due']); ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch($inv['payment_status']) {
                                        case 'paid': $statusClass = 'status-paid'; break;
                                        case 'partial': $statusClass = 'status-partial'; break;
                                        case 'pending': $statusClass = 'status-pending'; break;
                                        case 'overdue': $statusClass = 'status-overdue'; break;
                                        default: $statusClass = 'status-pending';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($inv['payment_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="view_invoice.php?id=<?php echo $inv['id']; ?>" class="btn btn-view">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Subscription Payments Section -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3">
            <h5 class="card-title mb-0">
                <i class="fas fa-crown text-warning"></i> Subscription Payments
            </h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($subscriptions)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-gem fa-4x text-muted mb-3"></i>
                    <p class="text-muted">No subscription payments yet.</p>
                    <a href="subscription.php" class="btn btn-primary">Subscribe Now</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            32
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Reference</th>
                            </thead>
                        <tbody>
                            <?php foreach ($subscriptions as $sub): ?>
                            <tr>
                                <td>
                                    <strong><?php echo ucfirst($sub['subscription_type']); ?> Plan</strong>
                                </td>
                                <td><?php echo formatCurrency($sub['amount']); ?></td>
                                <td>
                                    <span class="payment-method-icon">
                                        <?php 
                                        $icon = 'fa-credit-card';
                                        if($sub['payment_method'] == 'cash') $icon = 'fa-money-bill';
                                        if($sub['payment_method'] == 'online') $icon = 'fa-globe';
                                        if($sub['payment_method'] == 'clinic') $icon = 'fa-building';
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </span>
                                    <?php echo ucfirst(str_replace('_', ' ', $sub['payment_method'])); ?>
                                </td>
                                <td><?php echo formatDate($sub['payment_date']); ?></td>
                                <td>
                                    <?php
                                    $subStatusClass = '';
                                    switch($sub['status']) {
                                        case 'completed': $subStatusClass = 'status-paid'; break;
                                        case 'pending': $subStatusClass = 'status-pending'; break;
                                        case 'failed': $subStatusClass = 'status-overdue'; break;
                                        default: $subStatusClass = 'status-pending';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $subStatusClass; ?>">
                                        <?php echo ucfirst($sub['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars($sub['payment_reference'] ?? '-'); ?></code>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Information -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle text-info"></i> Payment Information
                    </h5>
                </div>
                <div class="card-body">
                    <p><strong>Accepted Payment Methods:</strong></p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-light text-dark p-2"><i class="fas fa-money-bill"></i> Cash</span>
                        <span class="badge bg-light text-dark p-2"><i class="fab fa-cc-visa"></i> Visa</span>
                        <span class="badge bg-light text-dark p-2"><i class="fab fa-cc-mastercard"></i> Mastercard</span>
                        <span class="badge bg-light text-dark p-2"><i class="fab fa-cc-amex"></i> American Express</span>
                        <span class="badge bg-light text-dark p-2"><i class="fas fa-university"></i> Bank Transfer</span>
                    </div>
                    <hr>
                    <p class="small text-muted">
                        <i class="fas fa-clock"></i> Payments are processed within 2-3 business days.
                        For questions about your bills, please contact our billing department.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-headset text-success"></i> Need Help?
                    </h5>
                </div>
                <div class="card-body">
                    <p>If you have any questions about your bills or payments:</p>
                    <div class="d-flex gap-3">
                        <a href="tel:+1234567890" class="btn btn-outline-primary">
                            <i class="fas fa-phone"></i> Call Us
                        </a>
                        <a href="mailto:billing@dentalclinic.com" class="btn btn-outline-primary">
                            <i class="fas fa-envelope"></i> Email Billing
                        </a>
                    </div>
                    <hr>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="fas fa-question-circle"></i>
                        <strong>Payment Plans Available</strong>
                        <p class="small mb-0">Ask about our flexible payment plans for major treatments.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>