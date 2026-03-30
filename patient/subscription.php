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

// Get patient current subscription
$patient = $db->fetchOne("SELECT subscription_type, subscription_start_date, subscription_end_date, subscription_status, referral_code FROM patients WHERE id = ?", [$patientId], "i");

// Generate referral code if not exists
if (empty($patient['referral_code'])) {
    $newCode = strtoupper(substr(md5($patientId . uniqid()), 0, 8));
    $db->execute("UPDATE patients SET referral_code = ? WHERE id = ?", [$newCode, $patientId], "si");
    $patient = $db->fetchOne("SELECT subscription_type, subscription_start_date, subscription_end_date, subscription_status, referral_code FROM patients WHERE id = ?", [$patientId], "i");
}

$currentPlan = $patient['subscription_type'] ?? 'none';
$subscriptionStatus = $patient['subscription_status'] ?? 'none';
$error = '';
$success = '';
$showPaymentForm = false;
$selectedPlan = $_GET['plan'] ?? '';

// If plan selected from index page
if ($selectedPlan && in_array($selectedPlan, ['basic', 'premium', 'family']) && $currentPlan == 'none') {
    $showPaymentForm = true;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'clinic_payment') {
        // Clinic payment - create pending subscription
        $newPlan = $_POST['plan'];
        $prices = ['basic' => 29, 'premium' => 49, 'family' => 79];
        $amount = $prices[$newPlan];
        $annualAmount = $amount * 12;
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+1 year'));
        
        try {
            // Update patient subscription as pending
            $db->execute(
                "UPDATE patients SET subscription_type = ?, subscription_start_date = ?, subscription_end_date = ?, subscription_status = 'pending' WHERE id = ?",
                [$newPlan, $startDate, $endDate, $patientId],
                "sssi"
            );
            
            // Create invoice for subscription
            $invoiceNumber = generateInvoiceNumber();
            $invoiceId = $db->insert(
                "INSERT INTO invoices (patient_id, invoice_number, subtotal, total_amount, payment_status, invoice_date, due_date, notes, created_by) 
                 VALUES (?, ?, ?, ?, 'pending', ?, DATE_ADD(?, INTERVAL 7 DAY), ?, ?)",
                [$patientId, $invoiceNumber, $annualAmount, $annualAmount, $startDate, $startDate, "Subscription: {$newPlan} plan (Annual) - Pending Payment", $userId],
                "isddsssi"
            );
            
            // Record subscription payment request
            $db->insert(
                "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_date, status, processed_by, notes) 
                 VALUES (?, ?, ?, 'clinic', NOW(), 'pending', ?, 'Pending payment at clinic - Please visit assistant')",
                [$patientId, $newPlan, $annualAmount, $userId],
                "isdi"
            );
            
            $success = "Subscription request created! Please visit the clinic assistant to complete payment. Your subscription will activate after payment confirmation.";
            $showPaymentForm = false;
            $currentPlan = $newPlan;
            $subscriptionStatus = 'pending';
            
        } catch (Exception $e) {
            $error = 'Error processing subscription: ' . $e->getMessage();
        }
        
    } elseif ($action == 'online_payment') {
        // Online payment via OWO/Wish
        $newPlan = $_POST['plan'];
        $prices = ['basic' => 29, 'premium' => 49, 'family' => 79];
        $amount = $prices[$newPlan];
        $annualAmount = $amount * 12;
        
        // Store payment details in session for OWO/Wish
        $_SESSION['pending_subscription'] = [
            'plan' => $newPlan,
            'amount' => $annualAmount,
            'patient_id' => $patientId,
            'user_id' => $userId
        ];
        
        // Redirect to OWO/Wish payment page
        header('Location: owo_payment.php');
        exit;
    }
}

$pageTitle = 'My Subscription';
include '../layouts/header.php';
?>

<style>
.subscription-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
}

