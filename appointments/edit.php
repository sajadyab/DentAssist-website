<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();
// block patients from editing appointments
if (Auth::hasRole('patient')) {
    header('Location: ../patient/index.php');
    exit;
}

$db = Database::getInstance();
$appointmentId = (int) ($_GET['id'] ?? 0);

// Get appointment details
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

// Get doctors and patients for dropdowns
$doctors = repo_user_list_doctors(false);
$patients = repo_patient_list_for_select();

$error = '';
$success = '';
$whatsappNotifyResult = null;

include '../layouts/header.php';
?>


<div class="container-fluid bills-page appointments-edit-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold appointments-edit-title-wrap">
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
        <div class="col-12">
            <div class="card bills-dash-section-card appointments-edit-form-card queue-registration-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-folder-open me-2" aria-hidden="true"></i>Appointment details</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo url('api/appointments_edit.php'); ?>" data-api="<?php echo url('api/appointments_edit.php'); ?>" data-message-target="#message">
                        <input type="hidden" name="id" value="<?php echo (int) $appointmentId; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="editAptPatient">Patient *</label>
                                <select class="form-select form-control-modern" id="editAptPatient" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?php echo (int) $p['id']; ?>" <?php echo (int) $appointment['patient_id'] === (int) $p['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="editAptDoctor">Doctor *</label>
                                <select class="form-select form-control-modern" id="editAptDoctor" name="doctor_id" required>
                                    <option value="">Select Doctor</option>
                                    <?php foreach ($doctors as $doc): ?>
                                        <option value="<?php echo (int) $doc['id']; ?>" <?php echo (int) $appointment['doctor_id'] === (int) $doc['id'] ? 'selected' : ''; ?>>
                                            Dr. <?php echo htmlspecialchars($doc['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="editAptDate">Date *</label>
                                <input type="date" class="form-control form-control-modern" id="editAptDate" name="appointment_date"
                                       value="<?php echo htmlspecialchars((string) ($appointment['appointment_date'] ?? '')); ?>" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="editAptTime">Time *</label>
                                <input type="time" class="form-control form-control-modern" id="editAptTime" name="appointment_time"
                                       value="<?php echo htmlspecialchars(substr((string) ($appointment['appointment_time'] ?? ''), 0, 5)); ?>" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label" for="editAptDuration">Duration</label>
                                <select class="form-select form-control-modern" id="editAptDuration" name="duration">
                                    <option value="15" <?php echo (int) $appointment['duration'] === 15 ? 'selected' : ''; ?>>15 min</option>
                                    <option value="30" <?php echo (int) $appointment['duration'] === 30 ? 'selected' : ''; ?>>30 min</option>
                                    <option value="45" <?php echo (int) $appointment['duration'] === 45 ? 'selected' : ''; ?>>45 min</option>
                                    <option value="60" <?php echo (int) $appointment['duration'] === 60 ? 'selected' : ''; ?>>60 min</option>
                                    <option value="90" <?php echo (int) $appointment['duration'] === 90 ? 'selected' : ''; ?>>90 min</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="editAptChair">Chair Number</label>
                                <input type="number" class="form-control form-control-modern" id="editAptChair" name="chair_number" min="1" max="10"
                                       value="<?php echo htmlspecialchars((string) ($appointment['chair_number'] ?? '')); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="editAptTreatment">Treatment Type *</label>
                                <input type="text" class="form-control form-control-modern" id="editAptTreatment" name="treatment_type"
                                       value="<?php echo htmlspecialchars((string) ($appointment['treatment_type'] ?? '')); ?>" required>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label" for="editAptDescription">Description</label>
                                <textarea class="form-control form-control-modern" id="editAptDescription" name="description" rows="2"><?php echo htmlspecialchars((string) ($appointment['description'] ?? '')); ?></textarea>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="editAptStatus">Status</label>
                                <select class="form-select form-control-modern" id="editAptStatus" name="status">
                                    <option value="scheduled" <?php echo $appointment['status'] === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="checked-in" <?php echo $appointment['status'] === 'checked-in' ? 'selected' : ''; ?>>Checked In</option>
                                    <option value="in-treatment" <?php echo $appointment['status'] === 'in-treatment' ? 'selected' : ''; ?>>In Treatment</option>
                                    <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="follow-up" <?php echo $appointment['status'] === 'follow-up' ? 'selected' : ''; ?>>Follow Up</option>
                                </select>
                            </div>

                            <div class="col-12 mb-0">
                                <label class="form-label" for="editAptNotes">Notes</label>
                                <textarea class="form-control form-control-modern" id="editAptNotes" name="notes" rows="2"><?php echo htmlspecialchars((string) ($appointment['notes'] ?? '')); ?></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-lg-end gap-2 flex-wrap appointment-edit-form-actions">
                            <button type="submit" class="btn-green">Update Appointment</button>
                            <a href="view.php?id=<?php echo (int) $appointmentId; ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
