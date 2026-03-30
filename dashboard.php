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

// Get today's date
$today = date('Y-m-d');

// Get today's appointments
$todayAppointments = $db->fetchAll(
    "SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
     FROM appointments a 
     JOIN patients p ON a.patient_id = p.id 
     JOIN users u ON a.doctor_id = u.id 
     WHERE a.appointment_date = ? 
     ORDER BY a.appointment_time",
    [$today],
    "s"
);

// Get upcoming appointments (next 7 days excluding today)
$upcomingAppointments = $db->fetchAll(
    "SELECT a.*, p.full_name as patient_name, u.full_name as doctor_name 
     FROM appointments a 
     JOIN patients p ON a.patient_id = p.id 
     JOIN users u ON a.doctor_id = u.id 
     WHERE a.appointment_date > ? AND a.appointment_date <= DATE_ADD(?, INTERVAL 7 DAY)
     AND a.status NOT IN ('cancelled', 'completed')
     ORDER BY a.appointment_date, a.appointment_time 
     LIMIT 10",
    [$today, $today],
    "ss"
);

// Get subscription statistics
$pendingSubscriptions = $db->fetchOne(
    "SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'pending'",
    [],
    ""
)['count'];

$activeSubscriptions = $db->fetchOne(
    "SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active'",
    [],
    ""
)['count'];

$expiringSubscriptions = $db->fetchOne(
    "SELECT COUNT(*) as count FROM patients WHERE subscription_status = 'active' AND subscription_end_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)",
    [],
    ""
)['count'];

$subscriptionRevenue = $db->fetchOne(
    "SELECT SUM(amount) as total FROM subscription_payments WHERE status = 'completed'",
    [],
    ""
)['total'] ?? 0;

// Get doctor statistics
$doctorStats = $db->fetchAll(
    "SELECT u.full_name, COUNT(a.id) as appointment_count 
     FROM users u 
     LEFT JOIN appointments a ON u.id = a.doctor_id AND a.appointment_date = ?
     WHERE u.role = 'doctor' 
     GROUP BY u.id 
     ORDER BY appointment_count DESC",
    [$today],
    "s"
);

// Get statistics
$stats = [
    'today_appointments' => count($todayAppointments),
    'total_patients' => $db->fetchOne("SELECT COUNT(*) as count FROM patients")['count'],
    'upcoming_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= ? AND status NOT IN ('cancelled', 'completed')", [$today], "s")['count'],
    'completed_today' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND status = 'completed'",
        [$today],
        "s"
    )['count'],
    'pending_subscriptions' => $pendingSubscriptions,
    'active_subscriptions' => $activeSubscriptions,
    'subscription_revenue' => $subscriptionRevenue
];

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
    margin-bottom: 1.8rem;
}

.dashboard-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2c3e50;
    margin-bottom: 0.25rem;
}

.dashboard-header p {
    color: #6c757d;
    font-size: 0.95rem;
}

/* Stats Cards */
.stats-card {
    border: none;
    border-radius: 20px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    position: relative;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
    pointer-events: none;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.15);
}

.stats-card .card-body {
    padding: 1.5rem;
}

.stats-card h6 {
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.75rem;
    opacity: 0.9;
    font-weight: 600;
}

.stats-card h2 {
    font-size: 2.2rem;
    font-weight: 800;
    margin-bottom: 0;
}

.stats-card i {
    opacity: 0.3;
    font-size: 3rem;
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

/* Calendar Container */
.calendar-container {
    background: white;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 2px 15px rgba(0,0,0,0.05);
}

.calendar-container .fc {
    min-height: 700px;
}

.calendar-container .fc-toolbar-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: #2c3e50;
}

.calendar-container .fc-button {
    border-radius: 10px !important;
    padding: 6px 14px !important;
    font-weight: 500;
    text-transform: capitalize;
}

.calendar-container .fc-button-primary {
    background: var(--primary-gradient);
    border: none;
}

.calendar-container .fc-button-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102,126,234,0.4);
}

