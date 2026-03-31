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
    die("Patient record not found. Please contact support.");
}

// Get patient details
$patient = $db->fetchOne("SELECT * FROM patients WHERE id = ?", [$patientId], "i");

// Get next appointment
$nextAppointment = $db->fetchOne(
    "SELECT a.*, u.full_name as doctor_name 
     FROM appointments a
     JOIN users u ON a.doctor_id = u.id
     WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('scheduled', 'checked-in')
     ORDER BY a.appointment_date, a.appointment_time
     LIMIT 1",
    [$patientId],
    "i"
);

// Get total visits (completed appointments)
$totalVisits = $db->fetchOne(
    "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'completed'",
    [$patientId],
    "i"
)['count'];

// Get recent appointments (last 5)
$recentAppointments = $db->fetchAll(
    "SELECT a.*, u.full_name as doctor_name 
     FROM appointments a
     JOIN users u ON a.doctor_id = u.id
     WHERE a.patient_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC
     LIMIT 5",
    [$patientId],
    "i"
);

// Get referral count (patients referred by this patient)
$referralCount = $db->fetchOne(
    "SELECT COUNT(*) as count FROM patients WHERE referred_by = ?",
    [$patientId],
    "i"
)['count'];

// Points and subscription
$points = $patient['points'] ?? 0;
$subscription = $patient['subscription_type'] ?? 'none';
$subscriptionStart = $patient['subscription_start_date'];
$subscriptionEnd = $patient['subscription_end_date'];

$pageTitle = 'My Portal';
include '../layouts/header.php';
?>

