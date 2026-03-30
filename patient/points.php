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
$patient = $db->fetchOne("SELECT points, full_name FROM patients WHERE id = ?", [$patientId], "i");

$points = $patient['points'] ?? 0;

// Get referral count
$referralCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM patients WHERE referred_by = ?",
    [$patientId],
    "i"
)['count'];

// Get points history from appointments and referrals
$appointmentPoints = $db->fetchAll(
    "SELECT appointment_date, treatment_type, 'appointment' as source FROM appointments WHERE patient_id = ? AND status = 'completed' ORDER BY appointment_date DESC LIMIT 10",
    [$patientId],
    "i"
);

$pageTitle = 'My Points';
include '../layouts/header.php';
?>

<style>
.points-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
}

.points-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    height: 100%;
}

.points-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.points-number {
    font-size: 48px;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 10px;
}

.points-label {
    font-size: 14px;
    color: #6c757d;
}

.points-progress {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    height: 10px;
    overflow: hidden;
}

.points-progress-bar {
    background: #ffc107;
    height: 100%;
    border-radius: 10px;
    transition: width 0.5s ease;
}

.earning-item {
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s ease;
}

.earning-item:hover {
    background: #f8f9fa;
    transform: translateX(5px);
}

.earning-icon {
    width: 40px;
    height: 40px;
    background: #f8f9fa;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
}

.points-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.points-earned { background: #d4edda; color: #155724; }
.points-spent { background: #f8d7da; color: #721c24; }

.reward-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.reward-card:hover {
    transform: scale(1.02);
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
}

.reward-points {
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
}

.btn-redeem {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 8px 20px;
    border-radius: 20px;
    color: white;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-redeem:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102,126,234,0.4);
}
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Points Header -->
    <div class="points-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="fas fa-star"></i> My Reward Points
                </h2>
                <p class="mb-0">Earn points with every visit and referral. Redeem them for exciting rewards!</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="points-progress mb-2">
                    <div class="points-progress-bar" style="width: <?php echo ($points % 250) / 250 * 100; ?>%"></div>
                </div>
                <small><?php echo 250 - ($points % 250); ?> points to next reward</small>
            </div>
        </div>
    </div>

    <!-- Points Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="points-card">
                <div class="points-number"><?php echo $points; ?></div>
                <div class="points-label">Total Points</div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="points-card">
                <div class="points-number"><?php echo floor($points / 250); ?></div>
                <div class="points-label">Rewards Available</div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="points-card">
                <div class="points-number"><?php echo $referralCount * 50; ?></div>
                <div class="points-label">Points from Referrals</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-7">
            <!-- How to Earn Points -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line text-success"></i> How to Earn Points
                    </h5>
                </div>
                <div class="card-body">
                    <div class="earning-item d-flex align-items-center">
                        <div class="earning-icon">
                            <i class="fas fa-calendar-check text-primary fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">Complete an Appointment</h6>
                            <small class="text-muted">Earn points for every completed dental visit</small>
                        </div>
                        <div class="points-badge points-earned">+50 points</div>
                    </div>
                    <div class="earning-item d-flex align-items-center">
                        <div class="earning-icon">
                            <i class="fas fa-users text-success fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">Refer a Friend</h6>
                            <small class="text-muted">Share your referral code and earn points</small>
                        </div>
                        <div class="points-badge points-earned">+50 points</div>
                    </div>
                    <div class="earning-item d-flex align-items-center">
                        <div class="earning-icon">
                            <i class="fas fa-gem text-warning fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">Subscribe to Premium Plan</h6>
                            <small class="text-muted">One-time bonus for upgrading</small>
                        </div>
                        <div class="points-badge points-earned">+200 points</div>
                    </div>
                    <div class="earning-item d-flex align-items-center">
                        <div class="earning-icon">
                            <i class="fas fa-birthday-cake text-info fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">First Appointment Bonus</h6>
                            <small class="text-muted">Welcome bonus for new patients</small>
                        </div>
                        <div class="points-badge points-earned">+100 points</div>
                    </div>
                </div>
            </div>

            <!-- Points History -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history text-info"></i> Points History
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($points == 0 && $referralCount == 0 && empty($appointmentPoints)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No points earned yet.</p>
                            <a href="book.php" class="btn btn-primary">Book Your First Appointment</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th class="text-end">Points</th>
                                    </thead>
                                <tbody>
                                    <?php if ($points > 0 && $points == ($referralCount * 50)): ?>
                                    <tr>
                                        <td>Ongoing</td>
                                        <td>Current balance from referrals</td>
                                        <td class="text-end text-success">+<?php echo $points; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php foreach ($appointmentPoints as $apt): ?>
                                    <tr>
                                        <td><?php echo formatDate($apt['appointment_date']); ?></td>
                                        <td>Completed: <?php echo $apt['treatment_type']; ?></td>
                                        <td class="text-end text-success">+50</td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if ($referralCount > 0): ?>
                                    <tr>
                                        <td>From referrals</td>
                                        <td><?php echo $referralCount; ?> friend<?php echo $referralCount > 1 ? 's' : ''; ?> joined</td>
                                        <td class="text-end text-success">+<?php echo $referralCount * 50; ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                             </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <!-- Available Rewards -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-gift text-warning"></i> Available Rewards
                    </h5>
                </div>
                <div class="card-body">
                    <div class="reward-card" onclick="alert('Contact clinic to redeem this reward')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Free Teeth Whitening</h6>
                                <small class="text-muted">Professional whitening session</small>
                            </div>
                            <div class="text-end">
                                <div class="reward-points">500 points</div>
                                <button class="btn-redeem btn-sm mt-1">Redeem</button>
                            </div>
                        </div>
                    </div>
                    <div class="reward-card" onclick="alert('Contact clinic to redeem this reward')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Free Dental Cleaning</h6>
                                <small class="text-muted">One free cleaning session</small>
                            </div>
                            <div class="text-end">
                                <div class="reward-points">250 points</div>
                                <button class="btn-redeem btn-sm mt-1">Redeem</button>
                            </div>
                        </div>
                    </div>
                    <div class="reward-card" onclick="alert('Contact clinic to redeem this reward')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">$50 Treatment Discount</h6>
                                <small class="text-muted">Off any dental treatment</small>
                            </div>
                            <div class="text-end">
                                <div class="reward-points">300 points</div>
                                <button class="btn-redeem btn-sm mt-1">Redeem</button>
                            </div>
                        </div>
                    </div>
                    <div class="reward-card" onclick="alert('Contact clinic to redeem this reward')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">Dental Care Kit</h6>
                                <small class="text-muted">Premium toothbrush, toothpaste, floss</small>
                            </div>
                            <div class="text-end">
                                <div class="reward-points">150 points</div>
                                <button class="btn-redeem btn-sm mt-1">Redeem</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tips Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lightbulb text-warning"></i> Pro Tips
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-3">
                        <i class="fas fa-share-alt text-primary fa-lg me-3"></i>
                        <div>
                            <strong>Share your referral code</strong>
                            <p class="small text-muted mb-0">Earn 50 points for each friend who joins!</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fas fa-calendar-check text-success fa-lg me-3"></i>
                        <div>
                            <strong>Don't miss appointments</strong>
                            <p class="small text-muted mb-0">Complete all scheduled appointments to earn points</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <i class="fas fa-crown text-warning fa-lg me-3"></i>
                        <div>
                            <strong>Upgrade to Premium</strong>
                            <p class="small text-muted mb-0">Get 200 bonus points and more benefits!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>