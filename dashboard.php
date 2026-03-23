<?php
// ==============================================
// IMPORTANT: Check your header/footer/layout files
// for any < src="https://via.placeholder.com/...">
// Replace them with local fallback images or a reliable CDN.
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

// Get today's appointments
$today = date('Y-m-d');
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

// Get statistics
$stats = [
    'today_appointments' => count($todayAppointments),
    'total_patients' => $db->fetchOne("SELECT COUNT(*) as count FROM patients")['count'],
    'total_appointments' => $db->fetchOne("SELECT COUNT(*) as count FROM appointments WHERE appointment_date >= ?", [$today], "s")['count'],
    'completed_today' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND status = 'completed'",
        [$today],
        "s"
    )['count']
];

// Get recent patients
$recentPatients = $db->fetchAll(
    "SELECT * FROM patients ORDER BY created_at DESC LIMIT 5"
);

include 'layouts/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Dashboard</h1>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
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
        
        <div class="col-md-3">
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
        
        <div class="col-md-3">
            <div class="card stats-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Upcoming Appointments</h6>
                            <h2 class="mb-0"><?php echo $stats['total_appointments']; ?></h2>
                        </div>
                        <i class="fas fa-calendar-alt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card stats-card bg-warning text-white">
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
    
    <div class="row">
        <!-- Calendar -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Appointment Calendar</h5>
                </div>
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
        
        <!-- Today's Appointments -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Today's Appointments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($todayAppointments)): ?>
                        <p class="text-muted">No appointments today</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($todayAppointments as $apt): ?>
                                <div class="timeline-item">
                                    <div class="timeline-time">
                                        <?php echo formatTime($apt['appointment_time']); ?>
                                    </div>
                                    <div class="timeline-content">
                                        <h6><?php echo htmlspecialchars($apt['patient_name']); ?></h6>
                                        <p class="mb-0">
                                            Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?><br>
                                            <small><?php echo $apt['treatment_type']; ?></small>
                                        </p>
                                        <?php echo getStatusBadge($apt['status']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Patients -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Patients</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recentPatients)): ?>
                        <p class="text-muted">No patients yet</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recentPatients as $patient): ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-0"><?php echo htmlspecialchars($patient['full_name']); ?></h6>
                                            <small class="text-muted"><?php echo $patient['phone']; ?></small>
                                        </div>
                                        <a href="patients/view.php?id=<?php echo $patient['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stats-card {
    border: none;
    border-radius: 10px;
    transition: transform 0.2s;
}

.stats-card:hover {
    transform: translateY(-5px);
}

.opacity-50 {
    opacity: 0.5;
}

.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-time {
    position: absolute;
    left: -30px;
    top: 0;
    font-size: 12px;
    font-weight: 600;
    color: #6c757d;
}

.timeline-content {
    padding-left: 15px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: 'api/appointments.php',
        eventClick: function(info) {
            alert('Appointment: ' + info.event.title);
        }
    });
    calendar.render();
});
</script>
<?php include 'layouts/footer.php'; ?>