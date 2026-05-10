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

if (isset($_GET['clear_pending']) && $_GET['clear_pending'] === '1') {
    unset($_SESSION['pending_subscription']);
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
$selectedBillingCycle = $_GET['billing_cycle'] ?? 'monthly';
$selectedBillingCycle = in_array($selectedBillingCycle, ['monthly', 'yearly'], true) ? $selectedBillingCycle : 'monthly';
$availablePlans = $db->fetchAll(
    "SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY display_order, monthly_price"
);
if (!$availablePlans) {
    $availablePlans = [
        ['plan_key' => 'basic', 'plan_name' => 'Basic Plan', 'monthly_price' => 29, 'annual_price' => 348, 'features' => "2 free cleanings/year\n10% off treatments\nFree consultation"],
        ['plan_key' => 'premium', 'plan_name' => 'Premium Plan', 'monthly_price' => 49, 'annual_price' => 588, 'features' => "4 free cleanings/year\n20% off treatments\nPriority scheduling\nEmergency access"],
        ['plan_key' => 'family', 'plan_name' => 'Family Plan', 'monthly_price' => 79, 'annual_price' => 948, 'features' => "Covers up to 4 members\n3 cleanings each/year\n15% off treatments"],
    ];
}
$plansByKey = [];
foreach ($availablePlans as $planRow) {
    $plansByKey[(string) ($planRow['plan_key'] ?? '')] = $planRow;
}
$planIcons = [
    'basic' => 'fa-tooth',
    'premium' => 'fa-crown premium-crown-icon',
    'family' => 'fa-users',
];
$hasExistingSubscription = $currentPlan !== 'none' && in_array($subscriptionStatus, ['pending', 'active'], true);

if (!function_exists('subscription_normalize_plan_features')) {
    /** @return list<string> One entry per bullet line */
    function subscription_normalize_plan_features(?string $raw): array
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return [];
        }
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw))));
        if (count($lines) === 1 && strpos($lines[0], ',') !== false) {
            return array_values(array_filter(array_map('trim', explode(',', $lines[0]))));
        }

        return $lines;
    }
}

// If plan selected from index page
if ($selectedPlan && in_array($selectedPlan, ['basic', 'premium', 'family']) && !$hasExistingSubscription) {
    $showPaymentForm = true;
}

$pageTitle = 'My Subscription';
include '../layouts/header.php';
?>


<div class="container-fluid bills-page patient-portal subscription-page">
  

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
                        $statusLine = '-';
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
                        <?php
                        if ($subscriptionStatus === 'active' && $currentPlan !== 'none') {
                            $heroSd = isset($patient['subscription_start_date']) ? trim((string) $patient['subscription_start_date']) : '';
                            $heroEd = isset($patient['subscription_end_date']) ? trim((string) $patient['subscription_end_date']) : '';
                            $heroValid = '';
                            if ($heroSd !== '' && $heroEd !== '') {
                                $heroValid = 'Valid ' . formatDate($patient['subscription_start_date']) . ' – ' . formatDate($patient['subscription_end_date']);
                            } elseif ($heroEd !== '') {
                                $heroValid = 'Valid until ' . formatDate($patient['subscription_end_date']);
                            } elseif ($heroSd !== '') {
                                $heroValid = 'Valid from ' . formatDate($patient['subscription_start_date']);
                            }
                            if ($heroValid !== '') {
                                echo '<small class="d-block subscription-hero-valid">' . htmlspecialchars($heroValid) . '</small>';
                            }
                        }
                        ?>
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
            <strong>Pending payment</strong> - your subscription request is pending. Please visit the clinic assistant to complete your payment.
            <div class="mt-2">
                <small>Valid until: <?php echo formatDate($patient['subscription_end_date']); ?></small>
            </div>
        </div>
    <?php endif; ?>

    <!-- Plan Selection / View -->
    <?php if ($hasExistingSubscription): ?>
        <div class="alert alert-info py-2">
            You already have an active or pending plan. You can compare other options below; you'll be able to activate a different plan once your current subscription ends.
        </div>
    <?php endif; ?>
    <div class="row">
        <?php foreach ($availablePlans as $planRow): ?>
            <?php
            $planKey = (string) ($planRow['plan_key'] ?? '');
            if (!in_array($planKey, ['basic', 'premium', 'family'], true)) {
                continue;
            }
            $features = subscription_normalize_plan_features((string) ($planRow['features'] ?? ''));
            $isSelected = $selectedPlan === $planKey;
            $isCurrentPlan = $currentPlan === $planKey && $hasExistingSubscription;
            $planCardMods = $planKey === 'premium' ? ' border-highlight-premium' : '';
            if ($isCurrentPlan) {
                $planCardMods .= ' plan-option-card--current';
            } elseif (!$hasExistingSubscription && $isSelected) {
                $planCardMods .= ' plan-option-card--selected';
            }
            ?>
            <div class="col-md-4 mb-4">
                <div class="card payment-card plan-option-card h-100 d-flex flex-column<?php echo $planCardMods; ?>" data-plan-card="<?php echo htmlspecialchars($planKey); ?>">
                    <div class="card-body plan-option-card-body d-flex flex-column flex-grow-1 align-items-center text-center p-4">
                        <?php if (!$hasExistingSubscription && $isSelected): ?>
                            <div class="plan-selected-badge">Selected Plan</div>
                        <?php endif; ?>
                        <div class="payment-icon">
                            <i class="fas <?php echo htmlspecialchars($planIcons[$planKey] ?? 'fa-crown'); ?>" aria-hidden="true"></i>
                        </div>
                        <h3 class="plan-option-card-title h5 mb-2 fw-bold"><?php echo htmlspecialchars((string) ($planRow['plan_name'] ?? ucfirst($planKey) . ' Plan')); ?></h3>
                        <h2 class="plan-price h3 mb-1"><?php echo formatCurrency((float) $planRow['monthly_price']); ?><span class="small fw-normal">/month</span></h2>
                        <p class="text-muted small mb-3 mb-md-4"><?php echo formatCurrency((float) $planRow['annual_price']); ?>/year</p>
                        <ul class="list-unstyled plan-feature-list w-100 mb-4">
                            <?php foreach ($features as $feature): ?>
                                <li class="plan-feature-list-item">
                                    <i class="fas fa-check plan-feature-list-check flex-shrink-0" aria-hidden="true"></i>
                                    <span class="plan-feature-list-text"><?php echo htmlspecialchars($feature); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="plan-option-card-footer mt-auto w-100">
                        <?php if ($hasExistingSubscription): ?>
                            <?php if ($isCurrentPlan): ?>
                                <p class="plan-card-footer-label plan-card-footer-label--current mb-0">Current plan</p>
                            <?php else: ?>
                                <p class="plan-card-alt-hint mb-0" role="note">
                                    You'll be able to activate a different plan once your current subscription ends.
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <button
                                type="button"
                                class="btn-green plan-option-card-action-btn"
                                onclick="selectPlan('<?php echo htmlspecialchars($planKey, ENT_QUOTES); ?>')"
                                <?php echo $isSelected ? 'disabled aria-disabled="true"' : ''; ?>
                            >
                                <?php echo $isSelected ? 'Selected' : ($selectedPlan === '' ? 'Select Plan' : 'Choose This Plan'); ?>
                            </button>
                        <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($showPaymentForm && $selectedPlan): ?>
        <!-- Payment Selection -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
                <h4>Complete Payment for <?php echo ucfirst($selectedPlan); ?> Plan</h4>
                <p class="text-muted">Your selected plan is locked in below. Choose a payment method to continue.</p>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <h5 class="mb-3">Choose billing cycle</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <button type="button" class="payment-card billing-cycle-option p-4 w-100 text-start<?php echo $selectedBillingCycle === 'monthly' ? ' selected' : ''; ?>" id="monthlyBilling" onclick="selectBillingCycle('monthly')">
                                <strong class="d-block">Monthly</strong>
                                <span class="text-muted">Pay one month now. Your plan is active for one month.</span>
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button type="button" class="payment-card billing-cycle-option p-4 w-100 text-start<?php echo $selectedBillingCycle === 'yearly' ? ' selected' : ''; ?>" id="yearlyBilling" onclick="selectBillingCycle('yearly')">
                                <strong class="d-block">Yearly</strong>
                                <span class="text-muted">Pay the full year now. Your plan is active for one year.</span>
                            </button>
                        </div>
                    </div>
                </div>
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
                    <input type="hidden" name="billing_cycle" id="billingCycle" value="<?php echo htmlspecialchars($selectedBillingCycle); ?>">
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

