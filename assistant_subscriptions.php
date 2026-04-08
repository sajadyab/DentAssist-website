<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/api/_helpers.php';

// Only assistant and admin can access
Auth::requireLogin();
if (!in_array($_SESSION['role'], ['admin', 'assistant', 'doctor'])) {
    header('Location: dashboard.php');
    exit;
}

$pendingSubscriptions = repo_subscription_list_pending_subscriptions();

$pageTitle = 'Manage Subscriptions';
include 'layouts/header.php';
?>


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
                            echo repo_subscription_count_active();
                        ?></div>
                        <div class="text-muted">Active Subscriptions</div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="stats-number"><?php 
                            echo repo_subscription_count_expiring_soon_30_days();
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
            <form method="POST" action="api/confirm_subscription_payment.php" data-api="api/confirm_subscription_payment.php" data-message-target="#accept_message">
                <div class="modal-body">
                    <div id="accept_message" data-api-message></div>
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
