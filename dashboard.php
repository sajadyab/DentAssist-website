<?php
// ==============================================
// Dental Clinic Management System - Staff Dashboard
// Version: 2.0
// ==============================================

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

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

$doctorTodayClause = '';
$todayApptParams = [$today];
$todayApptTypes = 's';
if ($dashboardRole === 'doctor') {
    $doctorTodayClause = ' AND a.doctor_id = ?';
    $todayApptParams[] = $dashboardUserId;
    $todayApptTypes .= 'i';
}

// Today's appointments (all clinic staff; scoped to doctor when logged in as doctor)
$todayAppointments = $db->fetchAll(
    "SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
     FROM appointments a 
     JOIN patients p ON a.patient_id = p.id 
     JOIN users u ON a.doctor_id = u.id 
     WHERE a.appointment_date = ? $doctorTodayClause
     ORDER BY a.appointment_time",
    $todayApptParams,
    $todayApptTypes
);

// Get subscription statistics (works with or without patients.subscription_status)
if (dbColumnExists('patients', 'subscription_status')) {
    $pendingSubscriptions = (int) ($db->fetchOne(
        "SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'pending'",
        [],
        ""
    )['count'] ?? 0);

    $activeSubscriptions = (int) ($db->fetchOne(
        "SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active'
           AND subscription_end_date IS NOT NULL AND subscription_end_date >= CURDATE()",
        [],
        ""
    )['count'] ?? 0);

    $expiringSubscriptions = (int) ($db->fetchOne(
        "SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active'
           AND subscription_end_date IS NOT NULL AND subscription_end_date >= CURDATE()
           AND subscription_end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
        [],
        ""
    )['count'] ?? 0);
} else {
    if (dbTableExists('subscription_payments')) {
        $pendingSubscriptions = (int) ($db->fetchOne(
            "SELECT COUNT(DISTINCT patient_id) as count FROM subscription_payments WHERE status = 'pending'",
            [],
            ""
        )['count'] ?? 0);
    } else {
        $pendingSubscriptions = 0;
    }

    $noPendingSub = dbTableExists('subscription_payments')
        ? 'AND NOT EXISTS (
            SELECT 1 FROM subscription_payments sp
            WHERE sp.patient_id = p.id AND sp.status = \'pending\'
        )'
        : '';

    $activeSubscriptions = (int) ($db->fetchOne(
        "SELECT COUNT(*) as count FROM patients p
         WHERE p.subscription_type != 'none'
           AND p.subscription_end_date IS NOT NULL
           AND p.subscription_end_date >= CURDATE()
           $noPendingSub",
        [],
        ""
    )['count'] ?? 0);

    $expiringSubscriptions = (int) ($db->fetchOne(
        "SELECT COUNT(*) as count FROM patients p
         WHERE p.subscription_type != 'none'
           AND p.subscription_end_date IS NOT NULL
           AND p.subscription_end_date >= CURDATE()
           AND p.subscription_end_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
           $noPendingSub",
        [],
        ""
    )['count'] ?? 0);
}

$subscriptionRevenue = 0.0;
if (dbTableExists('subscription_payments')) {
    $subscriptionRevenue = (float) ($db->fetchOne(
        "SELECT SUM(amount) as total FROM subscription_payments WHERE status = 'completed'",
        [],
        ""
    )['total'] ?? 0);
} else {
    $subscriptionRevenue = (float) ($db->fetchOne(
        "SELECT COALESCE(SUM(paid_amount), 0) AS total FROM invoices
         WHERE payment_status = 'paid' AND notes IS NOT NULL AND notes LIKE '%Subscription%'",
        [],
        ""
    )['total'] ?? 0);
}

// Summary stats (appointment counts respect doctor scope when role is doctor)
if ($dashboardRole === 'doctor') {
    $statsUpcoming = (int) ($db->fetchOne(
        "SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= ? AND status NOT IN ('cancelled', 'completed') AND doctor_id = ?",
        [$today, $dashboardUserId],
        "si"
    )['count'] ?? 0);
    $statsCompletedToday = (int) ($db->fetchOne(
        "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND status = 'completed' AND doctor_id = ?",
        [$today, $dashboardUserId],
        "si"
    )['count'] ?? 0);
} else {
    $statsUpcoming = (int) ($db->fetchOne(
        "SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= ? AND status NOT IN ('cancelled', 'completed')",
        [$today],
        "s"
    )['count'] ?? 0);
    $statsCompletedToday = (int) ($db->fetchOne(
        "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND status = 'completed'",
        [$today],
        "s"
    )['count'] ?? 0);
}

