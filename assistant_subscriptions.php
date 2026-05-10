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


<div class="container-fluid bills-page staff-portal">
    <!-- Header with summary box -->
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-md-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-crown me-2 opacity-90" aria-hidden="true"></i>Manage Subscriptions
                </h2>
                <p class="mb-0 opacity-90">Review and activate pending subscription requests</p>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="bills-balance-wrap">
                    <div class="bills-balance-box">
                        <small>Pending requests</small>
                        <p class="bills-balance-amount"><?php echo count($pendingSubscriptions); ?></p>
                        <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;">
                            <?php echo repo_subscription_count_active(); ?> active subscriptions
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show my-3" role="alert">
            <i class="fas fa-check-circle me-2"></i> Subscription activated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Stats Cards -->
    <div class="row patient-stats-row mb-4 g-3">
        <div class="col-6 col-md-4 mb-3">
            <div class="bills-stats-card bills-stats-card--subs">
                <div class="bills-stats-number"><?php echo count($pendingSubscriptions); ?></div>
                <div class="bills-stats-label">Pending Subscriptions</div>
            </div>
        </div>
        <div class="col-6 col-md-4 mb-3">
            <div class="bills-stats-card bills-stats-card--paid">
                <div class="bills-stats-number"><?php echo repo_subscription_count_active(); ?></div>
                <div class="bills-stats-label">Active Subscriptions</div>
            </div>
        </div>
        <div class="col-6 col-md-4 mb-3">
            <div class="bills-stats-card bills-stats-card--invoices">
                <div class="bills-stats-number"><?php echo repo_subscription_count_expiring_soon_30_days(); ?></div>
                <div class="bills-stats-label">Expiring Soon (30 days)</div>
            </div>
        </div>
    </div>

    <!-- Pending Subscriptions Card -->
    <div class="card bills-dash-section-card">
        <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
            <div class="bills-arrivals-section-header__inner align-items-center">
                <div>
                    <h5 class="card-title mb-0"><i class="fas fa-clock me-2" aria-hidden="true"></i>Pending Subscriptions</h5>
                </div>
                <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($pendingSubscriptions)): ?>
                <div class="bills-empty-state text-center py-4 px-3">
                    <i class="fas fa-check-circle fa-3x text-success mb-3" style="opacity: 0.8;"></i>
                    <p class="text-muted mb-0">No pending subscriptions. All subscriptions are up to date!</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th class="text-center">Plan</th>
                                <th>Amount</th>
                                <th>Request Date</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingSubscriptions as $sub): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($sub['full_name']); ?></td>
                                    <td class="small text-muted">
                                        <?php if ($sub['phone']): ?>
                                            <div><i class="fas fa-phone-alt me-1 opacity-75" aria-hidden="true"></i><?php echo htmlspecialchars($sub['phone']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($sub['email']): ?>
                                            <div><i class="fas fa-envelope me-1 opacity-75" aria-hidden="true"></i><?php echo htmlspecialchars($sub['email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo ucfirst(htmlspecialchars($sub['subscription_type'])); ?></span>
                                    </td>
                                    <td class="fw-semibold"><?php echo formatCurrency($sub['amount']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars(formatDate($sub['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars(timeAgo($sub['created_at'])); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center flex-wrap">
                                            <button class="btn btn-sm btn-success" onclick="confirmPayment(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['full_name']); ?>', <?php echo $sub['amount']; ?>)">
                                                <i class="fas fa-check me-1"></i> Accept
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="rejectPayment(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars($sub['full_name']); ?>')">
                                                <i class="fas fa-times me-1"></i> Reject
                                            </button>
                                        </div>
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
