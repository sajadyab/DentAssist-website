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

// Get available treatments
$treatments = [];
if (dbTableExists('treatments')) {
    $treatments = $db->fetchAll('SELECT name FROM treatments ORDER BY name');
}

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

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="addAptDate">Date *</label>
                                        <input type="date" class="form-control form-control-modern" id="addAptDate" name="appointment_date"
                                               min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="addAptTime">Time*</label>
                                        <select class="form-select form-control-modern" id="addAptTime" name="appointment_time" required>
                                            <option value="">Select time</option>
                                        </select>
                                    </div>

                                    <input type="hidden" name="duration" value="45">

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="addAptTreatment">Treatment Type *</label>
                                        <select class="form-select form-control-modern" id="addAptTreatment" name="treatment_type" required>
                                            <option value="">Select Treatment</option>
                                            <?php if (empty($treatments)): ?>
                                                <option value="General Treatment">General Treatment</option>
                                            <?php else: ?>
                                                <?php foreach ($treatments as $treatment): ?>
                                                    <option value="<?php echo htmlspecialchars((string) $treatment['name']); ?>">
                                                        <?php echo htmlspecialchars((string) $treatment['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
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

            <style>
                .patient-info-row {
                    margin-bottom: 0.75rem;
                }
                .patient-info-row strong {
                    display: block;
                    margin-bottom: 0.25rem;
                    font-weight: 600;
                }
                .medical-history-box {
                    background-color: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 0.35rem;
                    padding: 0.75rem 0.9rem;
                    white-space: pre-wrap;
                    word-break: break-word;
                    font-size: 0.95rem;
                    line-height: 1.5;
                }
            </style>

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
function escapeHtml(value) {
    const str = value == null ? '' : String(value);
    return str.replace(/[&<>"]+/g, (match) => {
        switch (match) {
            case '&': return '&amp;';
            case '<': return '&lt;';
            case '>': return '&gt;';
            case '"': return '&quot;';
            default: return match;
        }
    });
}

/** Display label: e.g. "9:30 AM - Available" (value stays 24h HH:MM for APIs). */
function formatAppointmentSlotLabelAvailable(hm24) {
    const hm = String(hm24 || '').trim();
    const m = /^(\d{1,2}):(\d{2})$/.exec(hm);
    if (!m) return hm ? hm + ' - Available' : '';
    let h = parseInt(m[1], 10);
    const min = m[2];
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12;
    if (h === 0) h = 12;
    return h + ':' + min + ' ' + ampm + ' - Available';
}

function formatMedicalHistory(text) {
    const raw = text == null ? '' : String(text).trim();
    if (raw === '') {
        return '<div class="text-muted">None</div>';
    }

    let parsed;
    if (raw.startsWith('{') && raw.endsWith('}')) {
        try {
            parsed = JSON.parse(raw);
        } catch (err) {
            parsed = null;
        }
    }

    if (parsed && typeof parsed === 'object') {
        const conditions = Array.isArray(parsed.conditions)
            ? parsed.conditions.filter(item => item != null && String(item).trim() !== '')
            : [];
        const notes = String(parsed.notes || '').trim();

        let html = '';
        if (conditions.length > 0) {
            html += '<div><strong>Conditions:</strong><ul class="mb-2">';
            conditions.forEach(condition => {
                html += `<li>${escapeHtml(String(condition))}</li>`;
            });
            html += '</ul></div>';
        }
        if (notes) {
            html += `<div><strong>Notes:</strong><div>${escapeHtml(notes).replace(/\r?\n/g, '<br>')}</div></div>`;
        }
        if (html === '') {
            html = '<div class="text-muted">None</div>';
        }
        return html;
    }

    return `<div>${escapeHtml(raw).replace(/\r?\n/g, '<br>')}</div>`;
}

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
                        <div class="patient-info-row"><strong>Name:</strong> ${escapeHtml(patient.full_name || 'None')}</div>
                        <div class="patient-info-row"><strong>Phone:</strong> ${escapeHtml(patient.phone || 'None')}</div>
                        <div class="patient-info-row"><strong>Email:</strong> ${escapeHtml(patient.email || 'None')}</div>
                        <div class="patient-info-row"><strong>DOB:</strong> ${escapeHtml(patient.date_of_birth || 'None')}</div>
                        <div class="patient-info-row"><strong>Insurance:</strong> ${escapeHtml(patient.insurance_provider || 'None')}</div>
                        <hr>
                        <div class="patient-info-row"><strong>Allergies:</strong> ${escapeHtml(patient.allergies || 'None')}</div>
                        <div class="patient-info-row"><strong>Medical History:</strong>
                            <div class="medical-history-box">${formatMedicalHistory(patient.medical_history)}</div>
                        </div>
                    `;
                    infoCard.classList.remove('d-none');
                }
            });
    } else {
        infoCard.classList.add('d-none');
    }
}

function checkAvailability() {
    const date = document.querySelector('[name="appointment_date"]').value;
    const time = document.querySelector('[name="appointment_time"]').value;
    
    if (!date || !time) {
        alert('Please select date and time first');
        return;
    }
    
    fetch(`../api/check_availability.php?date=${date}&time=${time}`)
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
        fetch(`../api/available_slots.php?date=${date}&duration=45`)
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('addAptTime');
                if (!select) {
                    return;
                }
                select.innerHTML = '<option value="">Select time</option>';
                (data.slots || []).forEach(slot => {
                    const t = slot && typeof slot.time === 'string' ? slot.time : '';
                    if (!t) return;
                    const option = document.createElement('option');
                    option.value = t;
                    option.textContent = formatAppointmentSlotLabelAvailable(t);
                    select.appendChild(option);
                });
            });
    }
});

function selectTime(time) {
    const t = document.querySelector('#addAptTime');
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