$stats = [
    'today_appointments' => count($todayAppointments),
    'upcoming_appointments' => $statsUpcoming,
    'completed_today' => $statsCompletedToday,
    'pending_subscriptions' => $pendingSubscriptions,
    'active_subscriptions' => $activeSubscriptions,
    'subscription_revenue' => $subscriptionRevenue
];

if (dbTableExists('appointment_requests')) {
    $dashOnlineRequestsBaseSql = "SELECT ar.*, p.full_name AS patient_name, p.phone AS patient_phone, u.full_name AS doctor_name
         FROM appointment_requests ar
         INNER JOIN patients p ON p.id = ar.patient_id
         INNER JOIN users u ON u.id = ar.doctor_id";
    if ($dashboardRole === 'doctor') {
        $dashOnlineRequests = $db->fetchAll(
            $dashOnlineRequestsBaseSql . ' WHERE ar.doctor_id = ? ORDER BY ar.requested_date ASC, ar.requested_time ASC, ar.id ASC',
            [$dashboardUserId],
            'i'
        );
    } else {
        $dashOnlineRequests = $db->fetchAll(
            $dashOnlineRequestsBaseSql . ' ORDER BY ar.requested_date ASC, ar.requested_time ASC, ar.id ASC',
            []
        );
    }
} else {
    $dashOnlineRequests = [];
}
$dashOnlineRequestCount = count($dashOnlineRequests);

$calendarDoctorOptions = [];
if ($dashboardRole !== 'doctor') {
    $calendarDoctorOptions = $db->fetchAll(
        "SELECT id, full_name FROM users WHERE role = 'doctor' AND COALESCE(is_active, 1) = 1 ORDER BY full_name"
    );
}
$defaultCalDoctorId = $dashboardRole === 'doctor'
    ? $dashboardUserId
    : (int) (($calendarDoctorOptions[0]['id'] ?? 0));

$calendarPatientsForJs = $db->fetchAll(
    'SELECT id, full_name FROM patients ORDER BY full_name LIMIT 800'
);

require_once __DIR__ . '/includes/dashboard_staff_calendar.php';

$dashWaitingQueue = [];
if (dbTableExists('waiting_queue') && $staffCalDoctorId > 0) {
    $dashWaitingQueue = $db->fetchAll(
        "SELECT wq.*, COALESCE(p.full_name, wq.patient_name) AS patient_name, p.phone AS patient_phone, u.full_name AS doctor_name
         FROM waiting_queue wq
         LEFT JOIN patients p ON wq.patient_id = p.id
         LEFT JOIN users u ON u.id = wq.doctor_id
         WHERE wq.queue_type = 'weekly' AND wq.status = 'waiting' AND wq.doctor_id = ?
         ORDER BY wq.preferred_date IS NULL, wq.preferred_date ASC, wq.joined_at ASC",
        [$staffCalDoctorId],
        'i'
    );
}

$todayApptSidebarParams = [$today];
$todayApptSidebarTypes = 's';
$todayApptSidebarClause = '';
if ($staffCalDoctorId > 0) {
    $todayApptSidebarClause = ' AND a.doctor_id = ?';
    $todayApptSidebarParams[] = $staffCalDoctorId;
    $todayApptSidebarTypes .= 'i';
}
$todayAppointmentsSidebar = $db->fetchAll(
    "SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
     FROM appointments a 
     JOIN patients p ON a.patient_id = p.id 
     JOIN users u ON a.doctor_id = u.id 
     WHERE a.appointment_date = ? $todayApptSidebarClause
     ORDER BY a.appointment_time",
    $todayApptSidebarParams,
    $todayApptSidebarTypes
);

/** @var list<array{item_name: string, status: string, badge_class: string}> $dashInventoryNotices */
$dashInventoryNotices = [];
if ($dashboardRole === 'doctor' && dbTableExists('inventory')) {
    $invCandidates = $db->fetchAll(
        "SELECT item_name, quantity, reorder_level, expiry_date FROM inventory WHERE
         (expiry_date IS NOT NULL AND expiry_date <> '' AND expiry_date <> '0000-00-00' AND expiry_date < CURDATE())
         OR (expiry_date IS NOT NULL AND expiry_date <> '' AND expiry_date <> '0000-00-00'
             AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY))
         OR (quantity <= reorder_level AND quantity > 0)"
    );
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

<style>
/* ============================================
   Global Dashboard Styles
   ============================================ */
