<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!function_exists('canViewPoints')) {
    function canViewPoints()
    {
        global $db;
        static $cached = null;
        if ($cached === null) {
            $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_points_view'");
            $cached = ($result && $result['setting_value'] == '1');
        }
        return $cached;
    }
}
if (!function_exists('canViewReferrals')) {
    function canViewReferrals()
    {
        global $db;
        static $cached = null;
        if ($cached === null) {
            $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_referrals_view'");
            $cached = ($result && $result['setting_value'] == '1');
        }
        return $cached;
    }
}
if (!function_exists('canViewSubscription')) {
    function canViewSubscription()
    {
        global $db;
        static $cached = null;
        if ($cached === null) {
            $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_subscription_view'");
            $cached = ($result && $result['setting_value'] == '1');
        }
        return $cached;
    }
}

Auth::requireLogin();
if ($_SESSION['role'] != 'patient') {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$userId = Auth::userId();
$patientId = getPatientIdFromUserId($userId);

if (!$patientId) {
    die('Patient record not found. Please contact support.');
}

$patient = $db->fetchOne('SELECT * FROM patients WHERE id = ?', [$patientId], 'i');

if (canViewReferrals() && empty($patient['referral_code'])) {
    $newCode = strtoupper(substr(md5($patientId . uniqid()), 0, 8));
    $db->execute('UPDATE patients SET referral_code = ? WHERE id = ?', [$newCode, $patientId], 'si');
    $patient = $db->fetchOne('SELECT * FROM patients WHERE id = ?', [$patientId], 'i');
}

$nextAppointment = $db->fetchOne(
    "SELECT a.*, u.full_name as doctor_name 
     FROM appointments a
     JOIN users u ON a.doctor_id = u.id
     WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('scheduled', 'checked-in')
     ORDER BY a.appointment_date, a.appointment_time
     LIMIT 1",
    [$patientId],
    'i'
);

$totalVisits = (int) $db->fetchOne(
    "SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'completed'",
    [$patientId],
    'i'
)['count'];

$recentAppointments = $db->fetchAll(
    "SELECT a.*, u.full_name as doctor_name 
     FROM appointments a
     JOIN users u ON a.doctor_id = u.id
     WHERE a.patient_id = ?
     ORDER BY a.appointment_date DESC, a.appointment_time DESC
     LIMIT 5",
    [$patientId],
    'i'
);

$showPoints = canViewPoints();
$showRefs = canViewReferrals();
$showSub = canViewSubscription();

$referralCount = 0;
if ($showRefs) {
    $referralCount = (int) $db->fetchOne(
        'SELECT COUNT(*) as count FROM patients WHERE referred_by = ?',
        [$patientId],
        'i'
    )['count'];
}

$points = (int) ($patient['points'] ?? 0);
$subscription = $patient['subscription_type'] ?? 'none';
$subscriptionEnd = $patient['subscription_end_date'] ?? null;

$statTiles = [
    ['mod' => 'invoices', 'value' => $totalVisits, 'label' => 'Total visits', 'isText' => false],
];
if ($showPoints) {
    $statTiles[] = ['mod' => 'paid', 'value' => $points, 'label' => 'Points earned', 'isText' => false];
}
if ($showRefs) {
    $statTiles[] = ['mod' => 'subs', 'value' => $referralCount, 'label' => 'Referrals made', 'isText' => false];
}
if ($showSub) {
    $planDisp = ($subscription === 'none' || $subscription === '') ? 'None' : ucfirst($subscription);
    $statTiles[] = ['mod' => 'due', 'value' => $planDisp, 'label' => 'Subscription', 'isText' => true];
}

$nStatFull = count($statTiles);
if ($nStatFull === 2) {
    $statTiles = array_values(array_filter($statTiles, static function ($t) {
        return ($t['mod'] ?? '') !== 'invoices';
    }));
}

$canonStatMods = ['invoices', 'paid', 'subs', 'due'];
$orderedStatMods = [];
foreach ($canonStatMods as $mod) {
    foreach ($statTiles as $t) {
        if (($t['mod'] ?? '') === $mod) {
            $orderedStatMods[] = $mod;
            break;
        }
    }
}
$showReferralsCountInHeader = count($orderedStatMods) >= 2 && ($orderedStatMods[1] ?? '') === 'subs' && $showRefs;
if ($showReferralsCountInHeader) {
    $statTiles = array_values(array_filter($statTiles, static function ($t) {
        return ($t['mod'] ?? '') !== 'subs';
    }));
}

$nStats = count($statTiles);
if ($nStats === 1) {
    $statColClass = 'col-12 col-md-4 mb-3 mx-auto';
} elseif ($nStats === 2) {
    $statColClass = 'col-6 col-md-6 mb-3';
} elseif ($nStats === 3) {
    $statColClass = 'col-6 col-md-4 mb-3';
} else {
    $statColClass = 'col-6 col-md-3 mb-3';
}

$quickActions = [
    ['href' => 'queue.php', 'icon' => 'fa-calendar-plus', 'title' => 'Book & queue', 'sub' => 'Schedule or join waitlist'],
    ['href' => 'bills.php', 'icon' => 'fa-file-invoice-dollar', 'title' => 'Bills', 'sub' => 'Payments & invoices'],
    ['href' => 'teeth.php', 'icon' => 'fa-smile', 'title' => 'Dental chart', 'sub' => 'Tooth chart'],
    ['href' => 'profile.php', 'icon' => 'fa-user', 'title' => 'Profile', 'sub' => 'Your account'],
    ['href' => 'subscription.php', 'icon' => 'fa-crown', 'title' => 'Subscription', 'sub' => 'Plans & benefits'],
];
if ($showPoints) {
    $quickActions[] = ['href' => 'points.php', 'icon' => 'fa-star', 'title' => 'Points', 'sub' => 'Rewards'];
}
if ($showRefs) {
    $quickActions[] = ['href' => 'referrals.php', 'icon' => 'fa-share-alt', 'title' => 'Referrals', 'sub' => 'Invite friends'];
}
$actionColClassSidebar = 'col-6 mb-2';

$ptsMod = $points % 250;
$toNextReward = $points === 0 ? 250 : ($ptsMod === 0 ? 250 : 250 - $ptsMod);

$pageTitle = 'My Portal';
include '../layouts/header.php';
?>


<div class="container-fluid bills-page patient-portal patient-index-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-md-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-hand-sparkles me-2 opacity-90" aria-hidden="true"></i>Welcome back, <?php echo htmlspecialchars($patient['full_name']); ?>
                </h2>
                <p class="mb-2 opacity-90">
                    <i class="fas fa-calendar-alt me-1" aria-hidden="true"></i>Member since <?php echo htmlspecialchars(formatDate($patient['created_at'], 'M Y')); ?>
                    <span class="mx-2">·</span>
                    <i class="fas fa-history me-1" aria-hidden="true"></i>Last visit:
                    <?php echo patientHasLastVisitDate($patient['last_visit_date'] ?? null)
                        ? htmlspecialchars(formatDate(normalizePatientOptionalDate($patient['last_visit_date'] ?? null)))
                        : 'Never'; ?>
                    <span class="mx-2">·</span>
                    <i class="fas fa-tooth me-1" aria-hidden="true"></i><?php echo (int) $totalVisits; ?> visits
                </p>
                <?php if ($showRefs): ?>
                    <p class="small mt-3 mb-0 opacity-90">
                        <i class="fas fa-gift me-1" aria-hidden="true"></i>Share your referral code with friends to earn bonus points.
                    </p>
                <?php endif; ?>
            </div>
            <div class="col-md-4 mt-3 mt-md-0">
                <div class="bills-balance-wrap">
                    <div class="bills-balance-box">
                        <?php if ($showPoints): ?>
                            <small>Your points</small>
                            <p class="bills-balance-amount"><?php echo (int) $points; ?></p>
                            <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;"><?php echo (int) $toNextReward; ?> pts to next reward</small>
                            <?php if (!empty($showReferralsCountInHeader)): ?>
                                <small class="d-block mt-2 opacity-90" style="font-size:0.65rem;text-transform:none;letter-spacing:0;"><i class="fas fa-user-friends me-1" aria-hidden="true"></i>Referrals made: <?php echo (int) $referralCount; ?></small>
                            <?php endif; ?>
                        <?php elseif ($showRefs): ?>
                            <?php if (!empty($showReferralsCountInHeader)): ?>
                                <small>Referrals made</small>
                                <p class="bills-balance-amount"><?php echo (int) $referralCount; ?></p>
                                <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;">Code: <span style="letter-spacing:0.1em;"><?php echo htmlspecialchars((string) ($patient['referral_code'] ?? '')); ?></span></small>
                                <button type="button" class="btn btn-sm bills-cta bills-cta--book mt-2 w-100" id="idxCopyRefBtn">Copy code</button>
                            <?php else: ?>
                            <small>Referral code</small>
                            <p class="bills-balance-amount" style="font-size:1rem;letter-spacing:0.12em;"><?php echo htmlspecialchars((string) ($patient['referral_code'] ?? '')); ?></p>
                            <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;">Click to copy</small>
                            <button type="button" class="btn btn-sm bills-cta bills-cta--book mt-2 w-100" id="idxCopyRefBtn">Copy code</button>
                            <?php endif; ?>
                        <?php elseif ($showSub): ?>
                            <small>Subscription</small>
                            <p class="bills-balance-amount" style="font-size:1rem;"><?php echo $subscription !== 'none' && $subscription !== '' ? htmlspecialchars(ucfirst($subscription)) : 'None'; ?></p>
                            <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;"><?php echo $subscription !== 'none' && $subscriptionEnd ? 'Until ' . htmlspecialchars(formatDate($subscriptionEnd)) : 'View available plans'; ?></small>
                            <a href="subscription.php" class="btn btn-sm bills-cta bills-cta--book mt-2">Subscription</a>
                        <?php else: ?>
                            <small>Activity</small>
                            <p class="bills-balance-amount"><?php echo (int) $totalVisits; ?></p>
                            <small class="d-block mt-1" style="font-size:0.6rem;text-transform:none;letter-spacing:0;">Completed visits</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row patient-stats-row mb-4 g-3 justify-content-center">
        <?php foreach ($statTiles as $tile): ?>
            <div class="<?php echo htmlspecialchars($statColClass); ?>">
                <div class="bills-stats-card bills-stats-card--<?php echo htmlspecialchars($tile['mod']); ?><?php echo !empty($tile['isText']) ? ' idx-stat-text' : ''; ?>">
                    <div class="bills-stats-number"><?php echo htmlspecialchars((string) $tile['value']); ?></div>
                    <div class="bills-stats-label"><?php echo htmlspecialchars($tile['label']); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-4 mb-4 idx-patient-two-col align-items-stretch">
        <div class="col-lg-8 col-xl-8 d-flex">
            <div class="idx-main-stack w-100" id="idx-main-stack">
                <div id="idx-next-appt-block" class="card bills-dash-section-card mb-0 idx-next-appt-card border-0 shadow-none bg-transparent">
                    <div class="card-body idx-next-appt-wrap p-0">
                        <?php if ($nextAppointment): ?>
                            <div class="idx-next-appt-panel">
                                <h2 class="idx-next-appt-panel__title">Next Appointment</h2>
                                <div class="idx-next-appt-panel__content">
                                    <div class="idx-next-appt-body-row">
                                        <div class="idx-next-appt-mid">
                                            <div class="idx-next-appt-rows">
                                                <div class="idx-next-appt-row">
                                                    <i class="fas fa-calendar-day" aria-hidden="true"></i>
                                                    <span class="idx-next-appt-label">Date</span><span class="idx-next-appt-value"><?php echo htmlspecialchars(formatDate($nextAppointment['appointment_date'])); ?></span>
                                                </div>
                                                <div class="idx-next-appt-row">
                                                    <i class="fas fa-clock" aria-hidden="true"></i>
                                                    <span class="idx-next-appt-label">Time</span><span class="idx-next-appt-value"><?php echo htmlspecialchars(formatTime($nextAppointment['appointment_time'])); ?></span>
                                                </div>
                                                <div class="idx-next-appt-row">
                                                    <i class="fas fa-user-md" aria-hidden="true"></i>
                                                    <span class="idx-next-appt-label">Doctor</span><span class="idx-next-appt-value">Dr. <?php echo htmlspecialchars($nextAppointment['doctor_name']); ?></span>
                                                </div>
                                                <div class="idx-next-appt-row">
                                                    <i class="fas fa-stethoscope" aria-hidden="true"></i>
                                                    <span class="idx-next-appt-label">Treatment</span><span class="idx-next-appt-value"><?php echo htmlspecialchars((string) $nextAppointment['treatment_type']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="idx-next-appt-actions">
                                            <a href="queue.php" class="idx-next-appt-action-btn idx-next-appt-action-btn--schedule">Reschedule</a>
                                            <button type="button" class="idx-next-appt-action-btn idx-next-appt-action-btn--cancel" id="idxCancelNextApptBtn" data-appt-id="<?php echo (int) $nextAppointment['id']; ?>">Cancel</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="idx-next-appt-panel">
                                <h2 class="idx-next-appt-panel__title">Next Appointment</h2>
                                <div class="idx-next-appt-panel__content idx-next-appt-panel__content--empty">
                                    <p class="idx-next-appt-empty-msg">No upcoming appointments.</p>
                                    <a href="queue.php" class="idx-next-appt-action-btn idx-next-appt-action-btn--schedule">Book Now</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div id="idx-recent-appt-block" class="card bills-dash-section-card mb-0 idx-recent-appt-card">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-history me-2" aria-hidden="true"></i>Recent appointments</h5>
                            </div>
                            <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentAppointments)): ?>
                            <div class="bills-empty-state text-center py-4 px-3">
                                <p class="text-muted small mb-3">No appointments yet.</p>
                                <a href="queue.php" class="btn btn-sm bills-cta bills-cta--book">Book appointment</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Date</th>
                                            <th>Time</th>
                                            <th>Treatment</th>
                                            <th>Doctor</th>
                                            <th>Status</th>
                                            <?php if ($showPoints): ?>
                                                <th>Points</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentAppointments as $apt): ?>
                                            <?php
                                            $badgeClass = 'bills-badge bills-badge--blue';
                                            switch ($apt['status']) {
                                                case 'completed':
                                                    $badgeClass = 'bills-badge bills-badge--green';
                                                    break;
                                                case 'cancelled':
                                                    $badgeClass = 'bills-badge bills-badge--red';
                                                    break;
                                                case 'checked-in':
                                                    $badgeClass = 'bills-badge bills-badge--yellow';
                                                    break;
                                            }
                                            ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars(formatDate($apt['appointment_date'])); ?></strong></td>
                                                <td><?php echo htmlspecialchars(formatTime($apt['appointment_time'])); ?></td>
                                                <td><?php echo htmlspecialchars((string) $apt['treatment_type']); ?></td>
                                                <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                                <td><span class="<?php echo htmlspecialchars($badgeClass); ?>"><?php echo htmlspecialchars(ucfirst((string) $apt['status'])); ?></span></td>
                                                <?php if ($showPoints): ?>
                                                    <td>
                                                        <?php if ($apt['status'] === 'completed'): ?>
                                                            <span class="bills-badge bills-badge--green">+50</span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
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
                <?php if ($showSub): ?>
                    <div id="idx-sub-slot-left" class="idx-sub-slot">
                        <div id="idx-subscription-panel" class="w-100">
                            <div class="card bills-dash-section-card mb-0">
                                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                                    <div class="bills-arrivals-section-header__inner align-items-center">
                                        <div>
                                            <h5 class="card-title mb-0"><i class="fas fa-crown me-2" aria-hidden="true"></i>Subscription</h5>
                                        </div>
                                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                                    </div>
                                </div>
                                <div class="card-body py-3">
                                    <?php if ($subscription !== 'none' && $subscription !== ''): ?>
                                        <p class="mb-1 small"><strong><?php echo htmlspecialchars(ucfirst($subscription)); ?></strong> plan</p>
                                        <?php if ($subscriptionEnd): ?>
                                            <small class="text-muted d-block mb-2">Valid until <?php echo htmlspecialchars(formatDate($subscriptionEnd)); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <p class="text-muted small mb-2">No active subscription. View plans for discounts and cleanings.</p>
                                    <?php endif; ?>
                                    <a href="subscription.php" class="btn btn-sm btn-light text-dark fw-semibold w-100 mt-2"><?php echo $subscription !== 'none' && $subscription !== '' ? 'Manage' : 'View plans'; ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4 col-xl-4 d-flex flex-column">
            <div class="idx-sidebar-stack d-flex flex-column w-100">
                <div class="card bills-dash-section-card mb-0 idx-quick-actions-card w-100">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-bolt me-2" aria-hidden="true"></i>Quick actions</h5>
                            </div>
                            <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body idx-quick-actions-body d-flex flex-column py-3">
                        <div class="row g-2 idx-quick-actions-inner">
                            <?php foreach ($quickActions as $qa): ?>
                                <div class="<?php echo htmlspecialchars($actionColClassSidebar); ?>">
                                    <a href="<?php echo htmlspecialchars($qa['href']); ?>" class="idx-quick-action">
                                        <i class="fas <?php echo htmlspecialchars($qa['icon']); ?> d-block" aria-hidden="true"></i>
                                        <strong class="d-block small"><?php echo htmlspecialchars($qa['title']); ?></strong>
                                        <span class="small text-muted"><?php echo htmlspecialchars($qa['sub']); ?></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php if ($showSub): ?>
                    <div id="idx-sub-slot-right" class="idx-sub-slot"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($showSub && ($subscription === 'none' || $subscription === '')): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card bills-dash-section-card mb-0">
                    <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                        <div class="bills-arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0"><i class="fas fa-gem me-2" aria-hidden="true"></i>Recommended plans</h5>
                            </div>
                            <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="idx-rec-plans-intro">Unlock benefits with an annual subscription — same options as on the subscription page, scaled for a quick overview.</p>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="card idx-rec-plan-card h-100">
                                    <div class="card-body text-center idx-rec-plan-card-body">
                                        <div class="idx-rec-plan-icon"><i class="fas fa-tooth" aria-hidden="true"></i></div>
                                        <h3 class="idx-rec-plan-name">Basic Plan</h3>
                                        <p class="idx-rec-plan-price mb-0">$29<span class="small">/month</span></p>
                                        <p class="idx-rec-plan-year">$348/year</p>
                                        <ul class="list-unstyled idx-rec-plan-features">
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>2 free cleanings/year</li>
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>10% off treatments</li>
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>Free consultation</li>
                                        </ul>
                                        <a href="subscription.php?plan=basic" class="btn btn-subscribe-plan btn-subscribe-plan--basic w-100">Choose plan</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card idx-rec-plan-card idx-rec-plan-card--highlight h-100">
                                    <div class="card-body text-center idx-rec-plan-card-body">
                                        <span class="bills-badge bills-badge--yellow mb-1 d-inline-block">Popular</span>
                                        <div class="idx-rec-plan-icon"><i class="fas fa-crown" aria-hidden="true"></i></div>
                                        <h3 class="idx-rec-plan-name">Premium Plan</h3>
                                        <p class="idx-rec-plan-price mb-0">$49<span class="small">/month</span></p>
                                        <p class="idx-rec-plan-year">$588/year</p>
                                        <ul class="list-unstyled idx-rec-plan-features">
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>4 free cleanings/year</li>
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>20% off treatments</li>
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>Priority scheduling</li>
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>Emergency access</li>
                                        </ul>
                                        <a href="subscription.php?plan=premium" class="btn btn-subscribe-plan btn-subscribe-plan--premium w-100">Choose plan</a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card idx-rec-plan-card h-100">
                                    <div class="card-body text-center idx-rec-plan-card-body">
                                        <div class="idx-rec-plan-icon"><i class="fas fa-users" aria-hidden="true"></i></div>
                                        <h3 class="idx-rec-plan-name">Family Plan</h3>
                                        <p class="idx-rec-plan-price mb-0">$79<span class="small">/month</span></p>
                                        <p class="idx-rec-plan-year">$948/year</p>
                                        <ul class="list-unstyled idx-rec-plan-features">
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>Covers up to 4 members</li>
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>3 cleanings each/year</li>
                                            <li><i class="fas fa-check me-2" aria-hidden="true"></i>15% off treatments</li>
                                        </ul>
                                        <a href="subscription.php?plan=family" class="btn btn-subscribe-plan btn-subscribe-plan--basic w-100">Choose plan</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($showSub): ?>
