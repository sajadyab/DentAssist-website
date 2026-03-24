<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

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
$doctors = $db->fetchAll("SELECT id, full_name FROM users WHERE role = 'doctor' ORDER BY full_name");
$patients = $db->fetchAll("SELECT id, full_name FROM patients ORDER BY full_name");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check availability (excluding current appointment)
    $existing = $db->fetchOne(
        "SELECT id FROM appointments 
         WHERE appointment_date = ? AND appointment_time = ? AND chair_number = ? 
         AND status != 'cancelled' AND id != ?",
        [$_POST['appointment_date'], $_POST['appointment_time'], $_POST['chair_number'], $appointmentId],
        "ssii"
    );

    if ($existing) {
        $error = 'This time slot is already booked for the selected chair';
    } else {
        $result = $db->execute(
            "UPDATE appointments SET
                patient_id = ?, doctor_id = ?, appointment_date = ?, appointment_time = ?,
                duration = ?, treatment_type = ?, description = ?, chair_number = ?, status = ?, notes = ?
             WHERE id = ?",
            [
                $_POST['patient_id'],
                $_POST['doctor_id'],
                $_POST['appointment_date'],
                $_POST['appointment_time'],
                $_POST['duration'] ?? 30,
                $_POST['treatment_type'],
                $_POST['description'] ?? null,
                $_POST['chair_number'] ?? null,
                $_POST['status'] ?? 'scheduled',
                $_POST['notes'] ?? null,
                $appointmentId
            ],
            "iississsssi"
        );

        if ($result !== false) {
            logAction('UPDATE', 'appointments', $appointmentId, $appointment, $_POST);
            $success = 'Appointment updated successfully';
            // Refresh data
            $appointment = $db->fetchOne(
                "SELECT a.*, p.full_name as patient_name FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?",
                [$appointmentId],
                "i"
            );
        } else {
            $error = 'Error updating appointment';
        }
    }
}

include '../layouts/header.php';
?>

<style>
    .appointments-edit-header {
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .appointments-edit-title {
        margin-bottom: 0;
    }

    .appointments-edit-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .appointment-edit-card {
        max-width: 75%;
        margin: 0 auto 1rem;
        border-radius: 12px;
    }

    .appointment-edit-card select.form-select:hover,
    .appointment-edit-card select.form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    }

    @media (max-width: 991.98px) {
        .appointment-edit-card {
            max-width: 90%;
        }
    }

    @media (max-width: 768px) {
        .appointments-edit-title {
            font-size: 1.15rem;
            width: 100%;
        }

        .appointments-edit-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
        }

        .appointments-edit-actions .btn {
            width: 100%;
            padding: 0.5rem 0.6rem;
            font-size: 14px;
        }

        .appointments-edit-actions .btn-secondary {
            grid-column: 1 / -1;
        }

        .appointment-edit-card {
            max-width: 100%;
        }

        .appointment-edit-card .card-body {
            padding: 1rem;
        }

        .appointment-edit-card .form-control,
        .appointment-edit-card .form-select {
            padding: 0.6rem 0.75rem;
            font-size: 14px;
        }

        .appointment-edit-card .form-label {
            font-size: 14px;
        }

        .appointment-edit-card .btn {
            padding: 0.55rem 0.85rem;
            font-size: 14px;
        }

        .appointment-edit-form-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .appointment-edit-form-actions .btn {
            width: 100%;
        }
    }
</style>

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

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card appointment-edit-card">
        <div class="card-body">
            <form method="POST" action="">
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