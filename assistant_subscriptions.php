<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Only assistant and admin can access
Auth::requireLogin();
if (!in_array($_SESSION['role'], ['admin', 'assistant', 'doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();

// Get all pending subscriptions
$pendingSubscriptions = $db->fetchAll(
    "SELECT p.id, p.full_name, p.phone, p.email, p.subscription_type, p.subscription_start_date, p.subscription_end_date, p.subscription_status,
            sp.amount, sp.created_at, sp.payment_method, sp.id as payment_id
     FROM patients p
     LEFT JOIN subscription_payments sp ON p.id = sp.patient_id AND sp.status = 'pending'
     WHERE p.subscription_status = 'pending'
     ORDER BY sp.created_at DESC"
);

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_payment'])) {
    $patientId = $_POST['patient_id'];
    $amount = $_POST['amount'];
    $reference = $_POST['reference'] ?? 'CASH-' . time();
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $userId = Auth::userId();
    
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+1 year'));
    
    try {
        // Get patient subscription type
        $patient = $db->fetchOne("SELECT subscription_type FROM patients WHERE id = ?", [$patientId], "i");
        $plan = $patient['subscription_type'];
        
        // Begin transaction
        $db->beginTransaction();
        
        // Update patient to active with proper dates
        $db->execute(
            "UPDATE patients SET subscription_status = 'active', subscription_start_date = ?, subscription_end_date = ? WHERE id = ?",
            [$startDate, $endDate, $patientId],
            "ssi"
        );
        
        // Update subscription payment to completed
        $db->execute(
            "UPDATE subscription_payments SET status = 'completed', payment_reference = ?, payment_date = NOW(), processed_by = ? WHERE patient_id = ? AND status = 'pending'",
            [$reference, $userId, $patientId],
            "sii"
        );
        
        // Check if invoice exists
        $invoiceExists = $db->fetchOne(
            "SELECT id FROM invoices WHERE patient_id = ? AND notes LIKE '%Subscription%' AND payment_status = 'pending'",
            [$patientId],
            "i"
        );
        
        if ($invoiceExists) {
            // Update existing invoice to paid
            $db->execute(
                "UPDATE invoices SET payment_status = 'paid', paid_amount = total_amount, paid_at = NOW() WHERE id = ?",
                [$invoiceExists['id']],
                "i"
            );
        } else {
            // Create new invoice for subscription
            $invoiceNumber = generateInvoiceNumber();
            $prices = ['basic' => 29, 'premium' => 49, 'family' => 79];
            $annualAmount = ($prices[$plan] ?? 29) * 12;
            
            $db->insert(
                "INSERT INTO invoices (patient_id, invoice_number, subtotal, total_amount, payment_status, invoice_date, due_date, notes, created_by, paid_at) 
                 VALUES (?, ?, ?, ?, 'paid', ?, ?, ?, ?, NOW())",
                [$patientId, $invoiceNumber, $annualAmount, $annualAmount, $startDate, $startDate, "Subscription: {$plan} plan (Annual) - Paid at Clinic", $userId],
                "isddsssi"
            );
        }
        
        $db->commit();
        
        $success = "Payment confirmed! Subscription activated for patient. Valid until " . formatDate($endDate);
        
        // Refresh the page to show updated list
        header('Location: assistant_subscriptions.php?success=1');
        exit;
        
    } catch (Exception $e) {
        $db->rollback();
        $error = "Error: " . $e->getMessage();
    }
}

$pageTitle = 'Manage Subscriptions';
include 'layouts/header.php';
?>

<style>
.subscription-stats {
    background: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.stats-number {
    font-size: 32px;
    font-weight: bold;
    color: #667eea;
}

.btn-confirm {
    background: #28a745;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 20px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-confirm:hover {
    background: #218838;
    transform: scale(1.02);
}

.btn-reject {
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 20px;
    transition: all 0.3s ease;
    cursor: pointer;
    margin-right: 5px;
}

.btn-reject:hover {
    background: #c82333;
    transform: scale(1.02);
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status-pending { background: #ffc107; color: #212529; }
.status-active { background: #28a745; color: white; }
.status-expired { background: #dc3545; color: white; }

.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> Subscription activated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="subscription-stats">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <div class="stats-number"><?php echo count($pendingSubscriptions); ?></div>
                        <div class="text-muted">Pending Subscriptions</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="stats-number"><?php 
                            $activeCount = $db->fetchOne("SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active'");
                            echo $activeCount['count']; 
                        ?></div>
                        <div class="text-muted">Active Subscriptions</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="stats-number"><?php 
                            $expiringCount = $db->fetchOne("SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active' AND subscription_end_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)");
                            echo $expiringCount['count']; 
                        ?></div>
                        <div class="text-muted">Expiring Soon (30 days)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">
                <i class="fas fa-clock text-warning"></i> Pending Subscriptions
            </h5>
        </div>
        <div class="card-body">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($pendingSubscriptions)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                    <p class="text-muted">No pending subscriptions.</p>
                    <p>All subscriptions are up to date!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            32
                                <th>Patient</th>
                                <th>Contact</th>
                                <th>Plan</th>
                                <th>Amount</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </thead>
                        <tbody>
                            <?php foreach ($pendingSubscriptions as $sub): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($sub['full_name']); ?></strong>
                                 </td>
                                 <td>
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($sub['phone']); ?><br>
                                    <i class="fas fa-envelope"></i> <small><?php echo htmlspecialchars($sub['email']); ?></small>
                                 </td>
                                 <td>
                                    <span class="badge bg-primary"><?php echo ucfirst($sub['subscription_type']); ?></span>
                                 </td>
                                 <td><?php echo formatCurrency($sub['amount']); ?></td>
                                 <td>
                                    <i class="fas fa-calendar"></i> <?php echo formatDate($sub['created_at']); ?><br>
                                    <small class="text-muted"><?php echo timeAgo($sub['created_at']); ?></small>
                                 </td>
                                 <td class="action-buttons">
                                    <button class="btn-confirm" onclick="confirmPayment(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['full_name']); ?>', <?php echo $sub['amount']; ?>)">
                                        <i class="fas fa-check"></i> Accept & Activate
                                    </button>
                                    <button class="btn-reject" onclick="rejectPayment(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['full_name']); ?>')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Accept Payment Confirmation Modal -->
<div class="modal fade" id="acceptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-credit-card"></i> Confirm Payment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="patient_id" id="accept_patient_id">
                    <input type="hidden" name="amount" id="accept_amount">
                    <input type="hidden" name="payment_method" value="cash">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Confirm that you have received payment from:
                        <strong id="accept_patient_name"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Receipt Number / Reference</label>
                        <input type="text" class="form-control" name="reference" placeholder="Enter receipt number (e.g., REC-001)">
                        <small class="text-muted">Optional but recommended for record keeping</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This will activate the subscription immediately and generate an invoice.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="confirm_payment" class="btn btn-success">
                        <i class="fas fa-check-circle"></i> Confirm & Activate
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Payment Confirmation Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-times-circle"></i> Reject Subscription
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" name="patient_id" id="reject_patient_id">
                    <input type="hidden" name="action" value="reject">
                    
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        Are you sure you want to reject this subscription for:
                        <strong id="reject_patient_name"></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason (Optional)</label>
                        <textarea class="form-control" name="rejection_reason" rows="2" placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="submitReject()">
                        <i class="fas fa-times"></i> Confirm Reject
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function confirmPayment(patientId, patientName, amount) {
    document.getElementById('accept_patient_id').value = patientId;
    document.getElementById('accept_patient_name').textContent = patientName;
    document.getElementById('accept_amount').value = amount;
    new bootstrap.Modal(document.getElementById('acceptModal')).show();
}

function rejectPayment(patientId, patientName) {
    document.getElementById('reject_patient_id').value = patientId;
    document.getElementById('reject_patient_name').textContent = patientName;
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function submitReject() {
    const patientId = document.getElementById('reject_patient_id').value;
    const reason = document.querySelector('[name="rejection_reason"]').value;
    
    fetch('api/reject_subscription.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            patient_id: patientId,
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Subscription rejected successfully');
            location.reload();
        } else {
            alert('Error rejecting subscription: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error rejecting subscription');
    });
}
</script>

<?php include 'layouts/footer.php'; ?>