<?php
// ==============================================
// Dental Clinic Management System - Staff Dashboard
// Version: 2.0
// ==============================================

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/api/_helpers.php';

// Require login
Auth::requireLogin();

// Patients should not access the staff dashboard
if (Auth::hasRole('patient')) {
    header('Location: patient/index.php');
    exit;
}

$pageTitle = 'Dashboard';
$db = Database::getInstance();

$dashboardRole = $_SESSION['role'] ?? '';
$dashboardUserId = (int) Auth::userId();

// Get today's date
$today = date('Y-m-d');

// Today's appointments (all clinic staff; scoped to doctor when logged in as doctor)
$todayAppointments = repo_dashboard_list_today_appointments(
    $today,
    $dashboardRole === 'doctor' ? $dashboardUserId : null
);

// Subscription stats (compatible with older schemas)
$subscriptionCounts = repo_dashboard_get_subscription_counts();
$pendingSubscriptions = (int) ($subscriptionCounts['pending'] ?? 0);
$activeSubscriptions = (int) ($subscriptionCounts['active'] ?? 0);
$expiringSubscriptions = (int) ($subscriptionCounts['expiring'] ?? 0);
$subscriptionRevenue = repo_dashboard_get_subscription_revenue_total();

// Summary stats (appointment counts respect doctor scope when role is doctor)
$apptCounts = repo_dashboard_get_appointment_counts(
    $today,
    $dashboardRole === 'doctor' ? $dashboardUserId : null
);
$statsUpcoming = (int) ($apptCounts['upcoming'] ?? 0);
$statsCompletedToday = (int) ($apptCounts['completed_today'] ?? 0);

$stats = [
    'today_appointments' => count($todayAppointments),
    'upcoming_appointments' => $statsUpcoming,
    'completed_today' => $statsCompletedToday,
    'pending_subscriptions' => $pendingSubscriptions,
    'active_subscriptions' => $activeSubscriptions,
    'subscription_revenue' => $subscriptionRevenue
];

$dashOnlineRequests = repo_dashboard_list_online_appointment_requests(
    $dashboardRole === 'doctor' ? $dashboardUserId : null
);
$dashOnlineRequestCount = count($dashOnlineRequests);

$calendarDoctorOptions = [];
if ($dashboardRole !== 'doctor') {
    $calendarDoctorOptions = repo_user_list_doctors(true);
}
$defaultCalDoctorId = $dashboardRole === 'doctor'
    ? $dashboardUserId
    : (int) (($calendarDoctorOptions[0]['id'] ?? 0));

$calendarPatientsForJs = repo_patient_list_for_select(800);

require_once __DIR__ . '/includes/dashboard_staff_calendar.php';

$dashWaitingQueue = repo_dashboard_list_weekly_waiting_queue($staffCalDoctorId);

$todayAppointmentsSidebar = repo_dashboard_list_today_appointments(
    $today,
    $staffCalDoctorId > 0 ? $staffCalDoctorId : null
);

/** @var list<array{item_name: string, status: string, badge_class: string}> $dashInventoryNotices */
$dashInventoryNotices = [];
if ($dashboardRole === 'doctor') {
    $invCandidates = repo_dashboard_list_inventory_notice_candidates();
    $weekLast = date('Y-m-d', strtotime($today . ' +7 days'));
    foreach ($invCandidates as $inv) {
        $expRaw = $inv['expiry_date'] ?? null;
        $hasExpiry = $expRaw !== null && $expRaw !== '' && $expRaw !== '0000-00-00' && strtotime((string) $expRaw) !== false;
        $expYmd = $hasExpiry ? date('Y-m-d', strtotime((string) $expRaw)) : null;
        $qty = (int) ($inv['quantity'] ?? 0);
        $reorder = (int) ($inv['reorder_level'] ?? 0);

        $status = null;
        $badgeClass = '';
        if ($hasExpiry && $expYmd !== null && $expYmd < $today) {
            $status = 'Expired';
            $badgeClass = 'badge-inv-expired';
        } elseif ($hasExpiry && $expYmd !== null && $expYmd >= $today && $expYmd <= $weekLast) {
            $status = 'Expiring soon';
            $badgeClass = 'badge-inv-expiring';
        } elseif ($qty > 0 && $qty <= $reorder) {
            $status = 'Low stock';
            $badgeClass = 'badge-inv-low';
        }
        if ($status !== null) {
            $dashInventoryNotices[] = [
                'item_name' => (string) ($inv['item_name'] ?? ''),
                'status' => $status,
                'badge_class' => $badgeClass,
            ];
        }
    }
    usort(
        $dashInventoryNotices,
        static function (array $a, array $b): int {
            $order = ['Expired' => 0, 'Expiring soon' => 1, 'Low stock' => 2];
            $cmp = ($order[$a['status']] ?? 99) <=> ($order[$b['status']] ?? 99);
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcasecmp($a['item_name'], $b['item_name']);
        }
    );
}

