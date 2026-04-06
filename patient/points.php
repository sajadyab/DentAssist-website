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
    die('Patient record not found.');
}

$patient = $db->fetchOne('SELECT points, full_name FROM patients WHERE id = ?', [$patientId], 'i');
$points = (int) ($patient['points'] ?? 0);

$referralCount = (int) $db->fetchOne(
    'SELECT COUNT(*) as count FROM patients WHERE referred_by = ?',
    [$patientId],
    'i'
)['count'];

$appointmentPoints = $db->fetchAll(
    "SELECT appointment_date, treatment_type, 'appointment' as source FROM appointments WHERE patient_id = ? AND status = 'completed' ORDER BY appointment_date DESC LIMIT 10",
    [$patientId],
    'i'
);

$historyItems = [];
foreach ($appointmentPoints as $apt) {
    $historyItems[] = [
        'side' => formatDate($apt['appointment_date']),
        'title' => 'Completed visit',
        'muted' => (string) $apt['treatment_type'],
        'badgeClass' => 'bills-badge bills-badge--green',
        'pointsLabel' => '+50',
    ];
}
if ($referralCount > 0) {
    $historyItems[] = [
        'side' => 'Referral',
        'title' => $referralCount . ' friend' . ($referralCount > 1 ? 's' : '') . ' joined',
        'muted' => 'Referral bonus',
        'badgeClass' => 'bills-badge bills-badge--green',
        'pointsLabel' => '+' . ($referralCount * 50),
    ];
}
if (empty($historyItems) && $points > 0) {
    $historyItems[] = [
        'side' => '—',
        'title' => 'Points balance',
        'muted' => 'Your current total',
        'badgeClass' => 'bills-badge bills-badge--blue',
        'pointsLabel' => (string) $points,
    ];
}

$ptsMod = $points % 250;
$toNextReward = $points === 0 ? 250 : ($ptsMod === 0 ? 250 : 250 - $ptsMod);

$pageTitle = 'My Points';
include '../layouts/header.php';
?>