.calendar-container .fc-day-today {
    background: rgba(102,126,234,0.05) !important;
}

/* Calendar Event Styling */
.calendar-container .fc-event {
    border-radius: 8px;
    padding: 4px 6px !important;
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--primary-gradient);
    border: none;
    margin: 2px 0;
    overflow: hidden;
    white-space: normal !important;
    word-wrap: break-word !important;
    word-break: break-word !important;
}

.calendar-container .fc-event:hover {
    transform: scale(1.02);
    filter: brightness(1.05);
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.calendar-container .fc-event.completed {
    background: var(--success-gradient);
}

.calendar-container .fc-event.cancelled {
    background: var(--danger-gradient);
    text-decoration: line-through;
}

.calendar-container .fc-event.checked-in {
    background: var(--warning-gradient);
}

.calendar-container .fc-daygrid-event {
    white-space: normal !important;
    word-wrap: break-word !important;
    word-break: break-word !important;
}

.calendar-container .fc-daygrid-day-events {
    min-height: 70px;
}

.calendar-container .fc-daygrid-day-frame {
    min-height: 85px;
}

.calendar-container .fc-daygrid-more-link {
    font-size: 11px;
    font-weight: 500;
    background: rgba(102,126,234,0.1);
    padding: 2px 8px;
    border-radius: 20px;
    margin-top: 3px;
    display: inline-block;
}

.calendar-container .fc-daygrid-more-link:hover {
    background: rgba(102,126,234,0.2);
    text-decoration: none;
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
    .calendar-container .fc {
        min-height: 550px;
    }
    
    .appointment-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    }
}

