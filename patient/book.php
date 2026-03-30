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

// Get doctors
$doctors = $db->fetchAll("SELECT id, full_name FROM users WHERE role = 'doctor' ORDER BY full_name");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check availability
    $existing = $db->fetchOne(
        "SELECT id FROM appointments 
         WHERE appointment_date = ? AND appointment_time = ? AND chair_number = ? AND status != 'cancelled'",
        [$_POST['appointment_date'], $_POST['appointment_time'], $_POST['chair_number']],
        "ssi"
    );

    if ($existing) {
        $error = 'This time slot is already booked';
    } else {
        $appointmentId = $db->insert(
            "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, duration, treatment_type, description, chair_number, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)",
            [
                $patientId,
                $_POST['doctor_id'],
                $_POST['appointment_date'],
                $_POST['appointment_time'],
                $_POST['duration'] ?? 30,
                $_POST['treatment_type'],
                $_POST['description'] ?? null,
                $_POST['chair_number'] ?? null,
                Auth::userId()
            ],
            "iississsi"
        );

        if ($appointmentId) {
            logAction('CREATE', 'appointments', $appointmentId, null, $_POST);
            $success = 'Appointment booked successfully';
        } else {
            $error = 'Error booking appointment';
        }
    }
}

$pageTitle = 'Book Appointment';
include '../layouts/header.php';
?>

<style>
.booking-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
    position: relative;
    overflow: hidden;
}