:root {
    --primary: #667eea;
    --primary-dark: #5a67d8;
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
    --info-gradient: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    --danger-gradient: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.dashboard-header {
    margin-bottom: 0.65rem;
}

.dashboard-header h1 {
    font-size: 1.65rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0;
}

/* Stats Cards — extra space below before calendar / sidebar */
.dashboard-summary-row {
    display: flex;
    flex-wrap: nowrap;
    gap: 0.6rem;
    margin-bottom: 0;
    margin-top: 0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    
}

.dashboard-summary-item {
    flex: 1 1 0;
    min-width: 3.5rem;
    border: none;
    border-radius: 12px;
    overflow: hidden;
    position: relative;
    pointer-events: none;
    user-select: none;
    box-shadow: 0 2px 10px rgba(15, 23, 42, 0.06);
}

.dashboard-summary-item::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.12) 0%, rgba(255,255,255,0) 100%);
    pointer-events: none;
}

.dashboard-summary-item .inner {
    padding: 0.55rem 0.55rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.13rem;
    min-height: 6.5rem;
}

.dashboard-summary-item h6 {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 1;
    opacity: 0.95;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
    text-align: center;
}

.dashboard-summary-item .summary-value-row {
    display: inline-flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
    gap: 0.45rem;
}

.dashboard-summary-item .summary-icon {
    flex-shrink: 0;
    opacity: 0.63;
    font-size: 1.6rem;
    line-height: 1;
}

.dashboard-summary-item .stat-value {
    font-size: 1rem;
    font-weight: 600;
    line-height: 1;
    margin: 0;
    text-align: center;
    font-variant-numeric: tabular-nums;
}

/* Quick Actions */
.quick-actions-card {
    border-radius: 20px;
    border: none;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.quick-action-btn {
    transition: all 0.3s ease;
    border-radius: 12px;
    padding: 10px 20px;
    font-weight: 500;
    font-size: 0.9rem;
}

.quick-action-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.quick-actions-card a.btn-warning.quick-action-btn,
.quick-actions-card a.btn-warning.quick-action-btn i {
    color: #fff !important;
}

.quick-actions-card a.btn-warning.quick-action-btn:hover,
.quick-actions-card a.btn-warning.quick-action-btn:hover i {
    color: #fff !important;
}

/* Today's Appointments Section */
.today-appointments-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.today-appointments-card .card-header {
    background: var(--primary-gradient);
    color: white;
    border: none;
    padding: 1rem 1.5rem;
}

.today-appointments-card .card-header h5 {
    font-weight: 600;
    margin-bottom: 0;
}

.appointment-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1rem;
}

.appointment-card {
    background: white;
    border-radius: 16px;
    padding: 1rem;
    transition: all 0.3s ease;
    border-left: 4px solid var(--primary);
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    position: relative;
    overflow: hidden;
}

.appointment-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.appointment-time {
    font-size: 1rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.appointment-patient {
    font-size: 1rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 6px;
}

.appointment-patient i {
    margin-right: 6px;
    color: var(--primary);
}

.appointment-doctor {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 4px;
}

.appointment-treatment {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 8px;
}

.appointment-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Upcoming Appointments Section */
.upcoming-appointments-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.upcoming-list {
    max-height: 400px;
    overflow-y: auto;
}

.upcoming-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
    transition: all 0.3s ease;
}

.upcoming-item:hover {
    background: #f8f9fa;
    padding-left: 8px;
}

.upcoming-date {
    min-width: 90px;
    font-weight: 600;
    color: var(--primary);
}

.upcoming-patient {
    flex: 1;
    font-weight: 500;
}

.upcoming-time {
    min-width: 70px;
    color: #6c757d;
    font-size: 0.85rem;
}

/* Dashboard section cards */
.dashboard-section-card {
    border: none;
    border-radius: 20px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
    margin-bottom: 1.25rem;
}

.dash-queue-page .dashboard-section-card {
    border: 1px solid var(--cal-line);
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
}

.dashboard-section-card .card-header {
    background: #fff;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    padding: 0.85rem 1.15rem;
}

.dash-queue-page .dashboard-section-card .card-header {
    border-bottom: 1px solid var(--cal-line);
}

.dash-queue-page .dashboard-section-card .card-header h5 i.fa-calendar-day {
    color: var(--queue-card-header-icon) !important;
}

/* Sidebar queue + today's cards: subtler corners */
.dash-queue-page .col-lg-4 .dashboard-section-card {
    border-radius: 18px;
}

/* Blue arrivals-style headers: icon matches white title (override yellow calendar icon rule) */
.dash-queue-page .dashboard-section-card .card-header.dash-arrivals-card-header h5.card-title i {
    color: #fff !important;
}

.dashboard-section-card .card-header h5 {
    font-size: 1rem;
    font-weight: 600;
    margin: 0;
    color: #2c3e50;
}

.dashboard-section-card .card-header .text-muted {
    font-size: 0.8rem;
    font-weight: 500;
}

