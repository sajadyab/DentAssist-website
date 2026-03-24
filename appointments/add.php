<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
// patients should not be able to add appointments via staff interface
if (Auth::hasRole('patient')) {
    header('Location: ../patient/index.php');
    exit;
}
$pageTitle = 'Schedule Appointment';

$db = Database::getInstance();
$patientId = $_GET['patient_id'] ?? 0;

// Get patient if specified
$patient = null;
if ($patientId) {
    $patient = $db->fetchOne(
        "SELECT * FROM patients WHERE id = ?",
        [$patientId],
        "i"
    );
}

// Get doctors
$doctors = $db->fetchAll(
    "SELECT id, full_name FROM users WHERE role = 'doctor' ORDER BY full_name"
);

// Get patients list
$patients = $db->fetchAll(
    "SELECT id, full_name, phone, email FROM patients ORDER BY full_name"
);

// Check if we have required data
if (empty($patients)) {
    $error = 'No patients found. Please <a href="../patients/add.php">add a patient</a> first.';
}

if (empty($doctors)) {
    $error = 'No doctors found. Please add a doctor user first.';
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if we have required data
    if (empty($patients)) {
        $error = 'No patients found. Please add a patient first.';
    } elseif (empty($doctors)) {
        $error = 'No doctors found. Please add a doctor user first.';
    } else {
        // Check availability
        $existing = $db->fetchOne(
            "SELECT id FROM appointments 
             WHERE appointment_date = ? AND appointment_time = ? AND chair_number = ? AND status != 'cancelled'",
            [$_POST['appointment_date'], $_POST['appointment_time'], $_POST['chair_number']],
            "ssi"
        );
        
        if ($existing) {
            $error = 'This time slot is already booked for the selected chair';
        } else {
        // Create appointment
        $appointmentId = $db->insert(
            "INSERT INTO appointments (
                patient_id, doctor_id, appointment_date, appointment_time, duration,
                treatment_type, description, chair_number, status, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $_POST['patient_id'],
                $_POST['doctor_id'],
                $_POST['appointment_date'],
                $_POST['appointment_time'],
                $_POST['duration'] ?? 30,
                $_POST['treatment_type'],
                $_POST['description'] ?? null,
                $_POST['chair_number'] ?? null,
                'scheduled',
                $_POST['notes'] ?? null,
                Auth::userId()
            ],
            "iississsssi"
        );
        
        if ($appointmentId) {
            logAction('CREATE', 'appointments', $appointmentId, null, $_POST);
            
            // Send notification to patient (if they have user account)
            $patientData = $db->fetchOne(
                "SELECT user_id FROM patients WHERE id = ?",
                [$_POST['patient_id']],
                "i"
            );
            
            if ($patientData && $patientData['user_id']) {
                sendNotification(
                    $patientData['user_id'],
                    'appointment_reminder',
                    'Appointment Scheduled',
                    'Your appointment has been scheduled for ' . formatDate($_POST['appointment_date']) . 
                    ' at ' . formatTime($_POST['appointment_time'])
                );
            }
            
            $success = 'Appointment scheduled successfully';
            
            if (!isset($_POST['save_and_new'])) {
                header("Location: view.php?id=$appointmentId");
                exit;
            }
        } else {
            $error = 'Error scheduling appointment';
        }
    }
}
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Schedule New Appointment</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Appointments
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Appointment Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="" id="appointmentForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Patient *</label>
                                <select class="form-select" name="patient_id" id="patientSelect" required 
                                        onchange="updatePatientInfo()">
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" 
                                                <?php echo $patientId == $p['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['full_name']); ?> - <?php echo $p['phone']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Doctor *</label>
                                <select class="form-select" name="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $doctor): ?>
                                        <option value="<?php echo $doctor['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date *</label>
                                <input type="date" class="form-control" name="appointment_date" 
                                       min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Time *</label>
                                <input type="time" class="form-control" name="appointment_time" required>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Duration</label>
                                <select class="form-select" name="duration">
                                    <option value="15">15 minutes</option>
                                    <option value="30" selected>30 minutes</option>
                                    <option value="45">45 minutes</option>
                                    <option value="60">60 minutes</option>
                                    <option value="90">90 minutes</option>
                                    <option value="120">2 hours</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Chair Number</label>
                                <input type="number" class="form-control" name="chair_number" min="1" max="10">
                                <small class="text-muted">Leave empty for automatic assignment</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Treatment Type *</label>
                                <input type="text" class="form-control" name="treatment_type" required 
                                       list="treatmentTypes">
                                <datalist id="treatmentTypes">
                                    <option value="Cleaning">
                                    <option value="Filling">
                                    <option value="Root Canal">
                                    <option value="Extraction">
                                    <option value="Crown">
                                    <option value="Bridge">
                                    <option value="Implant">
                                    <option value="Whitening">
                                    <option value="Orthodontics">
                                    <option value="Consultation">
                                </datalist>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2"></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Notes (internal)</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="submit" name="save_and_new" class="btn btn-info">
                                Save & Schedule Another
                            </button>
                            <button type="submit" class="btn btn-primary">
                                Schedule Appointment
                            </button>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Patient Info Card -->
            <div class="card mb-3" id="patientInfoCard" style="display: none;">
                <div class="card-header">
                    <h5 class="card-title mb-0">Patient Information</h5>
                </div>
                <div class="card-body">
                    <div id="patientDetails"></div>
                </div>
            </div>
            
            <!-- Available Slots Card -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Available Slots</h5>
                </div>
                <div class="card-body">
                    <div id="availableSlots">
                        <p class="text-muted">Select date to view available slots</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <a href="../patients/add.php" class="btn btn-success btn-sm w-100 mb-2">
                        <i class="fas fa-user-plus"></i> Add New Patient
                    </a>
                    <button class="btn btn-info btn-sm w-100" onclick="checkAvailability()">
                        <i class="fas fa-clock"></i> Check Availability
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function updatePatientInfo() {
    const patientId = document.getElementById('patientSelect').value;
    const infoCard = document.getElementById('patientInfoCard');
    
    if (patientId) {
        fetch(`../api/get_patient.php?id=${patientId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const patient = data.patient;
                    document.getElementById('patientDetails').innerHTML = `
                        <p><strong>Name:</strong> ${patient.full_name}</p>
                        <p><strong>Phone:</strong> ${patient.phone}</p>
                        <p><strong>Email:</strong> ${patient.email}</p>
                        <p><strong>DOB:</strong> ${patient.date_of_birth}</p>
                        <p><strong>Insurance:</strong> ${patient.insurance_provider || 'None'}</p>
                        <hr>
                        <p><strong>Allergies:</strong> ${patient.allergies || 'None'}</p>
                        <p><strong>Medical History:</strong> ${patient.medical_history || 'None'}</p>
                    `;
                    infoCard.style.display = 'block';
                }
            });
    } else {
        infoCard.style.display = 'none';
    }
}

function checkAvailability() {
    const date = document.querySelector('input[name="appointment_date"]').value;
    const time = document.querySelector('input[name="appointment_time"]').value;
    const chair = document.querySelector('input[name="chair_number"]').value;
    
    if (!date || !time) {
        alert('Please select date and time first');
        return;
    }
    
    fetch(`../api/check_availability.php?date=${date}&time=${time}&chair=${chair}`)
        .then(response => response.json())
        .then(data => {
            if (data.available) {
                alert('This time slot is available!');
            } else {
                alert('This time slot is already booked. Please choose another time.');
            }
        });
}

// Load available slots when date changes
document.querySelector('input[name="appointment_date"]').addEventListener('change', function() {
    const date = this.value;
    if (date) {
        fetch(`../api/available_slots.php?date=${date}`)
            .then(response => response.json())
            .then(data => {
                let html = '<div class="list-group">';
                data.slots.forEach(slot => {
                    html += `<a href="#" class="list-group-item list-group-item-action" 
                             onclick="selectTime('${slot.time}')">
                             ${slot.time} - ${slot.available ? 'Available' : 'Booked'}
                             ${slot.available ? '<span class="badge bg-success float-end">Free</span>' : 
                                               '<span class="badge bg-danger float-end">Taken</span>'}
                            </a>`;
                });
                html += '</div>';
                document.getElementById('availableSlots').innerHTML = html;
            });
    }
});

function selectTime(time) {
    document.querySelector('input[name="appointment_time"]').value = time;
}
</script>

<?php include '../layouts/footer.php'; ?>