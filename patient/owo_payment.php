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

// Get pending subscription from session
if (!isset($_SESSION['pending_subscription'])) {
    header('Location: subscription.php');
    exit;
}

$subscription = $_SESSION['pending_subscription'];
$patient = $db->fetchOne("SELECT full_name, phone, email FROM patients WHERE id = ?", [$patientId], "i");

// Clinic OWO/Wish number
$CLINIC_OWO_NUMBER = "1234567890"; // Replace with actual clinic OWO number

$pageTitle = 'Online Payment';
include '../layouts/header.php';
?>


<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="subscription.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Subscription
        </a>
    </div>

    <div class="payment-header">
        <h2 class="mb-2">
            <i class="fas fa-mobile-alt"></i> Pay via Wish/OWO
        </h2>
        <p class="mb-0">Complete your subscription payment securely through Wish/OWO</p>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="owo-instructions">
                <h4 class="mb-3">
                    <i class="fas fa-info-circle text-primary"></i> Payment Instructions
                </h4>
                
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="step-circle">1</div>
                        <div>
                            <strong>Open Wish/OWO App</strong>
                            <p class="text-muted mb-0">Open the Wish/OWO application on your phone</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="step-circle">2</div>
                        <div>
                            <strong>Select "Send Money"</strong>
                            <p class="text-muted mb-0">Choose the send money option</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="step-circle">3</div>
                        <div>
                            <strong>Enter Clinic Number</strong>
                            <p class="text-muted mb-0">Use the clinic's OWO number below</p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="step-circle">4</div>
                        <div>
                            <strong>Enter Amount</strong>
                            <p class="text-muted mb-0">Amount: <?php echo formatCurrency($subscription['amount']); ?></p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center mb-3">
                        <div class="step-circle">5</div>
                        <div>
                            <strong>Add Reference</strong>
                            <p class="text-muted mb-0">Reference: <?php echo 'SUB-' . $patientId . '-' . time(); ?></p>
                        </div>
                    </div>
                    
                    <div class="d-flex align-items-center">
                        <div class="step-circle">6</div>
                        <div>
                            <strong>Confirm Payment</strong>
                            <p class="text-muted mb-0">Confirm and complete the transaction</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5>Payment Details</h5>
                    <hr>
                    <p><strong>Plan:</strong> <?php echo ucfirst($subscription['plan']); ?> Plan</p>
                    <p><strong>Amount:</strong> <?php echo formatCurrency($subscription['amount']); ?></p>
                    <p><strong>Patient:</strong> <?php echo $patient['full_name']; ?></p>
                    
                    <div class="clinic-number">
                        <i class="fas fa-building"></i> Clinic OWO Number:<br>
                        <span style="font-size: 32px;"><?php echo $CLINIC_OWO_NUMBER; ?></span>
                    </div>
                    
                    <button class="btn-owo w-100" onclick="openOWO()">
                        <i class="fab fa-whatsapp"></i> Pay via Wish/OWO
                    </button>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i>
                        <small>After payment, please click "I've Paid" to activate your subscription instantly.</small>
                    </div>
                    
                    <button class="btn btn-success w-100 mt-2" onclick="confirmPayment()">
                        <i class="fas fa-check-circle"></i> I've Paid
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const clinicNumber = '<?php echo $CLINIC_OWO_NUMBER; ?>';
const amount = '<?php echo $subscription['amount']; ?>';
const reference = 'SUB-<?php echo $patientId . '-' . time(); ?>';

function openOWO() {
    // Open Wish/OWO app with clinic number and amount
    // This is the OWO deep link
    const owoUrl = `owo://send?number=${clinicNumber}&amount=${amount}&note=${reference}`;
    window.location.href = owoUrl;
    
    // Fallback to WhatsApp if OWO app not installed
    setTimeout(() => {
        const whatsappUrl = `https://wa.me/${clinicNumber}?text=I'm%20making%20a%20payment%20for%20subscription%0AReference:%20${reference}%0AAmount:%20${amount}`;
        window.open(whatsappUrl, '_blank');
    }, 1000);
}

function confirmPayment() {
    if (confirm('Have you completed the payment? Click OK to activate your subscription.')) {
        // Submit payment confirmation
        fetch('confirm_payment.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                plan: '<?php echo $subscription['plan']; ?>',
                amount: <?php echo $subscription['amount']; ?>,
                reference: reference,
                payment_method: 'owo'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Payment confirmed! Your subscription is now active.');
                window.location.href = 'subscription.php';
            } else {
                alert('Error confirming payment. Please contact support.');
            }
        });
    }
}
</script>

<?php include '../layouts/footer.php'; ?>