<script>
(function () {
    var mq = window.matchMedia('(min-width: 992px)');
    var resizeTimer;

    function parseGapPx(el) {
        if (!el) {
            return 24;
        }
        var g = window.getComputedStyle(el).gap;
        if (!g || g === 'normal') {
            return 24;
        }
        var part = g.split(/\s+/)[0];
        var px = /^([\d.]+)px$/i.exec(part);
        if (px) {
            return parseFloat(px[1]);
        }
        var rem = /^([\d.]+)rem$/i.exec(part);
        if (rem) {
            return parseFloat(rem[1]) * parseFloat(window.getComputedStyle(document.documentElement).fontSize);
        }
        return 24;
    }

    function placeSubscriptionPanel() {
        var panel = document.getElementById('idx-subscription-panel');
        var slotL = document.getElementById('idx-sub-slot-left');
        var slotR = document.getElementById('idx-sub-slot-right');
        var nextB = document.getElementById('idx-next-appt-block');
        var recentB = document.getElementById('idx-recent-appt-block');
        var qa = document.querySelector('.patient-index-page .idx-quick-actions-card');
        var stack = document.getElementById('idx-main-stack');
        if (!panel || !slotL || !slotR) {
            return;
        }
        if (!mq.matches) {
            slotL.appendChild(panel);
            slotL.style.display = '';
            slotR.style.display = 'none';
            return;
        }
        if (!nextB || !recentB || !qa) {
            slotL.appendChild(panel);
            slotL.style.display = '';
            slotR.style.display = 'none';
            return;
        }
        var gapPx = parseGapPx(stack);
        var hLeft = nextB.offsetHeight + recentB.offsetHeight + gapPx;
        var hQa = qa.offsetHeight;
        if (hQa < hLeft) {
            slotR.appendChild(panel);
            slotL.style.display = 'none';
            slotR.style.display = '';
        } else {
            slotL.appendChild(panel);
            slotL.style.display = '';
            slotR.style.display = 'none';
        }
    }

    function schedulePlace() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(placeSubscriptionPanel, 80);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', placeSubscriptionPanel);
    } else {
        placeSubscriptionPanel();
    }
    window.addEventListener('load', placeSubscriptionPanel);
    window.addEventListener('resize', schedulePlace);
    if (typeof mq.addEventListener === 'function') {
        mq.addEventListener('change', placeSubscriptionPanel);
    } else if (typeof mq.addListener === 'function') {
        mq.addListener(placeSubscriptionPanel);
    }
})();
</script>
<?php endif; ?>

