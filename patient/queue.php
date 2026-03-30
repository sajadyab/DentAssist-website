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

$error = '';
$success = '';

$patient = $db->fetchOne("SELECT full_name FROM patients WHERE id = ?", [$patientId], "i");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $queueType = $_POST['queue_type'];
    $priority = $_POST['priority'];
    $reason = $_POST['reason'];
    $preferredTreatment = $_POST['preferred_treatment'];
    $preferredDay = $_POST['preferred_day'] ?? null;

    $db->insert(
        "INSERT INTO waiting_queue (patient_id, patient_name, queue_type, priority, reason, preferred_treatment, preferred_day, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'waiting')",
        [$patientId, $patient['full_name'], $queueType, $priority, $reason, $preferredTreatment, $preferredDay],
        "issssss"
    );

    $success = 'You have been added to the queue.';
}

$pageTitle = 'Join Queue';
include '../layouts/header.php';
?>

<style>
.queue-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
    position: relative;
    overflow: hidden;
}

.queue-header::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 1%, transparent 1%);
    background-size: 50px 50px;
    animation: moveBackground 30s linear infinite;
}

@keyframes moveBackground {
    0% { transform: translate(0, 0); }
    100% { transform: translate(50px, 50px); }
}

.form-card {
    border-radius: 20px;
    border: none;
    box-shadow: 0 5px 20px rgba(0,0,0,0.08);
    overflow: hidden;
}

.form-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px 25px;
}

.form-header h4 {
    margin-bottom: 5px;
}

.form-control-modern {
    border-radius: 10px;
    border: 1px solid #e0e0e0;
    padding: 12px 15px;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    outline: none;
}

.form-label-modern {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 8px;
    display: block;
}

.btn-queue {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    color: white;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-queue:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102,126,234,0.4);
}