@media (max-width: 768px) {
    .stats-card h2 {
        font-size: 1.5rem;
    }
    
    .stats-card i {
        font-size: 2rem;
    }
    
    .calendar-container .fc {
        min-height: 450px;
    }
    
    .calendar-container .fc-toolbar {
        flex-direction: column;
        gap: 10px;
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
    .calendar-container .fc-event {
        display: none !important;
    }
    
    .calendar-container .fc-daygrid-more-link {
        display: block !important;
        text-align: center;
    }
    
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
</style>

<div class="container-fluid">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <h1>Dashboard</h1>
        <p>Welcome back! Here's an overview of your dental clinic.</p>
    </div>
    
    <!-- Quick Actions Bar -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card quick-actions-card">
                <div class="card-body">
                    <h6 class="mb-3 fw-semibold">Quick Actions</h6>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="assistant_subscriptions.php" class="btn btn-warning quick-action-btn">
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
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Row 1 -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stats-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Today's Appointments</h6>
                            <h2 class="mb-0"><?php echo $stats['today_appointments']; ?></h2>
                        </div>
                        <i class="fas fa-calendar-day fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stats-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Total Patients</h6>
                            <h2 class="mb-0"><?php echo $stats['total_patients']; ?></h2>
                        </div>
                        <i class="fas fa-users fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stats-card" style="background: var(--info-gradient); color: white;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Upcoming Appointments</h6>
                            <h2 class="mb-0"><?php echo $stats['upcoming_appointments']; ?></h2>
                        </div>
                        <i class="fas fa-calendar-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stats-card" style="background: var(--warning-gradient); color: white;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Completed Today</h6>
                            <h2 class="mb-0"><?php echo $stats['completed_today']; ?></h2>
                        </div>
                        <i class="fas fa-check-circle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Row 2 - Subscriptions -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card stats-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Pending Subscriptions</h6>
                            <h2 class="mb-0"><?php echo $stats['pending_subscriptions']; ?></h2>
                            <small>Waiting for payment</small>
                        </div>
                        <i class="fas fa-clock fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card stats-card" style="background: var(--success-gradient); color: white;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Active Subscriptions</h6>
                            <h2 class="mb-0"><?php echo $stats['active_subscriptions']; ?></h2>
                            <small>Active members</small>
                        </div>
                        <i class="fas fa-crown fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card stats-card" style="background: var(--info-gradient); color: white;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Subscription Revenue</h6>
                            <h2 class="mb-0"><?php echo formatCurrency($stats['subscription_revenue']); ?></h2>
                            <small>Total collected</small>
                        </div>
                        <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Appointments Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card today-appointments-card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-day me-2"></i> Today's Appointments
                        <span class="badge bg-light text-dark ms-2"><?php echo count($todayAppointments); ?> appointments</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($todayAppointments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-check fa-4x text-muted mb-3"></i>
                            <p class="text-muted mb-0">No appointments scheduled for today.</p>
                            <a href="appointments/add.php" class="btn btn-primary mt-3">Book an Appointment</a>
                        </div>
                    <?php else: ?>
                        <div class="appointment-grid">
                            <?php foreach ($todayAppointments as $apt): ?>
                                <div class="appointment-card">
                                    <div class="appointment-time">
                                        <span><i class="far fa-clock me-1"></i> <?php echo formatTime($apt['appointment_time']); ?></span>
                                        <span class="badge bg-light text-dark"><?php echo $apt['duration']; ?> min</span>
                                    </div>
                                    <div class="appointment-patient">
                                        <i class="fas fa-user-circle"></i> <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong>
                                    </div>
                                    <div class="appointment-doctor">
                                        <i class="fas fa-user-md"></i> Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?>
                                    </div>
                                    <div class="appointment-treatment">
                                        <i class="fas fa-tooth"></i> <?php echo htmlspecialchars($apt['treatment_type']); ?>
                                    </div>
                                    <div class="mt-2">
                                        <?php 
                                        $statusColors = [
                                            'scheduled' => 'bg-primary',
                                            'checked-in' => 'bg-warning text-dark',
                                            'in-treatment' => 'bg-info',
                                            'completed' => 'bg-success',
                                            'cancelled' => 'bg-danger',
                                            'no-show' => 'bg-secondary'
                                        ];
                                        $statusColor = $statusColors[$apt['status']] ?? 'bg-secondary';
                                        ?>
                                        <span class="appointment-status <?php echo $statusColor; ?>">
                                            <i class="fas <?php echo $apt['status'] == 'scheduled' ? 'fa-clock' : ($apt['status'] == 'completed' ? 'fa-check' : 'fa-info-circle'); ?> me-1"></i>
                                            <?php echo ucfirst(str_replace('-', ' ', $apt['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content: Calendar + Sidebar -->
    <div class="row">
        <!-- Calendar Section (Main) -->
        <div class="col-lg-8 mb-4">
            <div class="calendar-container">
                <div id="calendar"></div>
            </div>
        </div>
        
        <!-- Sidebar Section -->
        <div class="col-lg-4">
            <!-- Upcoming Appointments -->
            <div class="card upcoming-appointments-card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calendar-week text-primary me-2"></i> Upcoming Appointments
                        <span class="badge bg-primary ms-2">Next 7 days</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($upcomingAppointments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                            <p class="text-muted mb-0">No upcoming appointments</p>
                        </div>
                    <?php else: ?>
                        <div class="upcoming-list">
                            <?php foreach ($upcomingAppointments as $apt): ?>
                                <div class="upcoming-item px-3">
                                    <div class="upcoming-date">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?php echo formatDate($apt['appointment_date']); ?>
                                    </div>
                                    <div class="upcoming-patient">
                                        <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong>
                                        <br>
                                        <small class="text-muted">Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></small>
                                    </div>
                                    <div class="upcoming-time">
                                        <i class="far fa-clock me-1"></i>
                                        <?php echo formatTime($apt['appointment_time']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-white text-center border-0">
                    <a href="appointments/index.php" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye me-1"></i> View All Appointments
                    </a>
                </div>
            </div>
            
            <!-- Doctor Statistics -->
            <div class="card doctor-stats-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-chart-line text-success me-2"></i> Today's Doctor Activity
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($doctorStats)): ?>
                        <p class="text-muted text-center py-3">No data available</p>
                    <?php else: ?>
                        <ul class="doctor-stats-list">
                            <?php foreach ($doctorStats as $doc): ?>
                                <li>
                                    <span class="doctor-name">
                                        <i class="fas fa-user-md text-primary me-2"></i>
                                        Dr. <?php echo htmlspecialchars($doc['full_name']); ?>
                                    </span>
                                    <span class="doctor-count"><?php echo $doc['appointment_count']; ?> appointments</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){if(!window.chatbase||window.chatbase("getState")!=="initialized"){window.chatbase=(...arguments)=>{if(!window.chatbase.q){window.chatbase.q=[]}window.chatbase.q.push(arguments)};window.chatbase=new Proxy(window.chatbase,{get(target,prop){if(prop==="q"){return target.q}return(...args)=>target(prop,...args)}})}const onLoad=function(){const script=document.createElement("script");script.src="https://www.chatbase.co/embed.min.js";script.id="J9p5V3puetElIpM5CL1jK";script.domain="www.chatbase.co";document.body.appendChild(script)};if(document.readyState==="complete"){onLoad()}else{window.addEventListener("load",onLoad)}})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    
    // Helper function to get status class
    function getStatusClass(status) {
        switch(status) {
            case 'completed': return 'completed';
            case 'cancelled': return 'cancelled';
            case 'checked-in': return 'checked-in';
            default: return 'scheduled';
        }
    }
    
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: function(fetchInfo, successCallback, failureCallback) {
            // Fetch events from API
            fetch('api/appointments.php')
                .then(response => response.json())
                .then(data => {
                    // Process events and add status classes
                    const events = data.map(event => ({
                        ...event,
                        className: getStatusClass(event.status)
                    }));
                    successCallback(events);
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    failureCallback(error);
                });
        },
        eventClick: function(info) {
            // Show appointment details
            const event = info.event;
            alert(`Appointment: ${event.title}\nStatus: ${event.extendedProps.status || 'Scheduled'}\nTime: ${event.start ? event.start.toLocaleTimeString() : 'N/A'}`);
        },
        height: 'auto',
        contentHeight: 650,
        aspectRatio: 1.6,
        expandRows: true,
        stickyHeaderDates: true,
        weekNumbers: true,
        eventDisplay: 'block',
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        },
        eventContent: function(arg) {
            // Get patient name and time
            let patientName = arg.event.title;
            let time = arg.event.start ? arg.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
            
            // Truncate long names based on screen size
            let maxLength = window.innerWidth < 768 ? 12 : 20;
            if (patientName.length > maxLength) {
                patientName = patientName.substring(0, maxLength - 2) + '...';
            }
            
            return {
                html: `<div style="
                            padding: 2px 4px;
                            line-height: 1.3;
                            white-space: normal;
                            word-wrap: break-word;
                            overflow-wrap: break-word;
                        ">
                            <div style="
                                font-weight: 700;
                                font-size: 11px;
                                margin-bottom: 2px;
                                white-space: normal;
                                word-break: break-word;
                            ">${escapeHtml(patientName)}</div>
                            <div style="
                                font-size: 9px;
                                opacity: 0.9;
                            ">${time}</div>
                        </div>`
            };
        },
        eventDidMount: function(info) {
            // Add tooltip with full details
            const startTime = info.event.start ? info.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '';
            const status = info.event.extendedProps.status || 'Scheduled';
            info.el.setAttribute('title', `${info.event.title}\nTime: ${startTime}\nStatus: ${status}\nClick for details`);
            info.el.style.cursor = 'pointer';
        },
        businessHours: {
            daysOfWeek: [1, 2, 3, 4, 5],
            startTime: '09:00',
            endTime: '18:00',
        },
        dayMaxEvents: true,
        moreLinkText: '+{0} more',
        eventOverlap: false,
        slotEventOverlap: false,
        nowIndicator: true,
        selectable: true,
        selectHelper: true,
        windowResize: function() {
            calendar.refetchEvents();
        }
    });
    
    calendar.render();
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

<?php include 'layouts/footer.php'; ?>