<style>
/* CSS as before (unchanged) */
.dashboard-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border-radius: 15px;
    overflow: hidden;
    cursor: pointer;
    height: 100%;
}
.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}
.welcome-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    color: white;
    padding: 25px;
    margin-bottom: 30px;
}
.stats-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}
.stats-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.stats-number {
    font-size: 36px;
    font-weight: bold;
    color: #667eea;
    margin-bottom: 5px;
}
.stats-label {
    font-size: 14px;
    color: #6c757d;
    margin-bottom: 0;
}
.quick-action-btn {
    background: white;
    border-radius: 12px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    display: block;
}
.quick-action-btn:hover {
    background: #f8f9fa;
    transform: translateY(-2px);
    text-decoration: none;
}
.quick-action-icon {
    font-size: 32px;
    margin-bottom: 10px;
    display: inline-block;
}
.appointment-card {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 15px;
    padding: 20px;
}
.appointment-time {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
}
.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}
.status-scheduled { background: #17a2b8; color: white; }
.status-completed { background: #28a745; color: white; }
.status-cancelled { background: #dc3545; color: white; }
.status-checked-in { background: #ffc107; color: #212529; }
.referral-code-box {
    background: rgba(255,255,255,0.2);
    border-radius: 10px;
    padding: 8px 15px;
    display: inline-block;
    cursor: pointer;
    transition: all 0.3s ease;
}
.referral-code-box:hover {
    background: rgba(255,255,255,0.3);
}
.points-circle {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 10px;
}
.points-circle h2 {
    font-size: 28px;
    margin: 0;
    font-weight: bold;
}
.subscription-plan-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s ease;
}
.subscription-plan-card:hover {
    transform: scale(1.02);
}
.plan-feature {
    padding: 5px 0;
    font-size: 13px;
}
.plan-price {
    font-size: 24px;
    font-weight: bold;
    color: #667eea;
}
.table-modern {
    border-radius: 15px;
    overflow: hidden;
}
.table-modern thead {
    background: #f8f9fa;
}
.table-modern tbody tr:hover {
    background: #f8f9fa;
    transition: background 0.3s ease;
}
</style>

<div class="container-fluid">
    <!-- Welcome Card with Profile and Stats -->
    <div class="welcome-card">
        <div class="row align-items-center">
            <div class="col-md-7">
                <div class="d-flex align-items-center mb-3">
                    <div>
                        <h1 class="display-6 mb-0">Welcome back, <?php echo htmlspecialchars($patient['full_name']); ?>!</h1>
                        <p class="mb-2 mt-2">
                            <i class="fas fa-calendar-alt"></i> Member since <?php echo formatDate($patient['created_at'], 'M Y'); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-history"></i> Last visit: <?php echo $patient['last_visit_date'] ? formatDate($patient['last_visit_date']) : 'Never'; ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-tooth"></i> <?php echo $totalVisits; ?> total visits
                        </p>
                    </div>
                    <div class="ms-3">
                        <a href="profile.php" class="btn btn-light">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                    </div>
                </div>
                <?php if (canViewReferrals()): ?>
                <div class="mt-3">
                    <div class="referral-code-box" onclick="copyReferralCode()">
                        <i class="fas fa-share-alt"></i>
                        <strong>Referral Code:</strong> <?php echo $patient['referral_code']; ?>
                        <small><i class="fas fa-copy"></i> Click to copy</small>
                    </div>
                    <p class="small mt-2 mb-0">
                        <i class="fas fa-gift"></i> Share this code with friends to earn 50 points each!
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-5 text-center text-md-end">
                <?php if (canViewPoints()): ?>
                <div class="points-circle">
                    <h2><?php echo $points; ?></h2>
                </div>
                <p class="mb-0">Reward Points</p>
                <small class="opacity-75"><?php echo 250 - ($points % 250); ?> points to next reward</small>
                <div class="mt-2">
                    <a href="points.php" class="btn btn-sm btn-light">
                        <i class="fas fa-star"></i> View Points
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo $totalVisits; ?></div>
                <div class="stats-label">Total Visits</div>
            </div>
        </div>
        <?php if (canViewPoints()): ?>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo $points; ?></div>
                <div class="stats-label">Points Earned</div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo $referralCount; ?></div>
                <div class="stats-label">Referrals Made</div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stats-card">
                <div class="stats-number"><?php echo ucfirst($subscription) == 'none' ? 'None' : ucfirst($subscription); ?></div>
                <div class="stats-label">Subscription Plan</div>
            </div>
        </div>
    </div>

    <!-- Next Appointment & Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="appointment-card h-100">
                <h5 class="mb-3">
                    <i class="fas fa-calendar-check text-primary"></i> Next Appointment
                </h5>
                <?php if ($nextAppointment): ?>
                    <div class="appointment-time">
                        <?php echo formatDate($nextAppointment['appointment_date']); ?>
                    </div>
                    <p class="lead mt-2">
                        <i class="fas fa-clock"></i> <?php echo formatTime($nextAppointment['appointment_time']); ?>
                    </p>
                    <p>
                        <i class="fas fa-user-md"></i> Dr. <?php echo $nextAppointment['doctor_name']; ?><br>
                        <i class="fas fa-stethoscope"></i> <?php echo $nextAppointment['treatment_type']; ?>
                    </p>
                    <div class="mt-3">
                        <a href="book.php" class="btn btn-primary btn-sm">Reschedule</a>
                        <button class="btn btn-outline-secondary btn-sm" onclick="alert('Contact clinic for cancellation')">Cancel</button>
                    </div>
                <?php else: ?>
                    <p class="text-muted">No upcoming appointments.</p>
                    <a href="book.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Book Now
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <div class="row h-100">
                <div class="col-6 mb-3">
                    <a href="book.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-calendar-plus fa-2x text-primary"></i>
                        </div>
                        <h6 class="mb-0">Book Appointment</h6>
                        <small class="text-muted">Schedule a visit</small>
                    </a>
                </div>
                <div class="col-6 mb-3">
                    <a href="bills.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-file-invoice-dollar fa-2x text-success"></i>
                        </div>
                        <h6 class="mb-0">View Bills</h6>
                        <small class="text-muted">Check payments</small>
                    </a>
                </div>
                <div class="col-6 mb-3">
                    <a href="queue.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-hourglass-half fa-2x text-warning"></i>
                        </div>
                        <h6 class="mb-0">Join Queue</h6>
                        <small class="text-muted">Walk-in waitlist</small>
                    </a>
                </div>
                <div class="col-6 mb-3">
                    <a href="teeth.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-smile fa-2x text-info"></i>
                        </div>
                        <h6 class="mb-0">Dental Chart</h6>
                        <small class="text-muted">View your teeth</small>
                    </a>
                </div>
                <?php if (canViewPoints()): ?>
                <div class="col-6 mb-3">
                    <a href="points.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-star fa-2x text-warning"></i>
                        </div>
                        <h6 class="mb-0">View Points</h6>
                        <small class="text-muted">Rewards & bonuses</small>
                    </a>
                </div>
                <?php endif; ?>
                <?php if (canViewReferrals()): ?>
                <div class="col-6 mb-3">
                    <a href="referrals.php" class="quick-action-btn">
                        <div class="quick-action-icon">
                            <i class="fas fa-share-alt fa-2x text-primary"></i>
                        </div>
                        <h6 class="mb-0">Referrals</h6>
                        <small class="text-muted">Invite friends</small>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Subscription Status -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="mb-1">
                                <i class="fas fa-crown text-warning"></i> Subscription Status
                            </h5>
                            <?php if ($subscription != 'none'): ?>
                                <p class="mb-0">
                                    <strong><?php echo ucfirst($subscription); ?> Plan</strong> - Active
                                </p>
                                <small class="text-muted">
                                    Valid until <?php echo formatDate($subscriptionEnd); ?>
                                </small>
                            <?php else: ?>
                                <p class="mb-0 text-muted">No active subscription</p>
                                <small>Subscribe to get discounts and free cleanings!</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-md-end mt-3 mt-md-0">
                            <a href="subscription.php" class="btn btn-outline-primary">
                                <i class="fas fa-arrow-right"></i> 
                                <?php echo $subscription != 'none' ? 'Manage Subscription' : 'View Plans'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Appointments Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history"></i> Recent Appointments
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentAppointments)): ?>
                        <p class="text-muted text-center py-4">
                            <i class="fas fa-calendar-times fa-3x mb-2 d-block"></i>
                            No appointments yet. Book your first appointment!
                        </p>
                        <div class="text-center">
                            <a href="book.php" class="btn btn-primary">Book Appointment</a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    32
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Treatment</th>
                                        <th>Doctor</th>
                                        <th>Status</th>
                                        <?php if (canViewPoints()): ?>
                                        <th>Points</th>
                                        <?php endif; ?>
                                    </thead>
                                <tbody>
                                    <?php foreach ($recentAppointments as $apt): ?>
                                    <tr>
                                        <td><strong><?php echo formatDate($apt['appointment_date']); ?></strong></td>
                                        <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                        <td><?php echo $apt['treatment_type']; ?></td>
                                        <td>Dr. <?php echo $apt['doctor_name']; ?></td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            switch($apt['status']) {
                                                case 'scheduled': $statusClass = 'status-scheduled'; break;
                                                case 'completed': $statusClass = 'status-completed'; break;
                                                case 'cancelled': $statusClass = 'status-cancelled'; break;
                                                case 'checked-in': $statusClass = 'status-checked-in'; break;
                                                default: $statusClass = 'status-scheduled';
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($apt['status']); ?>
                                            </span>
                                        </td>
                                        <?php if (canViewPoints()): ?>
                                        <td>
                                            <?php if ($apt['status'] == 'completed'): ?>
                                                <span class="text-success">
                                                    <i class="fas fa-plus-circle"></i> +50
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscription Plans (if not subscribed) -->
    <?php if ($subscription == 'none'): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-gem text-warning"></i> Recommended for You
                    </h5>
                    <small class="text-muted">Unlock exclusive benefits with our subscription plans</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="subscription-plan-card">
                                <h5 class="mt-2">Basic</h5>
                                <div class="plan-price">$29<span class="small">/month</span></div>
                                <hr>
                                <div class="plan-feature">
                                    <i class="fas fa-check-circle text-success"></i> 2 free cleanings/year
                                </div>
                                <div class="plan-feature">
                                    <i class="fas fa-check-circle text-success"></i> 10% off treatments
                                </div>
                                <div class="plan-feature text-muted">
                                    <i class="fas fa-times-circle"></i> Priority scheduling
                                </div>
                                <a href="subscription.php?plan=basic" class="btn btn-outline-primary btn-sm mt-3 w-100">
                                    Choose Plan
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="subscription-plan-card border-warning" style="border: 2px solid #ffc107;">
                                <div class="badge bg-warning text-dark mb-2">Most Popular</div>
                                <h5>Premium</h5>
                                <div class="plan-price">$49<span class="small">/month</span></div>
                                <hr>
                                <div class="plan-feature">
                                    <i class="fas fa-check-circle text-success"></i> 4 free cleanings/year
                                </div>
                                <div class="plan-feature">
                                    <i class="fas fa-check-circle text-success"></i> 20% off treatments
                                </div>
                                <div class="plan-feature">
                                    <i class="fas fa-check-circle text-success"></i> Priority scheduling
                                </div>
                                <a href="subscription.php?plan=premium" class="btn btn-warning btn-sm mt-3 w-100">
                                    Choose Plan
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="subscription-plan-card">
                                <h5>Family</h5>
                                <div class="plan-price">$79<span class="small">/month</span></div>
                                <hr>
                                <div class="plan-feature">
                                    <i class="fas fa-check-circle text-success"></i> Covers up to 4 members
                                </div>
                                <div class="plan-feature">
                                    <i class="fas fa-check-circle text-success"></i> 3 cleanings each/year
                                </div>
                                <div class="plan-feature">
                                    <i class="fas fa-check-circle text-success"></i> 15% off treatments
                                </div>
                                <a href="subscription.php?plan=family" class="btn btn-outline-primary btn-sm mt-3 w-100">
                                    Choose Plan
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function copyReferralCode() {
    const code = '<?php echo $patient['referral_code']; ?>';
    navigator.clipboard.writeText(code).then(function() {
        alert('Referral code copied to clipboard!');
    });
}
</script>

<?php include '../layouts/footer.php'; ?>