function selectBillingCycle(cycle) {
    document.getElementById('billingCycle').value = cycle;
    document.getElementById('monthlyBilling').classList.toggle('selected', cycle === 'monthly');
    document.getElementById('yearlyBilling').classList.toggle('selected', cycle === 'yearly');
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
    .subscription-page .plan-card-footer-label--current {
        width: 100%;
        margin: 0;
        padding: 0.5rem 0 0;
        font-size: 0.8125rem;
        font-weight: 600;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: rgb(22, 163, 74);
        background: transparent none;
        border: none;
        text-align: center;
    }
    .subscription-page .plan-card-alt-hint {
        width: 100%;
        max-width: 16rem;
        margin: 0 auto;
        padding: 0.5rem 0.35rem 0;
        font-size: 0.75rem;
        font-weight: 400;
        line-height: 1.45;
        letter-spacing: 0.01em;
        color: #64748b;
        text-align: center;
    }
    .subscription-page .subscription-active-label {
        letter-spacing: 0.06em;
        font-size: 0.6875rem;
    }
    .subscription-page .subscription-active-plan-name {
        color: #0f172a;
        font-weight: 700;
    }
    .subscription-page .subscription-active-dates {
        font-size: 0.9375rem;
        color: #334155;
        line-height: 1.5;
    }
    .subscription-page .subscription-active-feature-list li {
        padding: 0.35rem 0;
        padding-left: 0.25rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        font-size: 0.9375rem;
        color: #334155;
        text-align: left;
    }
    .subscription-page .subscription-active-feature-list li:last-child {
        border-bottom: none;
    }
    .subscription-page .subscription-active-check {
        color: rgb(34, 197, 94);
        font-size: 0.85em;
    }
    .subscription-page .subscription-active-pricing {
        letter-spacing: 0.02em;
    }
    .subscription-page .subscription-active-icon-ring {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 88px;
        height: 88px;
        margin: 0 auto;
        border-radius: 50%;
        background: rgba(34, 197, 94, 0.12);
    }
    .subscription-page .subscription-active-icon {
        color: rgb(22, 163, 74);
    }
    .subscription-page .subscription-active-aside {
        padding-top: 0.25rem;
    }
    @media (max-width: 767.98px) {
        .subscription-page .subscription-active-aside {
            padding-top: 0.5rem;
        }
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
    .subscription-page .billing-cycle-option {
        border: 1px solid rgba(0,0,0,.12);
        background: #fff;
        border-radius: 8px;
    }
`;
document.head.appendChild(style);
</script>

<?php include '../layouts/footer.php'; ?>
