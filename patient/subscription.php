<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/patient_cloud_repository.php';

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

// Get patient current subscription
$patient = patient_portal_fetch_patient_cloud_first((int) $patientId);
if (!$patient) {
    die("Patient record not found.");
}

// Generate referral code if not exists
if (empty($patient['referral_code'])) {
    $newCode = strtoupper(substr(md5($patientId . uniqid()), 0, 8));
    try {
        patient_portal_set_referral_code_cloud_first((int) $patientId, $newCode);
        $db->execute("UPDATE patients SET referral_code = ?, sync_status = 'pending' WHERE id = ?", [$newCode, $patientId], "si");
        sync_push_row_now('patients', (int) $patientId);
        $patient = patient_portal_fetch_patient_cloud_first((int) $patientId);
    } catch (Throwable $e) {
        error_log('Patient subscription cloud-first code update failed: ' . $e->getMessage());
    }
}

$currentPlan = $patient['subscription_type'] ?? 'none';
$subscriptionStatus = $patient['subscription_status'] ?? 'none';
$showPaymentForm = false;
$selectedPlan = $_GET['plan'] ?? '';
$selectedPlan = in_array($selectedPlan, ['basic', 'premium', 'family'], true) ? $selectedPlan : '';

// If plan selected from index page
if ($selectedPlan && in_array($selectedPlan, ['basic', 'premium', 'family']) && $currentPlan == 'none') {
    $showPaymentForm = true;
}

$pageTitle = 'My Subscription';
include '../layouts/header.php';
?>