/* —— Dashboard calendar: match patient/queue.php booking grid tones —— */
.dash-queue-page {
    --cal-accent: #667eea;
    --cal-accent-soft: rgba(102, 126, 234, 0.12);
    --cal-slate: #334155;
    --cal-muted: #64748b;
    --cal-line: #e2e8f0;
    --queue-card-header-icon: rgb(244, 247, 110);
    --inv-badge-expiring: rgb(250, 236, 82);
    --inv-badge-expired-bg: rgba(253, 52, 72, 0.89);
    --inv-badge-low-bg: rgb(140, 199, 241);
}

.dash-queue-page .queue-panel-card-header {
    background: #fff !important;
    border-bottom: none !important;
    padding-top: 0.5rem !important;
    padding-bottom: 0.5rem !important;
}

.dash-queue-page .queue-panel-card-header .card-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: #212529;
    line-height: 1.25;
}

.dash-queue-page .queue-panel-card-header .card-title i {
    color: var(--queue-card-header-icon) !important;
}

.dash-queue-page .queue-calendar-card {
    border-radius: 20px;
    overflow: hidden;
}

.dash-queue-page .cal-nav .btn-cal {
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.75rem;
    padding: 0.3rem 0.5rem;
    border: 1px solid var(--cal-line);
    background: #fff;
    color: var(--cal-slate);
}

.dash-queue-page .cal-nav .btn-cal:hover {
    background: var(--cal-accent-soft);
    border-color: rgba(102, 126, 234, 0.25);
    color: #4f46e5;
}

.dash-queue-page .cal-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 6px 10px;
    font-size: 0.68rem;
    color: var(--cal-muted);
    margin-bottom: 8px;
}

.dash-queue-page .cal-legend > span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.dash-queue-page .cal-dot {
    width: 9px;
    height: 9px;
    border-radius: 3px;
    flex-shrink: 0;
}

.dash-queue-page .cal-dot-free {
    background: #22c55e;
    box-shadow: 0 0 0 1.5px rgba(34, 197, 94, 0.35);
}

.dash-queue-page .cal-dot-busy {
    background: #93c5fd;
    box-shadow: 0 0 0 1.5px rgba(59, 130, 246, 0.35);
}

.dash-queue-page .cal-dot-request {
    background: #fcd34d;
    box-shadow: 0 0 0 1.5px rgba(251, 191, 36, 0.45);
}

.dash-queue-page .booking-calendar-wrap {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--cal-line);
    background: #fff;
    box-shadow: 0 2px 12px rgba(15, 23, 42, 0.04);
    -webkit-overflow-scrolling: touch;
    padding: 6px;
}

.calendar-container {
    background: transparent;
    border-radius: 0;
    padding: 0;
    box-shadow: none;
    height: 100%;
}

/* patient/queue.php — main booking table (full parity) */
.dash-queue-page .queue-main-calendar .booking-calendar-wrap {
    padding: 0;
    overflow: visible;
    -webkit-overflow-scrolling: auto;
}

.dash-queue-page .staff-cal-view-toggle .btn-cal.active {
    background: var(--cal-accent-soft);
    border-color: rgba(102, 126, 234, 0.35);
    color: #4f46e5;
}

.booking-cal-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.72rem;
    table-layout: fixed;
}

.booking-cal-table thead th {
    background: linear-gradient(180deg, rgba(102, 126, 234, 0.14) 0%, rgba(118, 75, 162, 0.08) 100%);
    border-bottom: 2px solid rgba(102, 126, 234, 0.22);
    border-right: 1px solid var(--cal-line);
    color: #1e293b;
    font-weight: 700;
    text-align: center;
    vertical-align: middle;
    padding: 8px 6px;
    font-size: 0.68rem;
    letter-spacing: 0.02em;
}

.booking-cal-table thead th:first-child {
    width: 4.75rem;
    text-align: left;
    padding-left: 10px;
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border-right: 2px solid rgba(102, 126, 234, 0.15);
}

.booking-cal-table thead th:last-child {
    border-right: none;
}

.booking-cal-table thead th .booking-cal-th-day {
    display: block;
    text-transform: uppercase;
    font-size: 0.62rem;
    color: #475569;
}

.booking-cal-table thead th .booking-cal-th-date {
    display: block;
    font-weight: 600;
    color: var(--cal-muted);
    font-size: 0.65rem;
    margin-top: 2px;
    text-transform: none;
}

.booking-cal-table tbody td {
    border-right: 1px solid var(--cal-line);
    border-bottom: 1px solid var(--cal-line);
    vertical-align: middle;
    padding: 0;
}

.booking-cal-table tbody td:last-child {
    border-right: none;
}