$dashCalReturnQuery = [];
if ($dashboardRole !== 'doctor' && $staffCalDoctorId > 0) {
    $dashCalReturnQuery['cal_doctor_id'] = $staffCalDoctorId;
}
$dashCalReturnQuery['cal_view'] = $staffCalView;
$dashCalReturnQuery['cal_week'] = $staffCalWeekOffset;
if ($staffCalView === 'day') {
    $dashCalReturnQuery['cal_day'] = $staffCalDayYmd;
}
$dashCalReturnUrl = '../dashboard.php?' . http_build_query($dashCalReturnQuery);

$staffCalPrevWeek = $staffCalWeekOffset - 1;
$staffCalNextWeek = $staffCalWeekOffset + 1;
$dayForNav = DateTimeImmutable::createFromFormat('Y-m-d', $staffCalDayYmd) ?: new DateTimeImmutable('today');
$staffCalPrevDay = $dayForNav->modify('-1 day')->format('Y-m-d');
$staffCalNextDay = $dayForNav->modify('+1 day')->format('Y-m-d');

/**
 * Build query string for staff calendar navigation (preserves doctor for assistants).
 */
function dash_cal_query(array $p, string $dashboardRole, int $staffCalDoctorId): string
{
    if ($dashboardRole !== 'doctor' && $staffCalDoctorId > 0) {
        $p['cal_doctor_id'] = $staffCalDoctorId;
    }

    return http_build_query($p);
}

include 'layouts/header.php';
?>


