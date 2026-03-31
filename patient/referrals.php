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

$patient = $db->fetchOne("SELECT full_name, referral_code, points FROM patients WHERE id = ?", [$patientId], "i");

// Generate referral code if it doesn't exist
if (empty($patient['referral_code'])) {
    // Generate a unique referral code
    $newCode = strtoupper(substr(md5($patientId . uniqid()), 0, 8));
    $db->execute("UPDATE patients SET referral_code = ? WHERE id = ?", [$newCode, $patientId], "si");
    // Refresh patient data
    $patient = $db->fetchOne("SELECT full_name, referral_code, points FROM patients WHERE id = ?", [$patientId], "i");
}

// Get referred patients
$referred = $db->fetchAll(
    "SELECT full_name, created_at, email, phone FROM patients WHERE referred_by = ? ORDER BY created_at DESC",
    [$patientId],
    "i"
);

$referralCount = count($referred);
$pointsEarned = $referralCount * 50;

$pageTitle = 'My Referrals';
include '../layouts/header.php';
?>

<style>
.referral-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
}

.referral-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    height: 100%;
}

.referral-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.referral-number {
    font-size: 48px;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 10px;
}

.code-container {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    border: 2px solid #667eea;
    margin-bottom: 20px;
}

.referral-code {
    font-size: 32px;
    font-weight: bold;
    letter-spacing: 3px;
    color: #667eea;
    font-family: monospace;
    background: white;
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 15px;
    border: 2px solid #e0e0e0;
}