<div class="container-fluid bills-page subscription-page">
  

    <!-- Header: same layout as patient/points (title col-md-8, frosted box col-md-4) -->
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-md-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-crown me-2 opacity-90" aria-hidden="true"></i>My Subscription
                </h2>
                <p class="mb-0 opacity-90">Choose a plan that fits your needs and get exclusive benefits</p>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="bills-balance-wrap">
                    <div class="bills-balance-box">
                        <small>Subscription status</small>
                        <?php
                        $statusLine = '—';
                        $statusSub = 'No plan yet';
                        if ($currentPlan !== 'none') {
                            $statusSub = ucfirst($currentPlan) . ' plan';
                            if ($subscriptionStatus === 'active') {
                                $statusLine = 'Active';
                            } elseif ($subscriptionStatus === 'pending') {
                                $statusLine = 'Pending payment';
                            } elseif (in_array($subscriptionStatus, ['expired', 'cancelled'], true)) {
                                $statusLine = $subscriptionStatus === 'cancelled' ? 'Cancelled' : 'Expired';
                            } else {
                                $statusLine = ucfirst((string) $subscriptionStatus);
                            }
                        }
                        ?>
                        <p class="bills-balance-amount"><?php echo htmlspecialchars($statusLine); ?></p>
                        <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;"><?php echo htmlspecialchars($statusSub); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="message"></div>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success py-2 mt-3">Subscription request created. Please visit the clinic assistant to complete payment.</div>
    <?php endif; ?>

    <?php if ($currentPlan != 'none' && $subscriptionStatus == 'pending'): ?>
        <!-- Pending Payment Alert -->
        <div class="sub-alert-pending">
            <i class="fas fa-clock me-1" style="color:var(--bills-accent-deep);"></i>
            <strong>Pending payment</strong> — your subscription request is pending. Please visit the clinic assistant to complete your payment.
            <div class="mt-2">
                <small>Valid until: <?php echo formatDate($patient['subscription_end_date']); ?></small>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($currentPlan != 'none' && $subscriptionStatus == 'active'): ?>
        <!-- Active Subscription -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3><?php echo ucfirst($currentPlan); ?> Plan - Active</h3>
                        <p>Valid from <?php echo formatDate($patient['subscription_start_date']); ?> to <?php echo formatDate($patient['subscription_end_date']); ?></p>
                        <ul>
                            <?php if ($currentPlan == 'basic'): ?>
                                <li>2 free cleanings per year</li>
                                <li>10% off all treatments</li>
                                <li>Annual cost: $348</li>
                            <?php elseif ($currentPlan == 'premium'): ?>
                                <li>4 free cleanings per year</li>
                                <li>20% off all treatments</li>
                                <li>Priority scheduling</li>
                                <li>Annual cost: $588</li>
                            <?php elseif ($currentPlan == 'family'): ?>
                                <li>Covers up to 4 family members</li>
                                <li>3 cleanings per member per year</li>
                                <li>15% off all treatments</li>
                                <li>Annual cost: $948</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-check-circle fa-4x active-plan-icon" aria-hidden="true"></i>
                        <p class="mt-2">Your subscription is active</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($currentPlan == 'none'): ?>
        <!-- Plan Selection -->
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card payment-card plan-option-card h-100<?php echo $selectedPlan === 'basic' ? ' plan-option-card--selected' : ''; ?>">
                    <div class="card-body text-center p-4">
                        <?php if ($selectedPlan === 'basic'): ?>
                            <div class="plan-selected-badge">Selected Plan</div>
                        <?php endif; ?>
                        <div class="payment-icon">
                            <i class="fas fa-tooth" aria-hidden="true"></i>
                        </div>
                        <h3>Basic Plan</h3>
                        <h2 class="plan-price">$29<span class="small">/month</span></h2>
                        <p class="text-muted">$348/year</p>
                        <ul class="list-unstyled text-start mt-3 plan-feature-list">
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> 2 free cleanings/year</li>
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> 10% off treatments</li>
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> Free consultation</li>
                        </ul>
                        <button
                            type="button"
                            class="btn btn-subscribe-plan btn-subscribe-plan--basic w-100 mt-3"
                            onclick="selectPlan('basic')"
                            <?php echo $selectedPlan === 'basic' ? 'disabled aria-disabled="true"' : ''; ?>
                        >
                            <?php echo $selectedPlan === 'basic' ? 'Selected' : ($selectedPlan === '' ? 'Select Plan' : 'Choose This Plan'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card payment-card plan-option-card h-100 border-highlight-premium<?php echo $selectedPlan === 'premium' ? ' plan-option-card--selected' : ''; ?>">
                    <div class="card-body text-center p-4">
                        <?php if ($selectedPlan === 'premium'): ?>
                            <div class="plan-selected-badge">Selected Plan</div>
                        <?php endif; ?>
                        <div class="payment-icon">
                            <i class="fas fa-crown premium-crown-icon" aria-hidden="true"></i>
                        </div>
                        <h3>Premium Plan</h3>
                        <h2 class="plan-price">$49<span class="small">/month</span></h2>
                        <p class="text-muted">$588/year</p>
                        <ul class="list-unstyled text-start mt-3 plan-feature-list">
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> 4 free cleanings/year</li>
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> 20% off treatments</li>
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> Priority scheduling</li>
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> Emergency access</li>
                        </ul>
                        <button
                            type="button"
                            class="btn btn-subscribe-plan btn-subscribe-plan--premium w-100 mt-3"
                            onclick="selectPlan('premium')"
                            <?php echo $selectedPlan === 'premium' ? 'disabled aria-disabled="true"' : ''; ?>
                        >
                            <?php echo $selectedPlan === 'premium' ? 'Selected' : ($selectedPlan === '' ? 'Select Plan' : 'Choose This Plan'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card payment-card plan-option-card h-100<?php echo $selectedPlan === 'family' ? ' plan-option-card--selected' : ''; ?>">
                    <div class="card-body text-center p-4">
                        <?php if ($selectedPlan === 'family'): ?>
                            <div class="plan-selected-badge">Selected Plan</div>
                        <?php endif; ?>
                        <div class="payment-icon">
                            <i class="fas fa-users" aria-hidden="true"></i>
                        </div>
                        <h3>Family Plan</h3>
                        <h2 class="plan-price">$79<span class="small">/month</span></h2>
                        <p class="text-muted">$948/year</p>
                        <ul class="list-unstyled text-start mt-3 plan-feature-list">
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> Covers up to 4 members</li>
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> 3 cleanings each/year</li>
                            <li><i class="fas fa-check me-2" aria-hidden="true"></i> 15% off treatments</li>
                        </ul>
                        <button
                            type="button"
                            class="btn btn-subscribe-plan btn-subscribe-plan--basic w-100 mt-3"
                            onclick="selectPlan('family')"
                            <?php echo $selectedPlan === 'family' ? 'disabled aria-disabled="true"' : ''; ?>
                        >
                            <?php echo $selectedPlan === 'family' ? 'Selected' : ($selectedPlan === '' ? 'Select Plan' : 'Choose This Plan'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($showPaymentForm && $selectedPlan): ?>
        <!-- Payment Selection -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h4>Complete Payment for <?php echo ucfirst($selectedPlan); ?> Plan</h4>
                <p class="text-muted">Your selected plan is locked in below. Choose a payment method to continue.</p>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="payment-card payment-method-tile p-4" id="clinicPayment" onclick="selectPaymentMethod('clinic')">
                            <div class="text-center">
                                <i class="fas fa-building fa-3x mb-3" aria-hidden="true"></i>
                                <h5>Pay at Clinic</h5>
                                <p class="text-muted">Visit the clinic assistant to complete payment</p>
                                <small>After payment, your subscription will be activated</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="payment-card payment-method-tile p-4" id="onlinePayment" onclick="selectPaymentMethod('online')">
                            <div class="text-center">
                                <i class="fas fa-mobile-alt fa-3x mb-3" aria-hidden="true"></i>
                                <h5>Pay Online via OWO/Wish</h5>
                                <p class="text-muted">Pay using Wish/OWO application</p>
                                <small>Instant activation after payment</small>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" action="<?php echo url('api/patient_subscription.php'); ?>" data-api="<?php echo url('api/patient_subscription.php'); ?>" data-message-target="#message" id="paymentForm">
                    <input type="hidden" name="plan" value="<?php echo $selectedPlan; ?>">
                    <input type="hidden" name="action" id="paymentAction" value="">
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-subscribe-plan btn-subscribe-plan--basic btn-lg px-5" id="submitBtn" disabled>
                            Continue
                        </button>
                        <a href="subscription.php" class="btn btn-secondary btn-lg ms-3">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
let selectedMethod = '';

function selectPlan(plan) {
    window.location.href = 'subscription.php?plan=' + plan;
}

function selectPaymentMethod(method) {
    selectedMethod = method;
    
    document.getElementById('clinicPayment').classList.remove('selected');
    document.getElementById('onlinePayment').classList.remove('selected');
    document.getElementById(method + 'Payment').classList.add('selected');
    
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('paymentAction').value = method + '_payment';
}

const style = document.createElement('style');
style.textContent = `
    .subscription-page .plan-option-card {
        position: relative;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .subscription-page .plan-option-card--selected {
        border: 2px solid var(--bills-accent-deep, #6ca3f5) !important;
        box-shadow: 0 14px 34px rgba(108, 163, 245, 0.18);
        transform: translateY(-4px);
    }
    .subscription-page .plan-selected-badge {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: var(--bills-accent-deep, #6ca3f5);
        color: #fff;
        font-size: 0.75rem;
        font-weight: 700;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        letter-spacing: 0.02em;
    }
    .subscription-page .payment-card.selected {
        transform: scale(1.02);
    }
`;
document.head.appendChild(style);
</script>

<?php include '../layouts/footer.php'; ?>