<div class="container-fluid bills-page patient-portal points-page">
   

    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-md-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-star me-2 opacity-90" aria-hidden="true"></i>My Reward Points
                </h2>
                <p class="mb-0 opacity-90">Earn points with every visit and referral. Redeem them for rewards!</p>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="bills-balance-wrap">
                    <div class="bills-balance-box">
                        <small>Your points</small>
                        <p class="bills-balance-amount"><?php echo (int) $points; ?></p>
                        <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;"><?php echo (int) $toNextReward; ?> pts to next reward</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row patient-stats-row mb-4 g-3">
        <div class="col-6 col-md-4 mb-3">
            <div class="bills-stats-card bills-stats-card--paid">
                <div class="bills-stats-number"><?php echo (int) $points; ?></div>
                <div class="bills-stats-label">Total Points</div>
            </div>
        </div>
        <div class="col-6 col-md-4 mb-3">
            <div class="bills-stats-card bills-stats-card--invoices">
                <div class="bills-stats-number"><?php echo (int) floor($points / 250); ?></div>
                <div class="bills-stats-label">Rewards Available</div>
            </div>
        </div>
        <div class="col-12 col-md-4 mb-3">
            <div class="bills-stats-card bills-stats-card--subs">
                <div class="bills-stats-number"><?php echo (int) ($referralCount * 50); ?></div>
                <div class="bills-stats-label">Points from Referrals</div>
            </div>
        </div>
    </div>

    <div class="points-cards-grid">
        <div class="points-area-history">
            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-history me-2" aria-hidden="true"></i>Points History</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($historyItems)): ?>
                        <div class="bills-empty-state text-center py-4 px-3">
                            <p class="text-muted small mb-3">No points yet.</p>
                            <a href="queue.php" class="btn btn-sm bills-cta bills-cta--book">Book an Appointment</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($historyItems as $row): ?>
                            <div class="bills-dash-row">
                                <span class="bills-side-id"><?php echo htmlspecialchars($row['side']); ?></span>
                                <div class="bills-dash-col-main">
                                    <span class="bills-dash-strong"><?php echo htmlspecialchars($row['title']); ?></span>
                                    <span class="bills-dash-muted"><?php echo htmlspecialchars($row['muted']); ?></span>
                                </div>
                                <div class="bills-dash-actions">
                                    <span class="<?php echo htmlspecialchars($row['badgeClass']); ?>"><?php echo htmlspecialchars($row['pointsLabel']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="points-area-earn">
            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2" aria-hidden="true"></i>How to Earn Points</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="bills-dash-row points-earn-row">
                        <div class="bills-dash-col-main">
                            <span class="bills-dash-strong">Complete an appointment</span>
                            <span class="bills-dash-muted">Earn points for every completed dental visit</span>
                        </div>
                        <div class="bills-dash-actions">
                            <span class="bills-badge bills-badge--green">+50 pts</span>
                        </div>
                    </div>
                    <div class="bills-dash-row points-earn-row">
                        <div class="bills-dash-col-main">
                            <span class="bills-dash-strong">Refer a friend</span>
                            <span class="bills-dash-muted">Share your referral code and earn points</span>
                        </div>
                        <div class="bills-dash-actions">
                            <span class="bills-badge bills-badge--green">+50 pts</span>
                        </div>
                    </div>
                    <div class="bills-dash-row points-earn-row">
                        <div class="bills-dash-col-main">
                            <span class="bills-dash-strong">Subscribe to Premium</span>
                            <span class="bills-dash-muted">One-time bonus for upgrading</span>
                        </div>
                        <div class="bills-dash-actions">
                            <span class="bills-badge bills-badge--yellow">+200 pts</span>
                        </div>
                    </div>
                    <div class="bills-dash-row points-earn-row">
                        <div class="bills-dash-col-main">
                            <span class="bills-dash-strong">First appointment bonus</span>
                            <span class="bills-dash-muted">Welcome bonus for new patients</span>
                        </div>
                        <div class="bills-dash-actions">
                            <span class="bills-badge bills-badge--blue">+100 pts</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="points-area-rewards">
            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-gift me-2" aria-hidden="true"></i>Available Rewards</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php
                    $rewards = [
                        ['title' => 'Free Teeth Whitening', 'muted' => 'Professional whitening session', 'cost' => '500'],
                        ['title' => 'Free Dental Cleaning', 'muted' => 'One free cleaning session', 'cost' => '250'],
                        ['title' => '$50 Treatment Discount', 'muted' => 'Off any dental treatment', 'cost' => '300'],
                        ['title' => 'Dental Care Kit', 'muted' => 'Premium toothbrush, toothpaste, floss', 'cost' => '150'],
                    ];
                    foreach ($rewards as $rw):
                    ?>
                        <div class="bills-dash-row points-row-three-col-mobile">
                            <span class="bills-side-id"><?php echo htmlspecialchars($rw['cost']); ?> pts</span>
                            <div class="bills-dash-col-main">
                                <span class="bills-dash-strong"><?php echo htmlspecialchars($rw['title']); ?></span>
                                <span class="bills-dash-muted"><?php echo htmlspecialchars($rw['muted']); ?></span>
                            </div>
                            <div class="bills-dash-actions">
                                <button type="button" class="btn btn-sm btn-primary" onclick="alert('Contact the clinic to redeem this reward.')">Redeem</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="points-area-tips">
            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-lightbulb me-2" aria-hidden="true"></i>Pro Tips</h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="fw-semibold small text-uppercase mb-2" style="color:#475569;letter-spacing:0.04em;">Get more from your points</p>
                    <div class="d-flex mb-3">
                        <i class="fas fa-share-alt fa-lg me-3" style="color:var(--bills-accent-deep);" aria-hidden="true"></i>
                        <div>
                            <strong>Share your referral code</strong>
                            <p class="small text-muted mb-0">Earn 50 points for each friend who joins.</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fas fa-calendar-check fa-lg me-3" style="color:var(--bills-accent-deep);" aria-hidden="true"></i>
                        <div>
                            <strong>Keep appointments</strong>
                            <p class="small text-muted mb-0">Complete visits to keep earning points.</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <i class="fas fa-crown fa-lg me-3" style="color:var(--bills-accent-deep);" aria-hidden="true"></i>
                        <div>
                            <strong>Upgrade to Premium</strong>
                            <p class="small text-muted mb-0">Bonus points and extra benefits.</p>
                        </div>
                    </div>
                    <hr class="my-3 opacity-50">
                    <div class="bills-alert-soft p-3 mb-0">
                        <i class="fas fa-info-circle me-1" style="color: var(--bills-accent-deep);" aria-hidden="true"></i>
                        <strong>Questions?</strong>
                        <p class="small mb-0 mt-1 text-secondary">Ask the front desk about redemption and balances.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