<div class="container-fluid dash-queue-page">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1>Dashboard</h1>
    </div>
    
    <!-- Quick Actions Bar -->
    <div class="row mb-2">
        <div class="col-12">
            <div class="card quick-actions-card">
                <div class="card-body">
                    <h6 class="mb-3 fw-semibold">Quick Actions</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="assistant_subscriptions.php" class="btn btn-warning quick-action-btn text-white" style="background-color: rgb(237, 219, 16); border-color: rgb(250, 236, 82);">
                            <i class="fas fa-crown me-2"></i>
                            Pending Subscriptions
                            <?php if ($pendingSubscriptions > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $pendingSubscriptions; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="patients/add.php" class="btn btn-primary quick-action-btn" style="background-color: rgb(23, 69, 185); border-color: rgb(34, 197, 94);">
                            <i class="fas fa-user-plus me-2"></i> Add Patient
                        </a>
                        <a href="appointments/add.php" class="btn btn-success quick-action-btn">
                            <i class="fas fa-calendar-plus me-2"></i> Book Appointment
                        </a>
                        <a href="billing/invoices.php" class="btn btn-info quick-action-btn text-white">
                            <i class="fas fa-file-invoice-dollar me-2"></i> View Invoices
                        </a>
                        <button type="button"
                            class="btn btn-secondary quick-action-btn text-white quick-action-btn--online-requests"
                            data-bs-toggle="collapse"
                            data-bs-target="#dashboardOnlineRequests"
                            aria-expanded="false"
                            aria-controls="dashboardOnlineRequests">
                            <i class="fas fa-globe me-2"></i> Online requests
                          
                        </button>
                    </div>
                    <div class="collapse mt-3" id="dashboardOnlineRequests">
                        <div class="dashboard-online-requests-panel">
                            <div class="panel-inner-head d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <span><i class="fas fa-calendar-plus me-2 text-primary"></i>Patient portal — requested slots</span>
                                <a href="queue/index.php" class="btn btn-sm btn-outline-primary">Open full queue</a>
                            </div>
                            <div class="p-2 p-md-3">
                                <?php if (empty($dashOnlineRequests)): ?>
                                    <p class="text-muted small mb-0">No pending online booking requests.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover table-sm align-middle dashboard-online-requests-table">
                                            <thead>
                                                <tr>
                                                    <th>Patient</th>
                                                    <?php if ($dashboardRole !== 'doctor'): ?>
                                                        <th>Dentist</th>
                                                    <?php endif; ?>
                                                    <th>Date</th>
                                                    <th>Time</th>
                                                    <th>Visit type</th>
                                                    <th>Notes</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dashOnlineRequests as $ar): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="patients/view.php?id=<?php echo (int) $ar['patient_id']; ?>" class="fw-semibold"><?php echo htmlspecialchars($ar['patient_name'] ?? ''); ?></a>
                                                            <?php if (!empty($ar['patient_phone'])): ?>
                                                                <div class="text-muted small text-break"><?php echo htmlspecialchars((string) $ar['patient_phone']); ?></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <?php if ($dashboardRole !== 'doctor'): ?>
                                                            <td class="small"><?php echo htmlspecialchars($ar['doctor_name'] ?? ''); ?></td>
                                                        <?php endif; ?>
                                                        <td class="small"><?php echo htmlspecialchars(formatDate($ar['requested_date'])); ?></td>
                                                        <td class="small"><?php echo htmlspecialchars(formatTime($ar['requested_time'])); ?></td>
                                                        <td class="small"><?php echo htmlspecialchars((string) $ar['treatment_type']); ?></td>
                                                        <td class="text-muted small dor-notes"><?php echo htmlspecialchars((string) ($ar['description'] ?? '')); ?></td>
                                                        <td class="text-end">
                                                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                                <form method="post" action="queue/index.php" class="d-inline" onsubmit="return confirm('Confirm this appointment and notify the patient?');">
                                                                    <input type="hidden" name="request_id" value="<?php echo (int) $ar['id']; ?>">
                                                                    <button type="submit" name="approve_appointment_request" class="btn btn-sm btn-success">
                                                                        <i class="fas fa-check"></i> Accept
                                                                    </button>
                                                                </form>
                                                                <form method="post" action="queue/index.php" class="d-inline" onsubmit="return confirm('Decline this request and notify the patient?');">
                                                                    <input type="hidden" name="request_id" value="<?php echo (int) $ar['id']; ?>">
                                                                    <button type="submit" name="deny_appointment_request" class="btn btn-sm btn-outline-danger">
                                                                        <i class="fas fa-times"></i> Decline
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
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
            </div>
        </div>
    </div>
    
    <!-- Summary badges (non-clickable, single row) -->
    <div class="dashboard-summary-row mb-4" role="presentation">
        <div class="dashboard-summary-item text-white dashboard-summary-item--gradient-success">
            <div class="inner">
                <h6 class="text-white">Today's Appointments</h6>
                <div class="summary-value-row">
                    <i class="fas fa-calendar-day summary-icon text-white" aria-hidden="true"></i>
                    <p class="stat-value text-white mb-0"><?php echo (int) $stats['today_appointments']; ?></p>
                </div>
            </div>
        </div>
        <div class="dashboard-summary-item text-white dashboard-summary-item--gradient-info">
            <div class="inner" style="background-color: rgb(55, 145, 248); ">
                <h6 class="text-white" style="bac">Upcoming Appointments</h6>
                <div class="summary-value-row">
                    <i class="fas fa-calendar-alt summary-icon text-white" aria-hidden="true"></i>
                    <p class="stat-value text-white mb-0"><?php echo (int) $stats['upcoming_appointments']; ?></p>
                </div>
            </div>
        </div>
        <div class="dashboard-summary-item text-white dashboard-summary-item--gradient-warning">
            <div class="inner" style="background-color: rgb(248, 161, 55);  ">
                <h6 class="text-white">Completed Today</h6>
                <div class="summary-value-row">
                    <i class="fas fa-check-circle summary-icon text-white" aria-hidden="true"></i>
                    <p class="stat-value text-white mb-0"><?php echo (int) $stats['completed_today']; ?></p>
                </div>
            </div>
        </div>
        <div class="dashboard-summary-item text-white dashboard-summary-item--gradient-success">
            <div class="inner">
                <h6 class="text-white">Active Subscriptions</h6>
                <div class="summary-value-row">
                    <i class="fas fa-crown summary-icon text-white" aria-hidden="true"></i>
                    <p class="stat-value text-white mb-0"><?php echo (int) $stats['active_subscriptions']; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- Appointment calendar (queue grid) + sidebar (~20% wider calendar vs prior 7/12) -->
    <div class="row g-3 align-items-start mb-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-0 queue-calendar-card h-100">
                <div class="card-header queue-panel-card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2" aria-hidden="true"></i>Appointment calendar
                    </h5>
                  </div>
                <div class="card-body p-3 pt-2 queue-main-calendar">
                    <?php if ($staffCalDoctorId <= 0): ?>
                        <p class="text-muted small mb-0">Add an active dentist user to use the calendar.</p>
                    <?php else: ?>
                        <form method="get" action="dashboard.php" class="staff-cal-top-form mb-2">
                            <input type="hidden" name="cal_view" value="<?php echo htmlspecialchars($staffCalView); ?>">
                            <input type="hidden" name="cal_week" value="<?php echo (int) $staffCalWeekOffset; ?>">
                            <?php if ($staffCalView === 'day'): ?>
                                <input type="hidden" name="cal_day" value="<?php echo htmlspecialchars($staffCalDayYmd); ?>">
                            <?php endif; ?>
                            <?php if ($dashboardRole !== 'doctor'): ?>
                                <div class="mb-2">
                                    <label class="form-label small text-muted mb-1" for="dashCalDoctorSelect">Dentist</label>
                                    <select name="cal_doctor_id" id="dashCalDoctorSelect" class="form-select form-select-sm dash-cal-doctor-select" onchange="this.form.submit()">
                                        <?php foreach ($calendarDoctorOptions as $doc): ?>
                                            <option value="<?php echo (int) $doc['id']; ?>" <?php echo (int) $doc['id'] === $staffCalDoctorId ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($doc['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 staff-cal-toolbar">
                                <div class="d-flex align-items-center flex-wrap gap-1 gap-sm-2 staff-cal-date-nav">
                                    <?php if ($staffCalView === 'week'): ?>
                                        <a class="staff-cal-nav-arrow" href="dashboard.php?<?php echo dash_cal_query(['cal_view' => 'week', 'cal_week' => $staffCalPrevWeek], $dashboardRole, $staffCalDoctorId); ?>" aria-label="Previous week"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
                                        <span class="staff-cal-date-label"><?php echo htmlspecialchars($staffCalMonday->format('M j')); ?> – <?php echo htmlspecialchars($staffCalWeekEnd->format('M j')); ?></span>
                                        <a class="staff-cal-nav-arrow" href="dashboard.php?<?php echo dash_cal_query(['cal_view' => 'week', 'cal_week' => $staffCalNextWeek], $dashboardRole, $staffCalDoctorId); ?>" aria-label="Next week"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                                    <?php else: ?>
                                        <a class="staff-cal-nav-arrow" href="dashboard.php?<?php echo dash_cal_query(['cal_view' => 'day', 'cal_day' => $staffCalPrevDay, 'cal_week' => $staffCalWeekOffset], $dashboardRole, $staffCalDoctorId); ?>" aria-label="Previous day"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
                                        <span class="staff-cal-date-label"><?php echo htmlspecialchars(formatDate($staffCalDayYmd)); ?></span>
                                        <a class="staff-cal-nav-arrow" href="dashboard.php?<?php echo dash_cal_query(['cal_view' => 'day', 'cal_day' => $staffCalNextDay, 'cal_week' => $staffCalWeekOffset], $dashboardRole, $staffCalDoctorId); ?>" aria-label="Next day"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex align-items-center flex-wrap gap-1 cal-nav staff-cal-view-toggle">
                                    <a class="btn btn-cal <?php echo $staffCalView === 'week' ? 'active' : ''; ?>" href="dashboard.php?<?php echo dash_cal_query(['cal_view' => 'week', 'cal_week' => $staffCalWeekOffset], $dashboardRole, $staffCalDoctorId); ?>">Week</a>
                                    <a class="btn btn-cal <?php echo $staffCalView === 'day' ? 'active' : ''; ?>" href="dashboard.php?<?php echo dash_cal_query(['cal_view' => 'day', 'cal_day' => $staffCalView === 'day' ? $staffCalDayYmd : $today, 'cal_week' => $staffCalWeekOffset], $dashboardRole, $staffCalDoctorId); ?>">Day</a>
                                    <?php if ($staffCalView === 'week' && $staffCalWeekOffset !== 0): ?>
                                        <a class="btn btn-cal text-nowrap" href="dashboard.php?<?php echo dash_cal_query(['cal_view' => 'week', 'cal_week' => 0], $dashboardRole, $staffCalDoctorId); ?>">This week</a>
                                    <?php elseif ($staffCalView === 'day' && $staffCalDayYmd !== $today): ?>
                                        <a class="btn btn-cal text-nowrap" href="dashboard.php?<?php echo dash_cal_query(['cal_view' => 'day', 'cal_day' => $today, 'cal_week' => $staffCalWeekOffset], $dashboardRole, $staffCalDoctorId); ?>">Today</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>

                        <div class="calendar-container">
                            <div class="cal-legend">
                                <span><span class="cal-dot cal-dot-free" aria-hidden="true"></span> Available</span>
                                <span><span class="cal-dot cal-dot-busy" aria-hidden="true"></span> Scheduled</span>
                                <span><span class="cal-dot cal-dot-request" aria-hidden="true"></span> Requested</span>
                            </div>
                            <div class="booking-calendar-wrap">
                                <?php if (empty($staffCalColumns)): ?>
                                    <div class="cal-slot-hint p-3">No open days in this range for the clinic schedule.</div>
                                <?php elseif (empty($staffCalTimeRows)): ?>
                                    <div class="cal-slot-hint p-3"><?php echo $staffCalView === 'day' ? 'Clinic is closed on this day.' : 'No times match clinic hours this week.'; ?></div>
                                <?php else: ?>
                                    <table class="booking-cal-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">Time</th>
                                                <?php foreach ($staffCalColumns as $col): ?>
                                                    <th scope="col">
                                                        <span class="booking-cal-th-day"><?php echo htmlspecialchars($col['date']->format('D')); ?></span>
                                                        <span class="booking-cal-th-date"><?php echo htmlspecialchars($col['date']->format('M j')); ?></span>
                                                    </th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($staffCalTimeRows as $his): ?>
                                                <?php
                                                $rowLabel = $his;
                                                foreach ($staffCalColumns as $col) {
                                                    $ymdRl = $col['ymd'];
                                                    if (!empty($staffCalSlotByDayTime[$ymdRl][$his])) {
                                                        $rowLabel = $staffCalSlotByDayTime[$ymdRl][$his]['label'];
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <th class="cal-time-cell" scope="row"><?php echo htmlspecialchars((string) $rowLabel); ?></th>
                                                    <?php foreach ($staffCalColumns as $col): ?>
                                                        <?php
                                                        $ymd = $col['ymd'];
                                                        if (empty($staffCalSlotByDayTime[$ymd][$his])) {
                                                            echo '<td class="cal-table-empty" aria-hidden="true">—</td>';
                                                            continue;
                                                        }
                                                        $sl = $staffCalSlotByDayTime[$ymd][$his];
                                                        $st = $sl['state'];
                                                        ?>
                                                        <td>
                                                            <div class="cal-table-cell-inner">
                                                                <?php if ($st === 'free'): ?>
                                                                    <button type="button"
                                                                        class="cal-slot-btn cal-slot-btn-table cal-slot-btn-free staff-cal-slot-empty"
                                                                        data-date="<?php echo htmlspecialchars($ymd); ?>"
                                                                        data-time="<?php echo htmlspecialchars($sl['time']); ?>"
                                                                        data-doctor-id="<?php echo (int) $staffCalDoctorId; ?>"
                                                                        data-duration="<?php echo (int) $staffCalSlotMinutes; ?>"
                                                                        data-when-label="<?php echo htmlspecialchars($col['date']->format('l, M j, Y') . ' · ' . $sl['label']); ?>"
                                                                        onclick="dashStaffOpenBook(this)"
                                                                        aria-label="Book this slot"><span class="visually-hidden">Available slot</span></button>
                                                                <?php elseif ($st === 'past'): ?>
                                                                    <button type="button" class="cal-slot-btn cal-slot-btn-table cal-slot-btn-past" disabled><?php echo htmlspecialchars($sl['label']); ?></button>
                                                                <?php elseif ($st === 'scheduled' && is_array($sl['payload'])): ?>
                                                                    <?php
                                                                    $ap = $sl['payload'];
                                                                    $schedPayload = [
                                                                        'appointment_id' => (int) $ap['id'],
                                                                        'patient_name' => (string) ($ap['patient_name'] ?? ''),
                                                                        'patient_phone' => (string) ($ap['patient_phone'] ?? ''),
                                                                        'treatment_type' => (string) ($ap['treatment_type'] ?? ''),
                                                                        'doctor_name' => (string) ($ap['doctor_name'] ?? ''),
                                                                        'when_label' => formatDate($ap['appointment_date']) . ' · ' . formatTime($ap['appointment_time']) . ' – ' . formatTime($ap['end_time'] ?? $ap['appointment_time']),
                                                                        'duration' => (int) ($ap['duration'] ?? 30),
                                                                        'status' => (string) ($ap['status'] ?? ''),
                                                                        'chair_number' => $ap['chair_number'] ?? null,
                                                                        'description' => $ap['description'] ?? null,
                                                                        'notes' => $ap['notes'] ?? null,
                                                                    ];
                                                                    $schedJson = htmlspecialchars(json_encode($schedPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                                                    ?>
                                                                    <button type="button"
                                                                        class="cal-slot-btn cal-slot-btn-table cal-slot-btn-scheduled-staff"
                                                                        data-scheduled="<?php echo $schedJson; ?>"
                                                                        onclick="dashStaffOpenScheduled(this)">
                                                                        <span class="staff-cal-slot-stack"><span class="staff-cal-name"><?php echo htmlspecialchars((string) $ap['patient_name']); ?></span><span class="staff-cal-treat"><?php echo htmlspecialchars((string) $ap['treatment_type']); ?></span></span>
                                                                    </button>
                                                                <?php elseif ($st === 'request' && is_array($sl['payload'])): ?>
                                                                    <?php
                                                                    $rq = $sl['payload'];
                                                                    $durR = max(5, (int) ($rq['duration_minutes'] ?? $staffCalSlotMinutes));
                                                                    $rEnd = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $rq['requested_date'] . ' ' . (strlen(trim((string) $rq['requested_time'])) === 5 ? $rq['requested_time'] . ':00' : $rq['requested_time']));
                                                                    $whenReq = formatDate($rq['requested_date']) . ' · ' . formatTime($rq['requested_time']);
                                                                    if ($rEnd) {
                                                                        $whenReq .= ' – ' . formatTime($rEnd->modify('+' . $durR . ' minutes')->format('H:i:s'));
                                                                    }
                                                                    $reqPayload = [
                                                                        'request_id' => (int) $rq['id'],
                                                                        'patient_name' => (string) ($rq['patient_name'] ?? ''),
                                                                        'patient_phone' => (string) ($rq['patient_phone'] ?? ''),
                                                                        'treatment_type' => (string) ($rq['treatment_type'] ?? ''),
                                                                        'doctor_name' => (string) ($rq['doctor_name'] ?? ''),
                                                                        'when_label' => $whenReq,
                                                                        'duration_minutes' => $durR,
                                                                        'description' => $rq['description'] ?? null,
                                                                    ];
                                                                    $reqJson = htmlspecialchars(json_encode($reqPayload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                                                    ?>
                                                                    <button type="button"
                                                                        class="cal-slot-btn cal-slot-btn-table cal-slot-btn-request"
                                                                        data-request="<?php echo $reqJson; ?>"
                                                                        onclick="dashStaffOpenRequest(this)">
                                                                        <span class="staff-cal-slot-stack"><span class="staff-cal-name"><?php echo htmlspecialchars((string) $rq['patient_name']); ?></span><span class="staff-cal-treat"><?php echo htmlspecialchars((string) $rq['treatment_type']); ?></span></span>
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button type="button" class="cal-slot-btn cal-slot-btn-table cal-slot-btn-past" disabled>—</button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-4 d-flex flex-column gap-3">
            <?php if (!empty($dashWaitingQueue)): ?>
                <div class="card dashboard-section-card dashboard-waiting-queue mb-0">
                    <div class="card-header dash-arrivals-card-header arrivals-hdr-blue arrivals-section-header border-0">
                        <div class="arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0 text-white"><i class="fas fa-hourglass-half me-2" aria-hidden="true"></i>Waiting Queue</h5>
                            </div>
                            <div class="flex-shrink-0 flex-shrink-min" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($dashWaitingQueue as $wq): ?>
                            <?php
                            $flexDays = dbColumnExists('waiting_queue', 'date_flexibility_days')
                                ? (int) ($wq['date_flexibility_days'] ?? 0)
                                : 0;
                            $prefDate = $wq['preferred_date'] ?? null;
                            $dateLine = $prefDate ? formatDate((string) $prefDate) : '—';
                            $treat = (string) ($wq['preferred_treatment'] ?? $wq['reason'] ?? '—');
                            ?>
                            <div class="dash-waiting-row">
                                <div class="dw-date-stack">
                                    <div class="dw-pref-date"><?php echo htmlspecialchars($dateLine); ?></div>
                                    <?php if ($flexDays > 0): ?>
                                        <div class="dw-flex-line">± <?php echo (int) $flexDays; ?> day<?php echo $flexDays === 1 ? '' : 's'; ?> flexibility</div>
                                    <?php endif; ?>
                                </div>
                                <div class="dw-patient"><?php echo htmlspecialchars((string) ($wq['patient_name'] ?? '')); ?></div>
                                <div class="dw-treat"><?php echo htmlspecialchars($treat); ?></div>
                                <div class="dw-resolve">
                                    <form method="post" action="queue/index.php" class="d-inline" onsubmit="return confirm('Resolve and remove this weekly request?');">
                                        <input type="hidden" name="weekly_queue_id" value="<?php echo (int) $wq['id']; ?>">
                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($dashCalReturnUrl); ?>">
                                        <button type="submit" name="resolve_weekly_queue" value="1" class="btn btn-success dash-wq-resolve-btn d-inline-flex align-items-center justify-content-center" title="Resolve" aria-label="Resolve request"><i class="fas fa-check" aria-hidden="true"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card dashboard-section-card mb-0 align-self-stretch">
                <div class="card-header dash-arrivals-card-header arrivals-hdr-blue arrivals-section-header border-0">
                    <div class="arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0 text-white"><i class="fas fa-calendar-day me-2" aria-hidden="true"></i>Today's Appointments</h5>
                        </div>
                        <div class="flex-shrink-0 flex-shrink-min" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($todayAppointmentsSidebar)): ?>
                        <div class="text-center py-4 px-3">
                            <i class="fas fa-calendar-check fa-2x text-muted mb-2"></i>
                            <p class="text-muted small mb-2">None scheduled for today<?php echo $dashboardRole === 'doctor' ? ' for you' : ' for this dentist'; ?>.</p>
                            <a href="appointments/add.php" class="btn btn-sm btn-primary">Book</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($todayAppointmentsSidebar as $apt): ?>
                            <div class="dash-today-appt-row">
                                <span class="side-time"><?php echo htmlspecialchars(formatTime($apt['appointment_time'])); ?></span>
                                <span class="dash-today-patient"><?php echo htmlspecialchars((string) $apt['patient_name']); ?></span>
                                <span class="dash-today-treat"><?php echo htmlspecialchars((string) $apt['treatment_type']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($dashboardRole === 'doctor' && !empty($dashInventoryNotices)): ?>
                <div class="card dashboard-section-card dashboard-inventory-status mb-0">
                    <div class="card-header dash-arrivals-card-header arrivals-hdr-blue arrivals-section-header border-0">
                        <div class="arrivals-section-header__inner align-items-center">
                            <div>
                                <h5 class="card-title mb-0 text-white"><i class="fas fa-boxes me-2" aria-hidden="true"></i>Inventory Status</h5>
                            </div>
                            <div class="flex-shrink-0 flex-shrink-min" aria-hidden="true"></div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($dashInventoryNotices as $invNotice): ?>
                            <div class="dash-inventory-status-row" role="status">
                                <span class="dash-inventory-status-name"><?php echo htmlspecialchars($invNotice['item_name']); ?></span>
                                <div class="dash-inventory-status-badge-wrap">
                                    <span class="badge <?php echo htmlspecialchars($invNotice['badge_class']); ?>"><?php echo htmlspecialchars($invNotice['status']); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Calendar modals -->
<div class="modal fade dash-cal-modal" id="dashCalModalAvailable" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title dash-cal-modal-heading-lg"><i class="fas fa-plus-circle me-2 dash-cal-modal-title-icon--success" aria-hidden="true"></i>Book this slot</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="dashCalBookForm">
                <div class="modal-body">
                    <input type="hidden" name="doctor_id" id="dashCalBookDoctorId">
                    <input type="hidden" name="appointment_date" id="dashCalBookDate">
                    <input type="hidden" name="appointment_time" id="dashCalBookTime">
                    <input type="hidden" name="duration" id="dashCalBookDuration">
                    <div class="dash-cal-detail-card mb-3">
                        <div class="dash-cal-label">When</div>
                        <div class="dash-cal-value" id="dashCalBookWhenLabel"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dashCalBookPatient">Patient <span class="text-danger">*</span></label>
                        <select class="form-select" id="dashCalBookPatient" required>
                            <option value="">Select patient…</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="dashCalBookTreatment">Visit type <span class="text-danger">*</span></label>
                        <select class="form-select" id="dashCalBookTreatment" required>
                            <option value="">Select visit type…</option>
                            <option value="Cleaning">Cleaning</option>
                            <option value="Filling">Filling</option>
                            <option value="Root Canal">Root Canal</option>
                            <option value="Extraction">Extraction</option>
                            <option value="Crown">Crown</option>
                            <option value="Bridge">Bridge</option>
                            <option value="Implant">Implant</option>
                            <option value="Whitening">Whitening</option>
                            <option value="Orthodontics">Orthodontics</option>
                            <option value="Consultation">Consultation</option>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="dashCalBookNotes">Notes (optional)</label>
                        <textarea class="form-control" id="dashCalBookNotes" rows="2"></textarea>
                    </div>
                    <div class="alert alert-danger d-none mt-3 mb-0 small" id="dashCalBookError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="dashCalBookSubmit">
                        <i class="fas fa-check me-1"></i> Save appointment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade dash-cal-modal" id="dashCalModalScheduled" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title dash-cal-modal-heading-lg"><i class="fas fa-calendar-check me-2 dash-cal-modal-title-icon--calendar" aria-hidden="true"></i>Appointment details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="dashCalScheduledBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <a class="btn btn-primary" id="dashCalScheduledViewLink" href="#">Open record</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade dash-cal-modal" id="dashCalModalRequest" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title dash-cal-modal-heading-lg"><i class="fas fa-inbox me-2 dash-cal-modal-title-icon--inbox" aria-hidden="true"></i>Booking request</h5>
                <button type="button" class="dash-cal-modal-x ms-auto" data-bs-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="dashCalRequestBody"></div>
            <div class="modal-footer flex-wrap gap-2">
                <button type="button" class="btn btn-danger ms-auto" id="dashCalRequestDecline">
                    <i class="fas fa-times me-1"></i> Deny
                </button>
                <button type="button" class="btn btn-success" id="dashCalRequestAccept">
                    <i class="fas fa-check me-1"></i> Accept
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){if(!window.chatbase||window.chatbase("getState")!=="initialized"){window.chatbase=(...arguments)=>{if(!window.chatbase.q){window.chatbase.q=[]}window.chatbase.q.push(arguments)};window.chatbase=new Proxy(window.chatbase,{get(target,prop){if(prop==="q"){return target.q}return(...args)=>target(prop,...args)}})}const onLoad=function(){const script=document.createElement("script");script.src="https://www.chatbase.co/embed.min.js";script.id="J9p5V3puetElIpM5CL1jK";script.domain="www.chatbase.co";document.body.appendChild(script)};if(document.readyState==="complete"){onLoad()}else{window.addEventListener("load",onLoad)}})();
</script>

<script>
var DASH_STAFF_REQ_ACTION = <?php echo json_encode(url('api/appointment_request_action.php')); ?>;
var DASH_STAFF_APPT_API = <?php echo json_encode(url('api/appointments.php')); ?>;
var DASH_STAFF_VIEW_APPT = <?php echo json_encode(url('appointments/view.php')); ?>;
var DASH_STAFF_PATIENTS = <?php echo json_encode($calendarPatientsForJs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

function dashStaffEscapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }
    var div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function dashStaffDetailRow(label, value) {
    var v = (value === null || value === undefined || value === '') ? '—' : String(value);
    return '<dt>' + dashStaffEscapeHtml(label) + '</dt><dd>' + dashStaffEscapeHtml(v) + '</dd>';
}

function dashStaffOpenBook(btn) {
    document.getElementById('dashCalBookError').classList.add('d-none');
    document.getElementById('dashCalBookDoctorId').value = btn.getAttribute('data-doctor-id') || '';
    document.getElementById('dashCalBookDate').value = btn.getAttribute('data-date') || '';
    document.getElementById('dashCalBookTime').value = btn.getAttribute('data-time') || '';
    document.getElementById('dashCalBookDuration').value = btn.getAttribute('data-duration') || '30';
    document.getElementById('dashCalBookWhenLabel').textContent = btn.getAttribute('data-when-label') || '';
    document.getElementById('dashCalBookTreatment').value = '';
    document.getElementById('dashCalBookNotes').value = '';
    document.getElementById('dashCalBookPatient').value = '';
    var m = document.getElementById('dashCalModalAvailable');
    if (m) {
        bootstrap.Modal.getOrCreateInstance(m).show();
    }
}

function dashStaffOpenScheduled(btn) {
    var raw = btn.getAttribute('data-scheduled');
    if (!raw) {
        return;
    }
    var p;
    try {
        p = JSON.parse(raw);
    } catch (e) {
        alert('Could not read appointment data.');
        return;
    }
    var html = '<dl class="slot-modal-summary mb-0">';
    html += dashStaffDetailRow('Patient', p.patient_name);
    html += dashStaffDetailRow('Phone', p.patient_phone);
    html += dashStaffDetailRow('Treatment', p.treatment_type);
    html += dashStaffDetailRow('Dentist', p.doctor_name);
    html += dashStaffDetailRow('When', p.when_label);
   
    html += dashStaffDetailRow('Status', p.status);
  
    html += dashStaffDetailRow('Notes', p.notes);
    html += '</dl>';
    document.getElementById('dashCalScheduledBody').innerHTML = html;
    document.getElementById('dashCalScheduledViewLink').href = DASH_STAFF_VIEW_APPT + '?id=' + encodeURIComponent(p.appointment_id);
    var m = document.getElementById('dashCalModalScheduled');
    if (m) {
        bootstrap.Modal.getOrCreateInstance(m).show();
    }
}

var dashStaffPendingRequestId = null;

function dashStaffOpenRequest(btn) {
    var raw = btn.getAttribute('data-request');
    if (!raw) {
        return;
    }
    var p;
    try {
        p = JSON.parse(raw);
    } catch (e) {
        alert('Could not read request data.');
        return;
    }
    dashStaffPendingRequestId = p.request_id;
    var html = '<dl class="slot-modal-summary mb-0">';
    html += dashStaffDetailRow('Patient', p.patient_name);
    html += dashStaffDetailRow('Phone', p.patient_phone);
    html += dashStaffDetailRow('Treatment', p.treatment_type);
    html += dashStaffDetailRow('Dentist', p.doctor_name);
    html += dashStaffDetailRow('Requested time', p.when_label);
    html += dashStaffDetailRow('Duration (min)', p.duration_minutes);
    html += dashStaffDetailRow('Patient notes', p.description);
    html += '</dl>';
    document.getElementById('dashCalRequestBody').innerHTML = html;
    document.getElementById('dashCalRequestAccept').disabled = false;
    document.getElementById('dashCalRequestDecline').disabled = false;
    var m = document.getElementById('dashCalModalRequest');
    if (m) {
        bootstrap.Modal.getOrCreateInstance(m).show();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    var bookForm = document.getElementById('dashCalBookForm');
    if (!bookForm) {
        return;
    }

    var sel = document.getElementById('dashCalBookPatient');
    if (sel) {
        sel.innerHTML = '<option value="">Select patient…</option>';
        DASH_STAFF_PATIENTS.forEach(function(p) {
            var o = document.createElement('option');
            o.value = p.id;
            o.textContent = p.full_name;
            sel.appendChild(o);
        });
    }

    var bsAvail = bootstrap.Modal.getOrCreateInstance(document.getElementById('dashCalModalAvailable'));

    bookForm.addEventListener('submit', function(ev) {
        ev.preventDefault();
        var errEl = document.getElementById('dashCalBookError');
        var btn = document.getElementById('dashCalBookSubmit');
        errEl.classList.add('d-none');
        errEl.textContent = '';

        var payload = {
            patient_id: parseInt(document.getElementById('dashCalBookPatient').value, 10),
            doctor_id: parseInt(document.getElementById('dashCalBookDoctorId').value, 10),
            appointment_date: document.getElementById('dashCalBookDate').value,
            appointment_time: document.getElementById('dashCalBookTime').value,
            duration: parseInt(document.getElementById('dashCalBookDuration').value, 10) || 30,
            treatment_type: document.getElementById('dashCalBookTreatment').value.trim(),
            description: document.getElementById('dashCalBookNotes').value.trim() || null,
            status: 'scheduled'
        };

        if (!payload.patient_id || !payload.treatment_type) {
            errEl.textContent = 'Choose a patient and a visit type.';
            errEl.classList.remove('d-none');
            return;
        }

        btn.disabled = true;
        fetch(DASH_STAFF_APPT_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            if (data.success) {
                bsAvail.hide();
                window.location.reload();
            } else {
                errEl.textContent = data.message || 'Could not save.';
                errEl.classList.remove('d-none');
            }
        })
        .catch(function() {
            btn.disabled = false;
            errEl.textContent = 'Network error.';
            errEl.classList.remove('d-none');
        });
    });

    function setReqLoading(loading) {
        document.getElementById('dashCalRequestAccept').disabled = loading;
        document.getElementById('dashCalRequestDecline').disabled = loading;
    }

    document.getElementById('dashCalRequestAccept').addEventListener('click', function() {
        if (!dashStaffPendingRequestId || !confirm('Confirm this appointment and notify the patient?')) {
            return;
        }
        setReqLoading(true);
        fetch(DASH_STAFF_REQ_ACTION, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'approve', request_id: dashStaffPendingRequestId })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            setReqLoading(false);
            if (data.success) {
                var elR = document.getElementById('dashCalModalRequest');
                var instR = elR ? bootstrap.Modal.getInstance(elR) : null;
                if (instR) {
                    instR.hide();
                }
                window.location.reload();
            } else {
                alert(data.message || 'Action failed.');
            }
        })
        .catch(function() {
            setReqLoading(false);
            alert('Network error.');
        });
    });

    document.getElementById('dashCalRequestDecline').addEventListener('click', function() {
        if (!dashStaffPendingRequestId || !confirm('Deny this request and notify the patient?')) {
            return;
        }
        setReqLoading(true);
        fetch(DASH_STAFF_REQ_ACTION, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'decline', request_id: dashStaffPendingRequestId })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            setReqLoading(false);
            if (data.success) {
                var elD = document.getElementById('dashCalModalRequest');
                var instD = elD ? bootstrap.Modal.getInstance(elD) : null;
                if (instD) {
                    instD.hide();
                }
                window.location.reload();
            } else {
                alert(data.message || 'Action failed.');
            }
        })
        .catch(function() {
            setReqLoading(false);
            alert('Network error.');
        });
    });
});
</script>

<?php include 'layouts/footer.php'; ?>