<?php if ($showRefs && !$showPoints): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('idxCopyRefBtn');
    const code = <?php echo json_encode((string) ($patient['referral_code'] ?? '')); ?>;
    if (btn && code) {
        btn.addEventListener('click', function () {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(code).then(function () {
                    btn.textContent = 'Copied!';
                    setTimeout(function () { btn.textContent = 'Copy code'; }, 1800);
                });
            } else {
                alert('Your code: ' + code);
            }
        });
    }
});
</script>
<?php endif; ?>

<?php if (!empty($nextAppointment)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('idxCancelNextApptBtn');
    if (!btn) {
        return;
    }
    var id = parseInt(btn.getAttribute('data-appt-id'), 10);
    if (!id) {
        return;
    }
    btn.addEventListener('click', function () {
        if (!window.confirm('Remove this appointment from your schedule? This cannot be undone.')) {
            return;
        }
        btn.disabled = true;
        fetch('../api/appointments.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ id: id })
        })
            .then(function (response) {
                return response.json().then(function (data) {
                    return { ok: response.ok, data: data };
                });
            })
            .then(function (result) {
                if (result.data && result.data.success) {
                    window.location.reload();
                    return;
                }
                window.alert((result.data && result.data.message) || 'Could not cancel appointment.');
                btn.disabled = false;
            })
            .catch(function () {
                window.alert('Network error.');
                btn.disabled = false;
            });
    });
});
</script>
<?php endif; ?>

<?php include '../layouts/footer.php'; ?>