.btn-copy {
    background: #28a745;
    border: none;
    padding: 12px 25px;
    border-radius: 25px;
    color: white;
    font-weight: bold;
    font-size: 16px;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-copy:hover {
    background: #218838;
    transform: scale(1.02);
}

.btn-whatsapp {
    background: #25D366;
    border: none;
    padding: 12px 25px;
    border-radius: 25px;
    color: white;
    font-weight: bold;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-whatsapp:hover {
    background: #128C7E;
    transform: scale(1.02);
}

.referred-item {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s ease;
}

.referred-item:hover {
    background: #f8f9fa;
}

.referred-avatar {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 18px;
}

.success-message {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    background: #28a745;
    color: white;
    padding: 15px 30px;
    border-radius: 50px;
    z-index: 9999;
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateX(-50%) translateY(50px);
    }
    to {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
}

.input-group-custom {
    display: flex;
    margin-top: 10px;
}

.input-group-custom input {
    flex: 1;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px 0 0 8px;
    font-family: monospace;
    font-size: 12px;
}

.input-group-custom button {
    padding: 10px 15px;
    background: #667eea;
    color: white;
    border: none;
    border-radius: 0 8px 8px 0;
    cursor: pointer;
}

.input-group-custom button:hover {
    background: #5a67d8;
}
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Referral Header -->
    <div class="referral-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="fas fa-share-alt"></i> My Referrals
                </h2>
                <p class="mb-0">Share your unique code and earn points for every friend who joins!</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white text-dark rounded p-2">
                    <small>Total Points Earned</small>
                    <h3 class="mb-0 text-success">+<?php echo $pointsEarned; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="referral-card">
                <div class="referral-number"><?php echo $referralCount; ?></div>
                <div class="points-label">People Referred</div>
                <small class="text-muted">Earn 50 points each</small>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="referral-card">
                <div class="referral-number"><?php echo $pointsEarned; ?></div>
                <div class="points-label">Points Earned</div>
                <small class="text-muted">From referrals only</small>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="referral-card">
                <div class="referral-number"><?php echo floor($pointsEarned / 250); ?></div>
                <div class="points-label">Rewards Unlocked</div>
                <small class="text-muted">250 points per reward</small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <!-- Share Your Code -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-code"></i> Share Your Referral Code
                    </h5>
                </div>
                <div class="card-body">
                    <div class="code-container">
                        <div class="referral-code" id="referralCodeText">
                            <?php echo $patient['referral_code']; ?>
                        </div>
                        <button class="btn-copy" id="copyCodeBtn">
                            <i class="fas fa-copy"></i> Copy Code
                        </button>
                        <div class="input-group-custom mt-3">
                            <input type="text" id="referralLink" readonly 
                                   value="<?php echo url('register.php?ref=' . $patient['referral_code']); ?>">
                            <button id="copyLinkBtn">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                        <small class="text-muted d-block mt-3">
                            <i class="fas fa-info-circle"></i> Your referral code: <strong><?php echo $patient['referral_code']; ?></strong>
                        </small>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button class="btn-whatsapp" id="whatsappBtn">
                            <i class="fab fa-whatsapp"></i> Share via WhatsApp
                        </button>
                    </div>
                </div>
            </div>

            <!-- How It Works -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle"></i> How It Works
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">1</div>
                        </div>
                        <div>
                            <strong>Share Your Code</strong>
                            <p class="small text-muted mb-0">Share your unique referral code with friends and family</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">2</div>
                        </div>
                        <div>
                            <strong>Friend Signs Up</strong>
                            <p class="small text-muted mb-0">They use your code when creating their account</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">3</div>
                        </div>
                        <div>
                            <strong>Earn Points</strong>
                            <p class="small text-muted mb-0">You get 50 points, they get 50 bonus points on first visit!</p>
                        </div>
                    </div>
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-gift"></i> <strong>No limits!</strong> Refer as many friends as you want!
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <!-- Referred Friends List -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-users"></i> Your Referred Friends
                    </h5>
                    <span class="badge bg-primary"><?php echo $referralCount; ?> Total</span>
                </div>
                <div class="card-body">
                    <?php if (empty($referred)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-plus fa-4x text-muted mb-3"></i>
                            <p class="text-muted">You haven't referred anyone yet.</p>
                            <p>Share your referral code to earn 50 points per signup!</p>
                            <button class="btn-copy mt-2" id="emptyCopyBtn">
                                <i class="fas fa-copy"></i> Copy Your Code
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="referred-list">
                            <?php foreach ($referred as $ref): ?>
                            <div class="referred-item d-flex align-items-center">
                                <div class="referred-avatar me-3">
                                    <?php echo strtoupper(substr($ref['full_name'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($ref['full_name']); ?></h6>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar"></i> Joined <?php echo formatDate($ref['created_at']); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-success">
                                        <i class="fas fa-star"></i> +50 pts
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-chart-line"></i> 
                            <strong><?php echo $referralCount; ?> referral<?php echo $referralCount > 1 ? 's' : ''; ?></strong> earned you 
                            <strong><?php echo $pointsEarned; ?> points</strong>!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Get the referral code from PHP
const referralCode = '<?php echo $patient['referral_code']; ?>';
const fullLink = '<?php echo url('register.php?ref=' . $patient['referral_code']); ?>';

// Debug - check if code exists
console.log('Referral Code:', referralCode);
console.log('Full Link:', fullLink);

// Function to show success message
function showMessage(message) {
    const existing = document.querySelector('.success-message');
    if (existing) existing.remove();
    
    const msg = document.createElement('div');
    msg.className = 'success-message';
    msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
    document.body.appendChild(msg);
    
    setTimeout(() => {
        msg.remove();
    }, 2000);
}

// Simple copy function
function copyToClipboard(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = '-9999px';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    
    textarea.select();
    textarea.setSelectionRange(0, 99999);
    
    let success = false;
    try {
        success = document.execCommand('copy');
    } catch (err) {
        console.error('Copy failed:', err);
    }
    
    document.body.removeChild(textarea);
    return success;
}

// Copy code function
function copyCode() {
    if (!referralCode || referralCode === '') {
        alert('Error: Referral code not found. Please refresh the page.');
        return;
    }
    
    const success = copyToClipboard(referralCode);
    if (success) {
        showMessage('Referral code copied: ' + referralCode);
    } else {
        alert('Please copy manually: ' + referralCode);
    }
}

// Share via WhatsApp
function shareWhatsApp() {
    if (!referralCode || referralCode === '') {
        alert('Error: Referral code not found. Please refresh the page.');
        return;
    }
    
    const text = `Join me at Dental Clinic! Use my referral code: ${referralCode} to get 50 bonus points on your first visit!`;
    const url = `https://wa.me/?text=${encodeURIComponent(text)}`;
    console.log('WhatsApp URL:', url);
    window.open(url, '_blank');
}

// Add event listeners when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Check if code exists
    if (!referralCode || referralCode === '') {
        console.error('Referral code is empty!');
        const codeElement = document.getElementById('referralCodeText');
        if (codeElement) {
            codeElement.style.color = 'red';
            codeElement.innerHTML = 'ERROR: No referral code. Please contact support.';
        }
    } else {
        console.log('Referral code loaded successfully:', referralCode);
    }
    
    // Copy code buttons
    const copyBtns = document.querySelectorAll('#copyCodeBtn, #emptyCopyBtn');
    copyBtns.forEach(btn => {
        if (btn) {
            btn.addEventListener('click', copyCode);
        }
    });
    
    // WhatsApp button
    const whatsappBtn = document.getElementById('whatsappBtn');
    if (whatsappBtn) {
        whatsappBtn.addEventListener('click', shareWhatsApp);
    }
});
</script>

<?php include '../layouts/footer.php'; ?>