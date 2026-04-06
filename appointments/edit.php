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
$appointmentId = $_GET['id'] ?? 0;

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


<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 appointments-edit-header">
        <h1 class="h3 appointments-edit-title">Edit Appointment</h1>
        <div class="appointments-edit-actions">
            <a href="view.php?id=<?php echo $appointmentId; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
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

    <div class="card appointment-edit-card">
        <div class="card-body">
            <form method="POST" action="<?php echo url('api/appointments_edit.php'); ?>" data-api="<?php echo url('api/appointments_edit.php'); ?>" data-message-target="#message">
                <input type="hidden" name="id" value="<?php echo (int) $appointmentId; ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient *</label>
                        <select class="form-select" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $appointment['patient_id'] == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Doctor *</label>
                        <select class="form-select" name="doctor_id" required>
                            <option value="">Select Doctor</option>
                            <?php foreach ($doctors as $doc): ?>
                                <option value="<?php echo $doc['id']; ?>" <?php echo $appointment['doctor_id'] == $doc['id'] ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($doc['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Date *</label>
                        <input type="date" class="form-control" name="appointment_date" 
                               value="<?php echo $appointment['appointment_date']; ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Time *</label>
                        <input type="time" class="form-control" name="appointment_time" 
                               value="<?php echo substr($appointment['appointment_time'], 0, 5); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Duration</label>
                        <select class="form-select" name="duration">
                            <option value="15" <?php echo $appointment['duration'] == 15 ? 'selected' : ''; ?>>15 min</option>
                            <option value="30" <?php echo $appointment['duration'] == 30 ? 'selected' : ''; ?>>30 min</option>
                            <option value="45" <?php echo $appointment['duration'] == 45 ? 'selected' : ''; ?>>45 min</option>
                            <option value="60" <?php echo $appointment['duration'] == 60 ? 'selected' : ''; ?>>60 min</option>
                            <option value="90" <?php echo $appointment['duration'] == 90 ? 'selected' : ''; ?>>90 min</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Chair Number</label>
                        <input type="number" class="form-control" name="chair_number" min="1" max="10"
                               value="<?php echo $appointment['chair_number']; ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Treatment Type *</label>
                        <input type="text" class="form-control" name="treatment_type" 
                               value="<?php echo htmlspecialchars($appointment['treatment_type']); ?>" required>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($appointment['description']); ?></textarea>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="scheduled" <?php echo $appointment['status'] == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                            <option value="checked-in" <?php echo $appointment['status'] == 'checked-in' ? 'selected' : ''; ?>>Checked In</option>
                            <option value="in-treatment" <?php echo $appointment['status'] == 'in-treatment' ? 'selected' : ''; ?>>In Treatment</option>
                            <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="follow-up" <?php echo $appointment['status'] == 'follow-up' ? 'selected' : ''; ?>>Follow Up</option>
                        </select>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($appointment['notes']); ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2 appointment-edit-form-actions">
                    <button type="submit" class="btn btn-primary">Update Appointment</button>
                    <a href="view.php?id=<?php echo $appointmentId; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
