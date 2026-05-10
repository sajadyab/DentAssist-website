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

// Get pending subscription from session
if (!isset($_SESSION['pending_subscription'])) {
    header('Location: subscription.php');
    exit;
}

$subscription = $_SESSION['pending_subscription'];
$billingCycle = (string) ($subscription['billing_cycle'] ?? 'monthly');
$billingLabel = $billingCycle === 'yearly' ? 'Yearly' : 'Monthly';
$patient = patient_portal_fetch_patient_cloud_first((int) $patientId);
if (!$patient) {
    die("Patient record not found.");
}

// Clinic OWO/Wish number
$db = Database::getInstance();
$result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'clinic_phone'");
$CLINIC_OWO_NUMBER = $result ? $result['setting_value'] : '(555) 123-4567';
$paymentReference = 'SUB-' . $patientId . '-' . time();
$owoHref = 'https://www.whish.money/';

$pageTitle = 'Online Payment';
include '../layouts/header.php';
?>


<div class="container-fluid bills-page patient-portal owo-payment-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-md-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-mobile-alt me-2 opacity-90" aria-hidden="true"></i> Pay via Wish/OWO
                </h2>
                <p class="mb-0 opacity-90">Complete your subscription payment securely through Wish/OWO.</p>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="bills-balance-wrap">
                    <div class="bills-balance-box">
                        <small>Payment summary</small>
                        <p class="bills-balance-amount"><?php echo formatCurrency($subscription['amount']); ?></p>
                        <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;">
                            <?php echo htmlspecialchars($billingLabel); ?> billing · <?php echo ucfirst($subscription['plan']); ?> plan
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2" aria-hidden="true"></i>Payment Instructions</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex align-items-start mb-3">
                            <div class="step-circle">1</div>
                            <div>
                                <span class="bills-dash-strong">Open Wish/OWO App</span>
                                <span class="bills-dash-muted">Open the Wish/OWO application on your phone.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-3">
                            <div class="step-circle">2</div>
                            <div>
                                <span class="bills-dash-strong">Select "Send Money"</span>
                                <span class="bills-dash-muted">Choose the send money option.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-3">
                            <div class="step-circle">3</div>
                            <div>
                                <span class="bills-dash-strong">Enter Clinic Number</span>
                                <span class="bills-dash-muted">Use the clinic's OWO number shown in the right panel.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start mb-3">
                            <div class="step-circle">4</div>
                            <div>
                                <span class="bills-dash-strong">Enter Amount</span>
                                <span class="bills-dash-muted">Amount: <?php echo formatCurrency($subscription['amount']); ?></span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start">
                            <div class="step-circle">5</div>
                            <div>
                                <span class="bills-dash-strong">Confirm Payment</span>
                                <span class="bills-dash-muted">Confirm and complete the transaction.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card bills-dash-section-card h-100">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-credit-card me-2" aria-hidden="true"></i>Payment Details</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="bills-dash-row">
                        <span class="bills-side-id">Plan</span>
                        <div class="bills-dash-col-main">
                            <span class="bills-dash-strong"><?php echo ucfirst($subscription['plan']); ?> Plan</span>
                        </div>
                        <div class="bills-dash-actions">
                            <span class="bills-dash-balance"><?php echo htmlspecialchars($billingLabel); ?></span>
                        </div>
                    </div>
                    <div class="bills-dash-row">
                        <span class="bills-side-id">Amount</span>
                        <div class="bills-dash-col-main">
                            <span class="bills-dash-strong"><?php echo formatCurrency($subscription['amount']); ?></span>
                        </div>
                    </div>
                    <div class="bills-dash-row">
                        <span class="bills-side-id">Patient</span>
                        <div class="bills-dash-col-main">
                            <span class="bills-dash-strong"><?php echo htmlspecialchars($patient['full_name']); ?></span>
                        </div>
                    </div>
                    <div class="bills-dash-row">
                        <span class="bills-side-id">Clinic Number</span>
                        <div class="bills-dash-col-main">
                            <span class="bills-dash-strong"><?php echo htmlspecialchars($CLINIC_OWO_NUMBER); ?></span>
                        </div>
                    </div>
                    <a class="btn btn-primary w-100 mb-3" href="<?php echo htmlspecialchars($owoHref); ?>" target="_blank">
                        <i class="fab fa-whatsapp me-2"></i> Pay via Wish/OWO
                    </a>
                    <div class="alert alert-info mt-0 mb-3">
                        <i class="fas fa-info-circle"></i>
                        <small>After payment, click Continue to return to your subscription page. Your subscription will remain pending until the clinic confirms receipt.</small>
                    </div>
                    <button class="btn btn-success w-100" onclick="continueToSubscription()">
                        <i class="fas fa-arrow-right me-2"></i> Continue
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const clinicNumber = '<?php echo addslashes($CLINIC_OWO_NUMBER); ?>';
const amount = '<?php echo addslashes((string) $subscription['amount']); ?>';
const reference = '<?php echo addslashes($paymentReference); ?>';
const owoUrl = '<?php echo addslashes($owoHref); ?>';

function handleOwoClick(event) {
    event.preventDefault();
    openOWO();
}

function openOWO() {
    window.location.href = owoUrl;

    // Fallback to WhatsApp if OWO app is not installed.
    setTimeout(() => {
        const whatsappUrl = `https://wa.me/${encodeURIComponent(clinicNumber)}?text=${encodeURIComponent("I'm making a payment for subscription\nReference: " + reference + "\nAmount: " + amount)}`;
        window.open(whatsappUrl, '_blank');
    }, 1000);
}

function continueToSubscription() {
    window.location.href = 'subscription.php?success=1&clear_pending=1';
}
</script>

<?php include '../layouts/footer.php'; ?>