.booking-cal-table tbody tr:nth-child(even) td {
    background: #fafbfd;
}

.booking-cal-table tbody tr:nth-child(even) th.cal-time-cell {
    background: #f1f5f9;
}

.booking-cal-table tbody tr:hover td:not(.cal-table-empty) {
    background: rgba(102, 126, 234, 0.04);
}

.booking-cal-table .cal-time-cell {
    background: #f8fafc;
    font-weight: 700;
    font-size: 0.68rem;
    color: #475569;
    padding: 6px 10px;
    white-space: nowrap;
    border-right: 2px solid rgba(102, 126, 234, 0.12);
}

.cal-table-cell-inner {
    padding: 5px 4px;
    text-align: center;
    min-height: 2.35rem;
}

.cal-table-empty {
    background: repeating-linear-gradient(
        -45deg,
        #f8fafc,
        #f8fafc 4px,
        #f1f5f9 4px,
        #f1f5f9 8px
    ) !important;
    color: #cbd5e1;
    font-size: 0.85rem;
    text-align: center;
    padding: 8px 4px;
}

.cal-slot-hint {
    text-align: center;
    color: #94a3b8;
    font-size: 0.75rem;
    line-height: 1.35;
}

.cal-slot-btn-free.cal-slot-btn-table {
    font-size: 1.05rem;
    line-height: 1;
    font-weight: 800;
}

.cal-slot-btn-free.cal-slot-btn-table.staff-cal-slot-empty {
    font-size: 0;
    line-height: 0;
    font-weight: 600;
    color: transparent;
}

.cal-slot-btn-table {
    width: 100%;
    min-height: 2.15rem;
    font-size: 0.62rem;
    font-weight: 700;
    border-radius: 6px;
    padding: 6px 4px;
    line-height: 1.15;
}

.cal-slot-btn {
    width: 100%;
    border: none;
    border-radius: 5px;
    padding: 4px 2px;
    font-size: 0.6rem;
    font-weight: 700;
    line-height: 1.15;
    cursor: pointer;
    transition: background .15s ease, transform .1s ease, box-shadow .15s ease;
}

.cal-slot-btn-free {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #6ee7b7;
    box-shadow: 0 1px 2px rgba(6, 95, 70, 0.06);
}

.cal-slot-btn-free:hover {
    background: #a7f3d0;
    border-color: #34d399;
    color: #064e3b;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(16, 185, 129, 0.2);
}

.cal-slot-btn-busy {
    cursor: not-allowed;
    color: #1d4ed8;
    background: #dbeafe;
    border: 1px solid #93c5fd;
    font-weight: 600;
}

.cal-slot-btn-past {
    cursor: not-allowed;
    color: #94a3b8;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    font-weight: 600;
    opacity: 0.55;
}

/* Staff: scheduled cell — same blue tone as queue “taken”, clickable */
.cal-slot-btn-scheduled-staff {
    cursor: pointer;
    color: #1d4ed8;
    background: #dbeafe;
    border: 1px solid #93c5fd;
    font-weight: 600;
}

.cal-slot-btn-scheduled-staff:hover {
    background: #bfdbfe;
    border-color: #60a5fa;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.15);
}

/* Staff: request — calm yellow, black text */
.cal-slot-btn-request {
    cursor: pointer;
    color: #000;
    background: #fffbeb;
    border: 1px solid #fde68a;
    font-weight: 600;
}

.cal-slot-btn-request:hover {
    background: #fef3c7;
    border-color: #fcd34d;
    color: #000;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(251, 191, 36, 0.2);
}

.staff-cal-slot-stack {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    text-align: center;
    line-height: 1.15;
    width: 100%;
}

.staff-cal-slot-stack .staff-cal-name {
    font-size: 0.62rem;
    font-weight: 700;
    word-break: break-word;
}

.staff-cal-slot-stack .staff-cal-treat {
    font-size: 0.58rem;
    font-weight: 600;
    word-break: break-word;
}

.cal-slot-btn-scheduled-staff .staff-cal-treat {
    color: #1e40af;
}

.cal-slot-btn-request .staff-cal-slot-stack,
.cal-slot-btn-request .staff-cal-name,
.cal-slot-btn-request .staff-cal-treat {
    color: #000;
}

/* Calendar toolbar: black arrows + date weight */
.staff-cal-nav-arrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #111;
    text-decoration: none;
    padding: 0.2rem 0.45rem;
    line-height: 1;
    border-radius: 6px;
    transition: background 0.15s ease, color 0.15s ease;
}

.staff-cal-nav-arrow:hover {
    color: #000;
    background: rgba(15, 23, 42, 0.06);
}

.staff-cal-date-label {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--cal-slate, #1e293b);
    letter-spacing: 0.01em;
}

