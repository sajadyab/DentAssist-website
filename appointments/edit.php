<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();
if (Auth::hasRole('patient')) {
    header('Location: ../patient/index.php');
    exit;
}

$db = Database::getInstance();
$appointmentId = (int) ($_GET['id'] ?? 0);

$appointment = $db->fetchOne(
    "SELECT a.*, p.full_name as patient_name 
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     WHERE a.id = ?",
    [$appointmentId],
    "i"
);

if (!$appointment) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Edit Appointment';

$doctors = repo_user_list_doctors(false);
$patients = $db->fetchAll('SELECT id, full_name, phone, email FROM patients ORDER BY full_name');

$treatments = [];
if (dbTableExists('treatments')) {
    $treatments = $db->fetchAll('SELECT name FROM treatments ORDER BY name');
}

$rawTime = trim((string) ($appointment['appointment_time'] ?? ''));
$appointmentTimeHm = '';
if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $rawTime, $tm)) {
    $appointmentTimeHm = sprintf('%02d:%02d', (int) $tm[1], (int) $tm[2]);
}

$durationStored = (int) ($appointment['duration'] ?? 45);
$durationsAllowed = [15, 30, 45, 60, 90, 120];
if (!in_array($durationStored, $durationsAllowed, true)) {
    $durationStored = 45;
}

$treatmentVal = trim((string) ($appointment['treatment_type'] ?? ''));
$treatmentInList = false;
foreach ($treatments as $t) {
    if (trim((string) ($t['name'] ?? '')) === $treatmentVal && $treatmentVal !== '') {
        $treatmentInList = true;
        break;
    }
}
if (empty($treatments) && $treatmentVal === 'General Treatment') {
    $treatmentInList = true;
}

$chairStored = '';
if (isset($appointment['chair_number']) && $appointment['chair_number'] !== null && $appointment['chair_number'] !== '') {
    $chairStored = (string) (int) $appointment['chair_number'];
}

$statusStored = (string) ($appointment['status'] ?? 'scheduled');

$error = '';
$success = '';
$whatsappNotifyResult = null;

include '../layouts/header.php';
?>