.status-badge {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status-active { background: #28a745; color: white; }
.status-pending { background: #ffc107; color: #212529; }
.status-expired { background: #dc3545; color: white; }
.status-none { background: #6c757d; color: white; }

.payment-card {
    border-radius: 15px;
    border: none;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    cursor: pointer;
}

.payment-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.payment-card.selected {
    border: 2px solid #28a745;
    background: #f8f9fa;
}

.payment-icon {
    font-size: 48px;
    margin-bottom: 15px;
}

.alert-pending {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 20px;
}
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Header -->
    <div class="subscription-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="fas fa-crown"></i> My Subscription
                </h2>
                <p class="mb-0">Choose a plan that fits your needs and get exclusive benefits</p>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if ($currentPlan != 'none'): ?>
                    <div class="status-badge status-<?php echo $subscriptionStatus == 'active' ? 'active' : ($subscriptionStatus == 'pending' ? 'pending' : 'none'); ?>">
                        <?php echo ucfirst($subscriptionStatus == 'active' ? 'Active' : ($subscriptionStatus == 'pending' ? 'Pending Payment' : 'No Subscription')); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if ($currentPlan != 'none' && $subscriptionStatus == 'pending'): ?>
        <!-- Pending Payment Alert -->
        <div class="alert-pending">
            <i class="fas fa-clock"></i>
            <strong>Pending Payment!</strong> Your subscription request is pending. Please visit the clinic assistant to complete your payment.
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
                        <i class="fas fa-check-circle fa-4x text-success"></i>
                        <p class="mt-2">Your subscription is active</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($currentPlan == 'none' && !$showPaymentForm): ?>
        <!-- Plan Selection -->
        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card payment-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="payment-icon">
                            <i class="fas fa-tooth"></i>
                        </div>
                        <h3>Basic Plan</h3>
                        <h2 class="text-primary">$29<span class="small">/month</span></h2>
                        <p class="text-muted">$348/year</p>
                        <ul class="list-unstyled text-start mt-3">
                            <li><i class="fas fa-check text-success"></i> 2 free cleanings/year</li>
                            <li><i class="fas fa-check text-success"></i> 10% off treatments</li>
                            <li><i class="fas fa-check text-success"></i> Free consultation</li>
                        </ul>
                        <button class="btn btn-primary w-100 mt-3" onclick="selectPlan('basic')">Select Plan</button>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card payment-card h-100 border-warning">
                    <div class="card-body text-center p-4">
                        <div class="payment-icon">
                            <i class="fas fa-crown text-warning"></i>
                        </div>
                        <h3>Premium Plan</h3>
                        <h2 class="text-primary">$49<span class="small">/month</span></h2>
                        <p class="text-muted">$588/year</p>
                        <ul class="list-unstyled text-start mt-3">
                            <li><i class="fas fa-check text-success"></i> 4 free cleanings/year</li>
                            <li><i class="fas fa-check text-success"></i> 20% off treatments</li>
                            <li><i class="fas fa-check text-success"></i> Priority scheduling</li>
                            <li><i class="fas fa-check text-success"></i> Emergency access</li>
                        </ul>
                        <button class="btn btn-warning w-100 mt-3" onclick="selectPlan('premium')">Select Plan</button>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card payment-card h-100">
                    <div class="card-body text-center p-4">
                        <div class="payment-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>Family Plan</h3>
                        <h2 class="text-primary">$79<span class="small">/month</span></h2>
                        <p class="text-muted">$948/year</p>
                        <ul class="list-unstyled text-start mt-3">
                            <li><i class="fas fa-check text-success"></i> Covers up to 4 members</li>
                            <li><i class="fas fa-check text-success"></i> 3 cleanings each/year</li>
                            <li><i class="fas fa-check text-success"></i> 15% off treatments</li>
                        </ul>
                        <button class="btn btn-primary w-100 mt-3" onclick="selectPlan('family')">Select Plan</button>
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
                <p class="text-muted">Choose your payment method</p>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="payment-card p-4" id="clinicPayment" onclick="selectPaymentMethod('clinic')">
                            <div class="text-center">
                                <i class="fas fa-building fa-3x text-primary mb-3"></i>
                                <h5>Pay at Clinic</h5>
                                <p class="text-muted">Visit the clinic assistant to complete payment</p>
                                <small>After payment, your subscription will be activated</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="payment-card p-4" id="onlinePayment" onclick="selectPaymentMethod('online')">
                            <div class="text-center">
                                <i class="fas fa-mobile-alt fa-3x text-success mb-3"></i>
                                <h5>Pay Online via OWO/Wish</h5>
                                <p class="text-muted">Pay using Wish/OWO application</p>
                                <small>Instant activation after payment</small>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" id="paymentForm">
                    <input type="hidden" name="plan" value="<?php echo $selectedPlan; ?>">
                    <input type="hidden" name="action" id="paymentAction" value="">
                    <div class="text-center mt-4">
                        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitBtn" disabled>
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
    .payment-card.selected {
        border: 3px solid #28a745;
        background: #f0f9f0;
        transform: scale(1.02);
    }
`;
document.head.appendChild(style);
</script>

<?php include '../layouts/footer.php'; ?>