.booking-header::before {
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

.btn-book {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    color: white;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-book:hover {
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

.doctor-select {
    cursor: pointer;
}

.treatment-badge {
    display: inline-block;
    padding: 5px 12px;
    background: #f0f0f0;
    border-radius: 20px;
    font-size: 12px;
    margin-right: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.treatment-badge:hover {
    background: #667eea;
    color: white;
}

.available-slots {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.time-slot {
    background: #f8f9fa;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.time-slot:hover {
    background: #667eea;
    color: white;
    border-color: #667eea;
}
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Booking Header -->
    <div class="booking-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="fas fa-calendar-plus"></i> Book an Appointment
                </h2>
                <p class="mb-0">Schedule your dental visit with our expert doctors</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white text-dark rounded p-2" style="background: rgba(255,255,255,0.2) !important; color: white !important;">
                    <small>Available Hours</small>
                    <h5 class="mb-0">Mon-Fri: 9AM - 6PM</h5>
                    <small>Saturday: 9AM - 2PM</small>
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
        <div class="col-lg-8">
            <div class="form-card">
                <div class="form-header">
                    <h4 class="mb-0">
                        <i class="fas fa-edit"></i> Appointment Details
                    </h4>
                    <p class="mb-0 mt-2 opacity-75">Fill in the information below to book your appointment</p>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="form-label-modern">
                                    <i class="fas fa-user-md text-primary me-2"></i> Select Doctor *
                                </label>
                                <select class="form-select form-control-modern doctor-select" name="doctor_id" required>
                                    <option value="">-- Choose a Doctor --</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?php echo $doc['id']; ?>">👨‍⚕️ Dr. <?php echo $doc['full_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label-modern">
                                    <i class="fas fa-calendar-day text-primary me-2"></i> Appointment Date *
                                </label>
                                <input type="date" class="form-control form-control-modern" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label-modern">
                                    <i class="fas fa-clock text-primary me-2"></i> Appointment Time *
                                </label>
                                <input type="time" class="form-control form-control-modern" name="appointment_time" required>
                                <small class="text-muted">Available slots: 9:00 AM - 5:00 PM</small>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label-modern">
                                    <i class="fas fa-hourglass-half text-primary me-2"></i> Duration
                                </label>
                                <select class="form-select form-control-modern" name="duration">
                                    <option value="15">⏱️ 15 minutes - Quick Checkup</option>
                                    <option value="30" selected>⏱️ 30 minutes - Standard Appointment</option>
                                    <option value="45">⏱️ 45 minutes - Extended Appointment</option>
                                    <option value="60">⏱️ 60 minutes - Complex Procedure</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-4">
                                <label class="form-label-modern">
                                    <i class="fas fa-chair text-primary me-2"></i> Chair Number (Optional)
                                </label>
                                <input type="number" class="form-control form-control-modern" name="chair_number" min="1" max="10" placeholder="Auto-assigned if empty">
                            </div>
                            <div class="col-12 mb-4">
                                <label class="form-label-modern">
                                    <i class="fas fa-tooth text-primary me-2"></i> Treatment Type *
                                </label>
                                <input type="text" class="form-control form-control-modern" name="treatment_type" required list="treatments" placeholder="Select or type treatment">
                                <datalist id="treatments">
                                    <option value="🦷 Dental Cleaning">
                                    <option value="🦷 Dental Filling">
                                    <option value="🦷 Root Canal Treatment">
                                    <option value="🦷 Tooth Extraction">
                                    <option value="🦷 Dental Crown">
                                    <option value="✨ Teeth Whitening">
                                    <option value="🦷 Dental Implant">
                                    <option value="🦷 Orthodontic Consultation">
                                    <option value="🚨 Emergency Care">
                                    <option value="📋 Regular Checkup">
                                </datalist>
                                <div class="mt-2">
                                    <small class="text-muted">Popular treatments:</small>
                                    <div class="treatment-badge" onclick="document.querySelector('[name=treatment_type]').value='🦷 Dental Cleaning'">Cleaning</div>
                                    <div class="treatment-badge" onclick="document.querySelector('[name=treatment_type]').value='🦷 Dental Filling'">Filling</div>
                                    <div class="treatment-badge" onclick="document.querySelector('[name=treatment_type]').value='🦷 Root Canal'">Root Canal</div>
                                    <div class="treatment-badge" onclick="document.querySelector('[name=treatment_type]').value='🦷 Extraction'">Extraction</div>
                                    <div class="treatment-badge" onclick="document.querySelector('[name=treatment_type]').value='✨ Teeth Whitening'">Whitening</div>
                                </div>
                            </div>
                            <div class="col-12 mb-4">
                                <label class="form-label-modern">
                                    <i class="fas fa-pen text-primary me-2"></i> Additional Notes (Optional)
                                </label>
                                <textarea class="form-control form-control-modern" name="description" rows="3" 
                                    placeholder="Any specific concerns or symptoms you'd like to discuss?"></textarea>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn-book">
                                <i class="fas fa-check-circle"></i> Book Appointment
                            </button>
                            <a href="index.php" class="btn-cancel">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Quick Tips -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lightbulb text-warning"></i> Quick Tips
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-card">
                        <i class="fas fa-clock text-primary me-2"></i>
                        <strong>Arrive Early</strong>
                        <p class="small text-muted mb-0 mt-1">Please arrive 15 minutes before your appointment</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-id-card text-primary me-2"></i>
                        <strong>Bring Your ID</strong>
                        <p class="small text-muted mb-0 mt-1">Photo ID and insurance card required</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-file-medical text-primary me-2"></i>
                        <strong>Medical History</strong>
                        <p class="small text-muted mb-0 mt-1">Inform us of any medical conditions or medications</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-phone-alt text-primary me-2"></i>
                        <strong>Cancellation Policy</strong>
                        <p class="small text-muted mb-0 mt-1">Please cancel at least 24 hours in advance</p>
                    </div>
                </div>
            </div>

            <!-- What to Expect -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-smile text-success"></i> What to Expect
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">1</div>
                        </div>
                        <div>
                            <strong>Check-in</strong>
                            <p class="small text-muted mb-0">Arrive and check in at the front desk</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">2</div>
                        </div>
                        <div>
                            <strong>Consultation</strong>
                            <p class="small text-muted mb-0">Discuss your concerns with the doctor</p>
                        </div>
                    </div>
                    <div class="d-flex mb-3">
                        <div class="me-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">3</div>
                        </div>
                        <div>
                            <strong>Examination</strong>
                            <p class="small text-muted mb-0">Thorough dental examination</p>
                        </div>
                    </div>
                    <div class="d-flex">
                        <div class="me-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">4</div>
                        </div>
                        <div>
                            <strong>Treatment Plan</strong>
                            <p class="small text-muted mb-0">Review findings and treatment options</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>