.btn-cancel {
    background: #6c757d;
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    color: white;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.alert-custom {
    border-radius: 12px;
    border: none;
    padding: 15px 20px;
}

.info-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.info-card:hover {
    background: #e9ecef;
    transform: translateX(5px);
}

.priority-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.priority-low { background: #d1ecf1; color: #0c5460; }
.priority-medium { background: #fff3cd; color: #856404; }
.priority-high { background: #f8d7da; color: #721c24; }
.priority-emergency { background: #dc3545; color: white; }

.queue-timer {
    background: rgba(255,255,255,0.2);
    border-radius: 15px;
    padding: 15px;
    text-align: center;
    backdrop-filter: blur(10px);
}

.timer-number {
    font-size: 32px;
    font-weight: bold;
    font-family: monospace;
}

select.form-control-modern {
    cursor: pointer;
}

select.form-control-modern option {
    padding: 10px;
}
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Queue Header -->
    <div class="queue-header">
        <div class="row align-items-center">
            <div class="col-md-7">
                <h2 class="mb-2">
                    <i class="fas fa-hourglass-half"></i> Join Waiting Queue
                </h2>
                <p class="mb-0">Get in line for immediate care or schedule a future appointment</p>
                <div class="mt-3">
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-clock"></i> Open: Mon-Fri 9AM-6PM
                    </span>
                    <span class="badge bg-light text-dark">
                        <i class="fas fa-phone"></i> Emergency: Call (555) 123-4567
                    </span>
                </div>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="queue-timer">
                    <small>Estimated Wait Time</small>
                    <div class="timer-number">~<?php echo rand(15, 45); ?> min</div>
                    <small>Based on current queue</small>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-7">
            <div class="form-card">
                <div class="form-header">
                    <h4 class="mb-0">
                        <i class="fas fa-sign-in-alt"></i> Queue Registration
                    </h4>
                    <p class="mb-0 mt-2 opacity-75">Fill out the form below to join our waiting list</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label-modern">
                                <i class="fas fa-calendar-alt text-primary me-2"></i> Queue Type *
                            </label>
                            <select class="form-select form-control-modern" name="queue_type" id="queueType" onchange="togglePreferredDay()" required>
                                <option value="daily">Daily Queue - Today (Immediate Attention)</option>
                                <option value="weekly">Weekly Queue - Future Appointment</option>
                            </select>
                            <small class="text-muted">Choose daily for same-day service or weekly to schedule for next week</small>
                        </div>
                        
                        <div class="mb-4" id="preferredDayDiv" style="display: none;">
                            <label class="form-label-modern">
                                <i class="fas fa-calendar-week text-primary me-2"></i> Preferred Day
                            </label>
                            <select class="form-select form-control-modern" name="preferred_day">
                                <option value="Monday">📅 Monday</option>
                                <option value="Tuesday">📅 Tuesday</option>
                                <option value="Wednesday">📅 Wednesday</option>
                                <option value="Thursday">📅 Thursday</option>
                                <option value="Friday">📅 Friday</option>
                            </select>
                            <small class="text-muted">Select your preferred day for the appointment</small>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label-modern">
                                <i class="fas fa-chart-line text-primary me-2"></i> Priority Level *
                            </label>
                            <select class="form-select form-control-modern" name="priority" required>
                                <option value="low">🟢 Low - Routine checkup / Cleaning (30-45 min wait)</option>
                                <option value="medium" selected>🟡 Medium - Non-urgent dental issue (20-30 min wait)</option>
                                <option value="high">🟠 High - Pain or discomfort (10-20 min wait)</option>
                                <option value="emergency">🔴 Emergency - Severe pain or injury (Immediate)</option>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label-modern">
                                <i class="fas fa-stethoscope text-primary me-2"></i> Reason for Visit *
                            </label>
                            <textarea class="form-control form-control-modern" name="reason" rows="4" required 
                                placeholder="Please describe your dental concern in detail..."></textarea>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label-modern">
                                <i class="fas fa-tooth text-primary me-2"></i> Preferred Treatment (Optional)
                            </label>
                            <input type="text" class="form-control form-control-modern" name="preferred_treatment" 
                                placeholder="e.g., Cleaning, Filling, Extraction, Root Canal, Whitening">
                        </div>
                        
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn-queue">
                                <i class="fas fa-hourglass-start"></i> Join Queue
                            </button>
                            <a href="index.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <!-- Queue Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle text-info"></i> Queue Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-card">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-chart-line text-primary fa-lg me-3 mt-1"></i>
                            <div>
                                <strong>Current Queue Status</strong>
                                <p class="small mb-0 mt-1">Queue system is active and accepting patients</p>
                                <small class="text-muted">Last updated: <?php echo date('h:i A'); ?></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-clock text-warning fa-lg me-3 mt-1"></i>
                            <div>
                                <strong>Estimated Wait Times by Priority</strong>
                                <p class="small mb-0 mt-1">
                                    🔴 Emergency: Immediate<br>
                                    🟠 High: 10-20 minutes<br>
                                    🟡 Medium: 20-30 minutes<br>
                                    🟢 Low: 30-45 minutes
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="d-flex align-items-start">
                            <i class="fas fa-exclamation-triangle text-danger fa-lg me-3 mt-1"></i>
                            <div>
                                <strong>Emergency Cases</strong>
                                <p class="small mb-0 mt-1">If you have severe pain, bleeding, or trauma, select Emergency priority or call us immediately.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preparation Tips -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clinic-medical text-success"></i> Before Your Visit
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-3">
                        <i class="fas fa-id-card text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Bring Your ID & Insurance Card</strong>
                            <p class="small text-muted mb-0">For verification and coverage</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fas fa-list-alt text-primary me-3 mt-1"></i>
                        <div>
                            <strong>List of Medications</strong>
                            <p class="small text-muted mb-0">Current medications and allergies</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <i class="fas fa-file-medical text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Previous Records</strong>
                            <p class="small text-muted mb-0">X-rays or dental records if available</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <i class="fas fa-smile text-primary me-3 mt-1"></i>
                        <div>
                            <strong>Oral Hygiene</strong>
                            <p class="small text-muted mb-0">Brush your teeth before arrival</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePreferredDay() {
    const queueType = document.getElementById('queueType').value;
    const div = document.getElementById('preferredDayDiv');
    div.style.display = queueType === 'weekly' ? 'block' : 'none';
}
</script>

<?php include '../layouts/footer.php'; ?>