<div class="container-fluid bills-page appointments-edit-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold appointments-edit-title-wrap appointments-schedule-title">
                    <i class="fas fa-edit me-2 opacity-90" aria-hidden="true"></i>Edit appointment
                </h2>
                <p class="mb-0 opacity-90">
                    <?php echo htmlspecialchars((string) ($appointment['patient_name'] ?? '')); ?>
                    · <?php echo formatDate($appointment['appointment_date'] ?? null); ?>
                    · <?php echo formatTime($appointment['appointment_time'] ?? null); ?>
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 appointments-add-top-actions">
                <a href="view.php?id=<?php echo (int) $appointmentId; ?>" class="btn appointments-add-top-btn appointments-view-header-edit-btn">
                    <i class="fas fa-eye" aria-hidden="true"></i> View
                </a>
                <a href="index.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Appointments</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <div id="message"></div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($whatsappNotifyResult !== null): ?>
        <?php if (!empty($whatsappNotifyResult['skipped_whatsapp'])): ?>
            <div class="alert alert-info">
                <strong>WhatsApp</strong> — <?php echo htmlspecialchars($whatsappNotifyResult['message'] ?? 'No message sent.'); ?>
            </div>
        <?php elseif (!empty($whatsappNotifyResult['ok'])): ?>
            <div class="alert alert-success">
                <strong>WhatsApp</strong> — <?php echo htmlspecialchars($whatsappNotifyResult['message'] ?? 'Sent.'); ?>
                <?php if (!empty($whatsappNotifyResult['sid'])): ?>
                    <br><small class="text-muted">Message ID: <?php echo htmlspecialchars((string) $whatsappNotifyResult['sid']); ?></small>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <strong>WhatsApp</strong> — <?php echo htmlspecialchars($whatsappNotifyResult['message'] ?? 'Could not send.'); ?>
                <?php if (!empty($whatsappNotifyResult['error'])): ?>
                    <br><small><?php echo htmlspecialchars($whatsappNotifyResult['error']); ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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
                <form method="POST" action="<?php echo url('api/appointments_edit.php'); ?>" data-api="<?php echo url('api/appointments_edit.php'); ?>" data-message-target="#message" id="appointmentEditForm">
                    <input type="hidden" name="id" value="<?php echo (int) $appointmentId; ?>">
                    <input type="hidden" name="duration" value="<?php echo (int) $durationStored; ?>">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusStored, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="chair_number" value="<?php echo htmlspecialchars($chairStored, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="card-body">
                        <ul class="nav nav-tabs appointments-add-tabs-nav patient-view-tabs-nav mb-3" id="editAppointmentTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="edit-apt-scheduling-tab" data-bs-toggle="tab" data-bs-target="#editAptScheduling" type="button" role="tab" aria-controls="editAptScheduling" aria-selected="true">Scheduling</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="edit-apt-notes-tab" data-bs-toggle="tab" data-bs-target="#editAptNotes" type="button" role="tab" aria-controls="editAptNotes" aria-selected="false">Notes &amp; extras</button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane active" id="editAptScheduling" role="tabpanel" aria-labelledby="edit-apt-scheduling-tab" tabindex="0">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="editPatientSelect">Patient *</label>
                                        <select class="form-select form-control-modern" name="patient_id" id="editPatientSelect" required onchange="updatePatientInfoEdit()">
                                            <option value="">Select Patient</option>
                                            <?php foreach ($patients as $p): ?>
                                                <option value="<?php echo (int) $p['id']; ?>"
                                                    <?php echo (int) $appointment['patient_id'] === (int) $p['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($p['full_name']); ?> - <?php echo htmlspecialchars((string) $p['phone']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="editAptDoctor">Doctor *</label>
                                        <select class="form-select form-control-modern" name="doctor_id" id="editAptDoctor" required>
                                            <option value="">Select Doctor</option>
                                            <?php foreach ($doctors as $doctor): ?>
                                                <option value="<?php echo (int) $doctor['id']; ?>"
                                                    <?php echo (int) $appointment['doctor_id'] === (int) $doctor['id'] ? 'selected' : ''; ?>>
                                                    Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="editAptDate">Date *</label>
                                        <input type="date" class="form-control form-control-modern" id="editAptDate" name="appointment_date"
                                               value="<?php echo htmlspecialchars((string) ($appointment['appointment_date'] ?? '')); ?>" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="editAptTime">Time *</label>
                                        <select class="form-select form-control-modern" id="editAptTime" name="appointment_time" required
                                                data-initial-time="<?php echo htmlspecialchars($appointmentTimeHm, ENT_QUOTES, 'UTF-8'); ?>">
                                            <option value="">Select time</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="editAptTreatment">Treatment Type *</label>
                                        <select class="form-select form-control-modern" id="editAptTreatment" name="treatment_type" required>
                                            <option value="">Select Treatment</option>
                                            <?php if (empty($treatments)): ?>
                                                <option value="General Treatment" <?php echo $treatmentVal === 'General Treatment' ? 'selected' : ''; ?>>General Treatment</option>
                                            <?php else: ?>
                                                <?php foreach ($treatments as $treatment): ?>
                                                    <option value="<?php echo htmlspecialchars((string) $treatment['name']); ?>"
                                                        <?php echo $treatmentVal === (string) $treatment['name'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars((string) $treatment['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            <?php if (!$treatmentInList && $treatmentVal !== ''): ?>
                                                <option value="<?php echo htmlspecialchars($treatmentVal, ENT_QUOTES, 'UTF-8'); ?>" selected>
                                                    <?php echo htmlspecialchars($treatmentVal); ?>
                                                </option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="tab-pane" id="editAptNotes" role="tabpanel" aria-labelledby="edit-apt-notes-tab" tabindex="0">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label class="form-label" for="editAptDescription">Description</label>
                                        <textarea class="form-control form-control-modern" id="editAptDescription" name="description" rows="3"><?php echo htmlspecialchars((string) ($appointment['description'] ?? '')); ?></textarea>
                                    </div>
                                    <div class="col-12 mb-0">
                                        <label class="form-label" for="editAptNotesInternal">Notes (internal)</label>
                                        <textarea class="form-control form-control-modern" id="editAptNotesInternal" name="notes" rows="3"><?php echo htmlspecialchars((string) ($appointment['notes'] ?? '')); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-lg-end appointment-form-actions flex-wrap gap-2">
                            <button type="submit" class="btn-green order-lg-1">Save changes</button>
                            <a href="view.php?id=<?php echo (int) $appointmentId; ?>" class="btn btn-secondary order-lg-2">Cancel</a>
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
                    <button type="button" class="btn btn-info btn-sm w-100 text-dark" onclick="checkAvailabilityEdit()">
                        <i class="fas fa-clock" aria-hidden="true"></i> Check Availability
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const APPOINTMENT_EDIT_EXCLUDE_ID = <?php echo (int) $appointmentId; ?>;

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

    window.updatePatientInfoEdit = function updatePatientInfoEdit() {
        const patientId = document.getElementById('editPatientSelect').value;
        const infoCard = document.getElementById('patientInfoCard');

        if (patientId) {
            fetch(`../api/get_patient.php?id=${encodeURIComponent(patientId)}`)
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
    };

    /** e.g. "9:30 AM" from 24h slot value (for display only). */
    function formatAppointmentSlotTime12h(hm24) {
        const hm = String(hm24 || '').trim();
        const m = /^(\d{1,2}):(\d{2})$/.exec(hm);
        if (!m) return hm;
        let h = parseInt(m[1], 10);
        const min = m[2];
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12;
        if (h === 0) h = 12;
        return h + ':' + min + ' ' + ampm;
    }

    window.checkAvailabilityEdit = function checkAvailabilityEdit() {
        const date = document.querySelector('#appointmentEditForm [name="appointment_date"]')?.value;
        const time = document.querySelector('#appointmentEditForm [name="appointment_time"]')?.value;

        if (!date || !time) {
            alert('Please select date and time first');
            return;
        }

        fetch('../api/check_availability.php?date=' + encodeURIComponent(date)
            + '&time=' + encodeURIComponent(time)
            + '&exclude_id=' + encodeURIComponent(String(APPOINTMENT_EDIT_EXCLUDE_ID)))
            .then(response => response.json())
            .then(data => {
                if (data.available) {
                    alert('This time slot is available!');
                } else {
                    alert('This time slot is already booked. Please choose another time.');
                }
            });
    };

    function ensureTimeOption(selectEl, hm) {
        if (!selectEl || !hm) return;
        const exists = Array.from(selectEl.options).some(function (o) { return o.value === hm; });
        if (!exists) {
            const opt = document.createElement('option');
            opt.value = hm;
            opt.textContent = formatAppointmentSlotTime12h(hm) + ' - current';
            if (selectEl.options.length > 1) {
                selectEl.insertBefore(opt, selectEl.options[1]);
            } else {
                selectEl.appendChild(opt);
            }
        }
    }

    function fetchSlotsPreserveTime(preserve) {
        const dateEl = document.getElementById('editAptDate');
        const timeSelect = document.getElementById('editAptTime');
        const durationInput = document.querySelector('#appointmentEditForm input[name="duration"]');
        if (!dateEl || !timeSelect) return;

        const date = dateEl.value;
        const duration = durationInput ? durationInput.value : '45';
        const preferred = preserve ? (timeSelect.getAttribute('data-initial-time') || '').trim() : '';

        if (!date) {
            timeSelect.innerHTML = '<option value="">Select time</option>';
            return;
        }

        fetch('../api/available_slots.php?date=' + encodeURIComponent(date)
            + '&duration=' + encodeURIComponent(duration)
            + '&exclude_id=' + encodeURIComponent(String(APPOINTMENT_EDIT_EXCLUDE_ID)))
            .then(response => response.json())
            .then(data => {
                timeSelect.innerHTML = '<option value="">Select time</option>';
                (data.slots || []).forEach(function (slot) {
                    const t = slot && typeof slot.time === 'string' ? slot.time : '';
                    if (!t) return;
                    const option = document.createElement('option');
                    option.value = t;
                    option.textContent = formatAppointmentSlotTime12h(t) + ' - Available';
                    timeSelect.appendChild(option);
                });
                if (preferred) {
                    ensureTimeOption(timeSelect, preferred);
                    timeSelect.value = preferred;
                }
            })
            .catch(function () {
                timeSelect.innerHTML = '<option value="">Select time</option>';
                if (preserve && preferred) {
                    ensureTimeOption(timeSelect, preferred);
                    timeSelect.value = preferred;
                }
            });
    }

    document.getElementById('editAptDate')?.addEventListener('change', function () {
        fetchSlotsPreserveTime(false);
    });

    document.addEventListener('DOMContentLoaded', function () {
        const ps = document.getElementById('editPatientSelect');
        if (ps && ps.value) {
            updatePatientInfoEdit();
        }
        fetchSlotsPreserveTime(true);
    });
})();
</script>

<?php include '../layouts/footer.php'; ?>