/* Match appointments/index — Scheduled arrivals card header (compact) */
.dash-queue-page .dash-arrivals-card-header.arrivals-section-header {
    display: flex;
    align-items: center;
    padding: 0.45rem 0.85rem;
}

.dash-queue-page .dash-arrivals-card-header.arrivals-section-header .arrivals-section-header__inner {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 0.4rem;
    width: 100%;
    /* Shared row height: matches Waiting Queue (btn-sm); Today’s title centers in the same band */
    min-height: 2.5rem;
}

.dash-queue-page .dash-arrivals-card-header .card-title {
    font-size: 0.95rem;
    font-weight: 600;
    line-height: 1.2;
}

.dash-queue-page .dash-arrivals-card-header.arrivals-hdr-blue {
    background: linear-gradient(135deg, rgb(142, 203, 244) 0%, rgb(85, 189, 245) 100%);
    color: #fff;
}

/* Sidebar: waiting queue row + today grid */
.dash-waiting-row {
    display: grid;
    grid-template-columns: minmax(5.5rem, 1fr) minmax(6rem, 1.25fr) minmax(5rem, 1fr) auto;
    gap: 0.45rem 0.65rem;
    align-items: center;
    padding: 0.9rem 0.9rem;
    min-height: 3.5rem;
    border-bottom: 1px solid var(--cal-line);
    font-size: 0.78rem;
}

.dash-waiting-row .dw-date-stack {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.dash-waiting-row .dw-pref-date {
    font-weight: 600;
    font-size: 0.72rem;
    line-height: 1.2;
    color: #334155;
}

.dash-waiting-row .dw-flex-line {
    font-size: 0.68rem;
    color: #64748b;
    line-height: 1.2;
}

.dash-waiting-row .dw-patient {
    font-weight: 600;
    word-break: break-word;
}

.dash-waiting-row .dw-treat {
    font-size: 0.72rem;
    color: #475569;
    word-break: break-word;
}

.dash-waiting-row .dw-resolve .dash-wq-resolve-btn {
    width: 1.1rem;
    height: 1.1rem;
    min-width: 1rem;
    padding: 0 !important;
    font-size: 0.7rem;
    line-height: 1;
    border-radius: 30%;
}

.dash-waiting-row:last-child {
    border-bottom: none;
}

.dash-today-appt-row {
    display: grid;
    grid-template-columns: 4.5rem minmax(0, 1fr) minmax(0, 1fr);
    gap: 0.5rem 1.25rem;
    align-items: center;
    padding: 0.9rem 1rem;
    min-height: 3.25rem;
    border-bottom: 1px solid var(--cal-line);
    font-size: 0.9rem;
}

.dash-today-appt-row:last-child {
    border-bottom: none;
}

.dash-today-appt-row .side-time {
    font-weight: 700;
    color: #111;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.dash-today-appt-row .dash-today-patient {
    font-weight: 600;
    color: #1e293b;
    word-break: break-word;
    min-width: 0;
}

.dash-today-appt-row .dash-today-treat {
    font-weight: 500;
    color: #475569;
    word-break: break-word;
    min-width: 0;
}

/* Inventory Status (doctor sidebar) — row rhythm matches Waiting Queue; badges match inventory/index table */
.dash-inventory-status-row {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(8.25rem, 8.25rem);
    gap: 0.5rem 0.75rem;
    align-items: center;
    padding: 0.9rem 0.9rem;
    min-height: 3.5rem;
    border-bottom: 1px solid var(--cal-line);
    font-size: 0.875rem;
    pointer-events: none;
    user-select: none;
}

.dash-inventory-status-badge-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
    min-width: 0;
}

.dash-inventory-status-row:last-child {
    border-bottom: none;
}

.dash-inventory-status-name {
    font-weight: 600;
    color: #334155;
    word-break: break-word;
    min-width: 0;
}

