<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();
// patients should not be able to add appointments via staff interface
if (Auth::hasRole('patient')) {
    header('Location: ../patient/index.php');
    exit;
}
$pageTitle = 'Schedule Appointment';

$db = Database::getInstance();
$patientId = (int) ($_GET['patient_id'] ?? 0);

// Get doctors
$doctors = repo_user_list_doctors(false);

// Get patients list
$patients = $db->fetchAll('SELECT id, full_name, phone, email FROM patients ORDER BY full_name');

$error = '';
if (empty($patients)) {
    $error = 'No patients found. Please <a href="../patients/add.php">add a patient</a> first.';
} elseif (empty($doctors)) {
    $error = 'No doctors found. Please add a doctor user first.';
}

include '../layouts/header.php';
?>


<div class="container-fluid bills-page appointments-add-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold appointments-schedule-title">
                    <i class="fas fa-calendar-plus me-2 opacity-90" aria-hidden="true"></i>Schedule new appointment
                </h2>
                <p class="mb-0 opacity-90">Choose patient, time, and treatment. Notes stay on a separate tab.</p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 appointments-add-top-actions">
                <a href="index.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Appointments</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <div id="message"></div>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Appointment scheduled successfully</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card bills-dash-section-card appointments-add-tabs-card patient-view-tabs-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-folder-open me-2" aria-hidden="true"></i>Appointment details</h5>
                        </div>
                    </div>
                </div>
                <form method="POST" action="<?php echo url('api/appointments_add.php'); ?>" data-api="<?php echo url('api/appointments_add.php'); ?>" data-message-target="#message" id="appointmentForm">
                    <div class="card-body">
                        <ul class="nav nav-tabs appointments-add-tabs-nav patient-view-tabs-nav mb-3" id="addAppointmentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="add-apt-scheduling-tab" data-bs-toggle="tab" data-bs-target="#addAptScheduling" type="button" role="tab" aria-controls="addAptScheduling" aria-selected="true">Scheduling</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="add-apt-notes-tab" data-bs-toggle="tab" data-bs-target="#addAptNotes" type="button" role="tab" aria-controls="addAptNotes" aria-selected="false">Notes &amp; extras</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane active" id="addAptScheduling" role="tabpanel" aria-labelledby="add-apt-scheduling-tab" tabindex="0">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="patientSelect">Patient *</label>
                                        <select class="form-select form-control-modern" name="patient_id" id="patientSelect" required onchange="updatePatientInfo()">
                                            <option value="">Select Patient</option>
                                            <?php foreach ($patients as $p): ?>
                                                <option value="<?php echo (int) $p['id']; ?>"
                                                    <?php echo $patientId === (int) $p['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($p['full_name']); ?> - <?php echo htmlspecialchars((string) $p['phone']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="addAptDoctor">Doctor *</label>
                                        <select class="form-select form-control-modern" name="doctor_id" id="addAptDoctor" required>
                                            <option value="">Select Doctor</option>
                                            <?php foreach ($doctors as $doctor): ?>
                                                <option value="<?php echo (int) $doctor['id']; ?>">
                                                    Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="addAptDate">Date *</label>
                                        <input type="date" class="form-control form-control-modern" id="addAptDate" name="appointment_date"
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="addAptTime">Time *</label>
                                        <input type="time" class="form-control form-control-modern" id="addAptTime" name="appointment_time" required>
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label class="form-label" for="addAptDuration">Duration</label>
                                        <select class="form-select form-control-modern" id="addAptDuration" name="duration">
                                            <option value="15">15 minutes</option>
                                            <option value="30" selected>30 minutes</option>
                                            <option value="45">45 minutes</option>
                                            <option value="60">60 minutes</option>
                                            <option value="90">90 minutes</option>
                                            <option value="120">2 hours</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="addAptChair">Chair Number</label>
                                        <input type="number" class="form-control form-control-modern" id="addAptChair" name="chair_number" min="1" max="10">
                                        <small class="text-muted">Leave empty for automatic assignment</small>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="addAptTreatment">Treatment Type *</label>
                                        <input type="text" class="form-control form-control-modern" id="addAptTreatment" name="treatment_type" required list="treatmentTypes">
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
                                </div>
                            </div>

                            <div class="tab-pane" id="addAptNotes" role="tabpanel" aria-labelledby="add-apt-notes-tab" tabindex="0">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label" for="addAptDescription">Description</label>
                                        <textarea class="form-control form-control-modern" id="addAptDescription" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="col-12 mb-0">
                                        <label class="form-label" for="addAptNotesInternal">Notes (internal)</label>
                                        <textarea class="form-control form-control-modern" id="addAptNotesInternal" name="notes" rows="3"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-lg-end appointment-form-actions flex-wrap gap-2">
                            <button type="submit" name="save_and_new" class="btn btn-outline-secondary order-lg-0">
                                Save &amp; Schedule Another
                            </button>
                            <button type="submit" class="btn-green order-lg-1">Schedule Appointment</button>
                            <a href="index.php" class="btn btn-secondary order-lg-2">Cancel</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bills-dash-section-card appointment-side-card mb-3 d-none" id="patientInfoCard">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-user me-2" aria-hidden="true"></i>Patient information</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="patientDetails"></div>
                </div>
            </div>

            <div class="card bills-dash-section-card appointment-side-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-clock me-2" aria-hidden="true"></i>Available slots</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="availableSlots">
                        <p class="text-muted mb-0">Select a date to view available slots</p>
                    </div>
                </div>
            </div>

            <div class="card bills-dash-section-card appointment-side-card mt-lg-3">
                <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-bolt me-2" aria-hidden="true"></i>Quick actions</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <a href="../patients/add.php" class="btn btn-success btn-sm w-100 mb-2">
                        <i class="fas fa-user-plus" aria-hidden="true"></i> Add New Patient
                    </a>
                    <button type="button" class="btn btn-info btn-sm w-100 text-dark" onclick="checkAvailability()">
                        <i class="fas fa-clock" aria-hidden="true"></i> Check Availability
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
                    infoCard.classList.remove('d-none');
                }
            });
    } else {
        infoCard.classList.add('d-none');
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
document.querySelector('#addAptDate')?.addEventListener('change', function() {
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
    var t = document.querySelector('#addAptTime');
    if (t) {
        t.value = time;
    }
}

document.addEventListener('DOMContentLoaded', function () {
    var ps = document.getElementById('patientSelect');
    if (ps && ps.value) {
        updatePatientInfo();
    }
});
</script>

<?php include '../layouts/footer.php'; ?>