.dash-inventory-status-row .badge {
    font-size: 0.75rem;
    font-weight: 600;
    padding: 0.35em 0.55em;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

.dash-queue-page .badge-inv-expired {
    background-color: var(--inv-badge-expired-bg);
    color: #000;
}

.dash-queue-page .badge-inv-expiring {
    background-color: var(--inv-badge-expiring);
    color: #0f172a;
}

.dash-queue-page .badge-inv-low {
    background-color: var(--inv-badge-low-bg);
    color: rgb(7, 13, 23);
}

@media (max-width: 576px) {
    .dash-waiting-row {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 0.35rem;
    }
    .dash-waiting-row .dw-resolve {
        align-self: flex-end;
    }
    .dash-today-appt-row {
        grid-template-columns: 1fr;
        gap: 0.2rem;
    }
    .dash-today-appt-row .side-time {
        font-size: 0.85rem;
    }
    .dash-inventory-status-row {
        grid-template-columns: 1fr;
        gap: 0.35rem;
    }
    .dash-inventory-status-badge-wrap {
        justify-content: flex-start;
    }
}

/* Modals — align with patient/queue #slotModal */
.dash-cal-modal .modal-content {
    border-radius: 14px;
    border: none;
    box-shadow: 0 12px 40px rgba(15, 23, 42, 0.12);
    overflow: hidden;
}

.dash-cal-modal .modal-header {
    border-bottom: 1px solid #e2e8f0;
    background: linear-gradient(180deg, rgba(102, 126, 234, 0.10) 0%, #fff 100%);
    padding: 0.85rem 1rem;
}

.dash-cal-modal .modal-title {
    font-weight: 600;
    font-size: 1rem;
    color: #2c3e50;
}

.dash-cal-modal .modal-title.dash-cal-modal-heading-lg {
    font-size: 1.3rem;
    font-weight: 600;
    line-height: 1.35;
}

.dash-cal-modal .dash-cal-modal-x {
    color: #64748b;
    border: none;
    background: transparent;
    padding: 0.35rem 0.5rem;
    line-height: 1;
    font-size: 1.5rem;
    opacity: 0.85;
}

.dash-cal-modal .dash-cal-modal-x:hover {
    color: #1e293b;
    opacity: 1;
}

.dash-cal-modal .modal-body {
    padding: 1rem 1.1rem;
}

.dash-cal-modal .slot-modal-summary {
    background: #f8fafc;
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 0;
    border: 1px solid #e2e8f0;
}

.dash-cal-modal .slot-modal-summary dt {
    font-size: 0.68rem;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 2px;
    letter-spacing: 0.03em;
}

.dash-cal-modal .slot-modal-summary dd {
    margin: 0 0 10px 0;
    font-weight: 600;
    color: #1e293b;
    font-size: 0.9rem;
}

.dash-cal-modal .slot-modal-summary dd:last-child {
    margin-bottom: 0;
}

.dash-cal-modal .dash-cal-detail-card {
    background: #f8fafc;
    border-radius: 10px;
    padding: 0.75rem 0.9rem;
    margin-bottom: 0.75rem;
    border: 1px solid #e2e8f0;
}

.dash-cal-modal .dash-cal-detail-card:last-child {
    margin-bottom: 0;
}

.dash-cal-modal .dash-cal-label {
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #64748b;
    font-weight: 600;
    margin-bottom: 0.15rem;
}

.dash-cal-modal .dash-cal-value {
    font-size: 0.9rem;
    color: #1e293b;
    font-weight: 600;
}

.dash-cal-modal .modal-footer {
    border-top: 1px solid #e2e8f0;
    padding: 0.85rem 1rem;
    background: #fafbff;
}

/* Doctor Stats */
.doctor-stats-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
    overflow: hidden;
}

.doctor-stats-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.doctor-stats-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.doctor-stats-list li:last-child {
    border-bottom: none;
}

.doctor-name {
    font-weight: 500;
    color: #2c3e50;
}

.doctor-count {
    background: var(--primary-gradient);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 992px) {
    .appointment-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .dashboard-summary-item h6 {
        font-size: 0.52rem;
    }
    .dashboard-summary-item .stat-value {
        font-size: 0.82rem;
    }
    
    .appointment-grid {
        grid-template-columns: 1fr;
    }
    
    .upcoming-item {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .upcoming-date, .upcoming-time {
        font-size: 0.8rem;
    }
}

@media (max-width: 576px) {
    .quick-action-btn {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
}

/* Custom Scrollbar */
.upcoming-list::-webkit-scrollbar {
    width: 6px;
}

.upcoming-list::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.upcoming-list::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

.upcoming-list::-webkit-scrollbar-thumb:hover {
    background: var(--primary-dark);
}

/* Dashboard — online booking requests (collapsed under Quick Actions) */
.dashboard-online-requests-panel {
    border-radius: 16px;
    border: 1px solid rgba(102, 126, 234, 0.22);
    background: #fff;
    overflow: hidden;
}
.dashboard-online-requests-panel .panel-inner-head {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.12) 0%, rgba(118, 75, 162, 0.06) 100%);
    border-bottom: 1px solid rgba(102, 126, 234, 0.15);
    padding: 0.65rem 1rem;
    font-weight: 600;
    font-size: 0.9rem;
    color: #1e293b;
}
.dashboard-online-requests-table {
    margin-bottom: 0;
    font-size: 0.8125rem;
}
.dashboard-online-requests-table thead th {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    font-weight: 600;
    background: #e7f1ff;
    color: #084298;
    border-bottom: 2px solid #0d6efd;
    white-space: nowrap;
    padding: 0.5rem 0.45rem;
}
.dashboard-online-requests-table tbody td {
    padding: 0.5rem 0.45rem;
    vertical-align: middle;
}
.dashboard-online-requests-table .dor-notes {
    max-width: 12rem;
    max-height: 3.2rem;
    overflow-y: auto;
    font-size: 0.78rem;
    line-height: 1.35;
}
@media (max-width: 991px) {
    .dashboard-online-requests-table .dor-notes { max-width: none; }
}

.dashboard-side-list {
    max-height: 340px;
    overflow-y: auto;
    margin: 0;
    padding: 0 1rem 0.5rem;
}

.dashboard-side-list-item {
    padding: 0.65rem 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.875rem;
}

.dashboard-side-list-item:last-child {
    border-bottom: none;
}

.dashboard-side-list .side-time {
    font-weight: 700;
    color: var(--primary);
    font-size: 0.8rem;
}

.dashboard-side-list .side-status {
    font-size: 0.72rem;
    font-weight: 600;
    color: #6c757d;
    text-transform: capitalize;
}

.dashboard-waiting-queue .wq-meta {
    font-size: 0.78rem;
    color: #6c757d;
}
</style>

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
                        <a href="assistant_subscriptions.php" class="btn btn-warning quick-action-btn text-white">
                            <i class="fas fa-crown me-2"></i>
                            Pending Subscriptions
                            <?php if ($pendingSubscriptions > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $pendingSubscriptions; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="patients/add.php" class="btn btn-primary quick-action-btn">
                            <i class="fas fa-user-plus me-2"></i> Add Patient
                        </a>
                        <a href="appointments/add.php" class="btn btn-success quick-action-btn">
                            <i class="fas fa-calendar-plus me-2"></i> Book Appointment
                        </a>
                        <a href="billing/invoices.php" class="btn btn-info quick-action-btn text-white">
                            <i class="fas fa-file-invoice-dollar me-2"></i> View Invoices
                        </a>
                        <button type="button"
                            class="btn btn-secondary quick-action-btn text-white"
                            style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;"
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
        <div class="dashboard-summary-item bg-primary text-white">
            <div class="inner">
                <h6 class="text-white">Today's Appointments</h6>
                <div class="summary-value-row">
                    <i class="fas fa-calendar-day summary-icon text-white" aria-hidden="true"></i>
                    <p class="stat-value text-white mb-0"><?php echo (int) $stats['today_appointments']; ?></p>
                </div>
            </div>
        </div>
        <div class="dashboard-summary-item text-white" style="background: var(--info-gradient);">
            <div class="inner">
                <h6 class="text-white">Upcoming Appointments</h6>
                <div class="summary-value-row">
                    <i class="fas fa-calendar-alt summary-icon text-white" aria-hidden="true"></i>
                    <p class="stat-value text-white mb-0"><?php echo (int) $stats['upcoming_appointments']; ?></p>
                </div>
            </div>
        </div>
        <div class="dashboard-summary-item text-white" style="background: var(--warning-gradient);">
            <div class="inner">
                <h6 class="text-white">Completed Today</h6>
                <div class="summary-value-row">
                    <i class="fas fa-check-circle summary-icon text-white" aria-hidden="true"></i>
                    <p class="stat-value text-white mb-0"><?php echo (int) $stats['completed_today']; ?></p>
                </div>
            </div>
        </div>
        <div class="dashboard-summary-item text-white" style="background: var(--success-gradient);">
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
                                    <select name="cal_doctor_id" id="dashCalDoctorSelect" class="form-select form-select-sm" onchange="this.form.submit()" style="max-width: 22rem;">
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
                            <div class="flex-shrink-0" style="min-width: 1px;" aria-hidden="true"></div>
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
                        <div class="flex-shrink-0" style="min-width: 1px;" aria-hidden="true"></div>
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
                            <div class="flex-shrink-0" style="min-width: 1px;" aria-hidden="true"></div>
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
                <h5 class="modal-title dash-cal-modal-heading-lg"><i class="fas fa-plus-circle me-2" style="color:#22c55e;" aria-hidden="true"></i>Book this slot</h5>
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
                <h5 class="modal-title dash-cal-modal-heading-lg"><i class="fas fa-calendar-check me-2" style="color:#667eea;" aria-hidden="true"></i>Appointment details</h5>
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
                <h5 class="modal-title dash-cal-modal-heading-lg"><i class="fas fa-inbox me-2" style="color:#d97706;" aria-hidden="true"></i>Booking request</h5>
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