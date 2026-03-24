<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
// only staff users may view appointment listing
if (Auth::hasRole('patient')) {
    header('Location: ../patient/index.php');
    exit;
}
$pageTitle = 'Appointments';

$db = Database::getInstance();

// Filters
$date = $_GET['date'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$doctorId = $_GET['doctor_id'] ?? '';

// Get doctors for filter
$doctors = $db->fetchAll(
    "SELECT id, full_name FROM users WHERE role = 'doctor' ORDER BY full_name"
);

// Check if we have patients
$patientCount = $db->fetchOne("SELECT COUNT(*) as count FROM patients")['count'];

// Build query
$where = ["appointment_date = ?"];
$params = [$date];
$types = "s";

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($doctorId)) {
    $where[] = "doctor_id = ?";
    $params[] = $doctorId;
    $types .= "i";
}

$whereClause = implode(" AND ", $where);

// Get appointments
$appointments = $db->fetchAll(
    "SELECT a.*, 
            p.full_name as patient_name,
            p.phone as patient_phone,
            u.full_name as doctor_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN users u ON a.doctor_id = u.id
     WHERE $whereClause
     ORDER BY a.appointment_time",
    $params,
    $types
);

include '../layouts/header.php';
?>

<style>
    .appointments-page-title {
        margin-bottom: 0.75rem;
    }

    .appointments-page-header {
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .btn-new-appointment {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.55rem 1.25rem;
        font-weight: 600;
        border-radius: 10px;
    }

    .appointments-filters .form-label {
        font-weight: 500;
    }

    .appointments-date-nav {
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .appointments-date-nav .appointments-date-heading {
        text-align: center;
        flex: 1 1 auto;
        min-width: 0;
        font-size: 1.1rem;
    }

    .appointments-table-wrap .table {
        font-size: 0.875rem;
    }

    .appointments-table-wrap .table thead th {
        padding: 0.45rem 0.5rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .appointments-table-wrap .table tbody td {
        padding: 0.35rem 0.5rem;
        vertical-align: middle;
    }

    .appointments-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem;
        max-width: 140px;
    }

    .appointments-actions .btn {
        padding: 0.2rem 0.35rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    #appointmentModal .modal-dialog {
        margin: 0.5rem auto;
    }

    @media (max-width: 575.98px) {
        #appointmentModal .modal-dialog {
            margin: 0;
            max-width: 100%;
            height: 100%;
            min-height: 100%;
        }

        #appointmentModal .modal-content {
            min-height: 100vh;
            border-radius: 0;
            border: 0;
        }

        #appointmentModal .modal-body {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
    }

    @media (max-width: 768px) {
        .appointments-page-title {
            font-size: 1.15rem;
            width: 100%;
        }

        .appointments-page-header .btn {
            width: 100%;
            padding: 0.55rem 0.85rem;
            font-size: 14px;
        }

        .appointments-page-header .btn-new-appointment {
            width: 100%;
            max-width: 320px;
            margin-left: auto;
            margin-right: auto;
            justify-content: center;
        }

        .appointments-filters .card-body {
            padding: 1rem;
        }

        .appointments-filters .form-control,
        .appointments-filters .form-select {
            padding: 0.6rem 0.75rem;
            font-size: 14px;
        }

        .appointments-filters .form-label {
            font-size: 14px;
        }

        .appointments-date-nav .btn {
            flex: 1 1 auto;
            min-width: 0;
            padding: 0.5rem 0.6rem;
            font-size: 14px;
        }

        .appointments-date-nav .appointments-date-heading {
            order: -1;
            width: 100%;
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .appointments-table-wrap .table {
            font-size: 13px;
        }

        .appointments-table-wrap .table thead th,
        .appointments-table-wrap .table tbody td {
            padding: 0.3rem 0.35rem;
        }

        .appointments-actions {
            max-width: 100%;
            gap: 0.3rem;
        }

        #appointmentModal .modal-body .form-control,
        #appointmentModal .modal-body .form-select {
            padding: 0.6rem 0.75rem;
            font-size: 14px;
        }

        #appointmentModal .modal-body .form-label {
            font-size: 14px;
        }

        #appointmentModal .modal-footer .btn {
            padding: 0.55rem 0.85rem;
            font-size: 14px;
        }
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 appointments-page-header">
        <h1 class="h3 appointments-page-title">Appointments</h1>
        <button type="button" class="btn btn-primary btn-new-appointment" onclick="window.location.href='add.php'">
            <i class="fas fa-plus"></i> New Appointment
        </button>
    </div>
    
    <?php if ($patientCount == 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            No patients found in the system. You need to <a href="../patients/add.php">add patients</a> before you can schedule appointments.
        </div>
    <?php endif; ?>
    
    <!-- Filters -->
    <div class="card mb-4 appointments-filters">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Date</label>
                    <input type="date" class="form-control" name="date" value="<?php echo $date; ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <option value="scheduled" <?php echo $status == 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                        <option value="checked-in" <?php echo $status == 'checked-in' ? 'selected' : ''; ?>>Checked In</option>
                        <option value="in-treatment" <?php echo $status == 'in-treatment' ? 'selected' : ''; ?>>In Treatment</option>
                        <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        <option value="no-show" <?php echo $status == 'no-show' ? 'selected' : ''; ?>>No Show</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Doctor</label>
                    <select class="form-select" name="doctor_id">
                        <option value="">All Doctors</option>
                        <?php foreach ($doctors as $doctor): ?>
                            <option value="<?php echo $doctor['id']; ?>" <?php echo $doctorId == $doctor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($doctor['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Date Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-3 appointments-date-nav">
        <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' -1 day')); ?>&status=<?php echo urlencode($status); ?>&doctor_id=<?php echo urlencode($doctorId); ?>"
           class="btn btn-outline-primary">
            <i class="fas fa-chevron-left"></i> <span class="d-none d-sm-inline">Previous Day</span><span class="d-sm-none">Prev</span>
        </a>
        <h4 class="appointments-date-heading mb-0"><?php echo date('l, F j, Y', strtotime($date)); ?></h4>
        <a href="?date=<?php echo date('Y-m-d', strtotime($date . ' +1 day')); ?>&status=<?php echo urlencode($status); ?>&doctor_id=<?php echo urlencode($doctorId); ?>"
           class="btn btn-outline-primary">
            <span class="d-none d-sm-inline">Next Day</span><span class="d-sm-none">Next</span> <i class="fas fa-chevron-right"></i>
        </a>
    </div>
    
    <!-- Appointments List -->
    <div class="card">
        <div class="card-body appointments-table-wrap">
            <?php if (empty($appointments)): ?>
                <p class="text-muted text-center py-4">No appointments found for this date</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Treatment</th>
                                <th>Status</th>
                                <th>Chair</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $apt): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo formatTime($apt['appointment_time']); ?></strong><br>
                                        <small class="text-muted"><?php echo $apt['duration']; ?> min</small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($apt['patient_name']); ?></strong><br>
                                        <small class="text-muted"><?php echo $apt['patient_phone']; ?></small>
                                    </td>
                                    <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                    <td><?php echo $apt['treatment_type']; ?></td>
                                    <td><?php echo getStatusBadge($apt['status']); ?></td>
                                    <td>
                                        <?php if ($apt['chair_number']): ?>
                                            <span class="badge bg-info">Chair <?php echo $apt['chair_number']; ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="appointments-actions">
                                            <button type="button" class="btn btn-sm btn-info"
                                                    onclick="viewAppointment(<?php echo $apt['id']; ?>)"
                                                    title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning"
                                                    onclick="editAppointment(<?php echo $apt['id']; ?>)"
                                                    title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-success"
                                                    onclick="updateStatus(<?php echo $apt['id']; ?>, 'completed')"
                                                    title="Mark Completed">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                    onclick="cancelAppointment(<?php echo $apt['id']; ?>)"
                                                    title="Cancel">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add/Edit Appointment Modal -->
<div class="modal fade" id="appointmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">New Appointment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="appointmentForm">
                    <input type="hidden" id="appointmentId" name="id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Patient *</label>
                            <select class="form-select" id="patientId" name="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php
                                $patients = $db->fetchAll("SELECT id, full_name FROM patients ORDER BY full_name");
                                foreach ($patients as $patient):
                                ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Doctor *</label>
                            <select class="form-select" id="doctorId" name="doctor_id" required>
                                <option value="">Select Doctor</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date *</label>
                            <input type="date" class="form-control" id="appointmentDate" 
                                   name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Time *</label>
                            <input type="time" class="form-control" id="appointmentTime" 
                                   name="appointment_time" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <select class="form-select" id="duration" name="duration">
                                <option value="15">15 minutes</option>
                                <option value="30" selected>30 minutes</option>
                                <option value="45">45 minutes</option>
                                <option value="60">60 minutes</option>
                                <option value="90">90 minutes</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Chair Number</label>
                            <input type="number" class="form-control" id="chairNumber" 
                                   name="chair_number" min="1" max="10">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Treatment Type *</label>
                            <input type="text" class="form-control" id="treatmentType" 
                                   name="treatment_type" required>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="scheduled">Scheduled</option>
                                <option value="checked-in">Checked In</option>
                                <option value="in-treatment">In Treatment</option>
                                <option value="completed">Completed</option>
                                <option value="follow-up">Follow Up</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAppointment()">Save Appointment</button>
            </div>
        </div>
    </div>
</div>

<script>
function showAddAppointmentModal() {
    document.getElementById('modalTitle').textContent = 'New Appointment';
    document.getElementById('appointmentForm').reset();
    document.getElementById('appointmentId').value = '';
    document.getElementById('appointmentDate').value = '<?php echo $date; ?>';
    new bootstrap.Modal(document.getElementById('appointmentModal')).show();
}

function viewAppointment(id) {
    window.location.href = 'view.php?id=' + id;
}

function editAppointment(id) {
    window.location.href = 'edit.php?id=' + id;
}

function saveAppointment() {
    const form = document.getElementById('appointmentForm');
    
    // Check if form is valid
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }
    
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    console.log('Sending data:', data); // Debug log
    
    // Show loading state
    const saveBtn = document.querySelector('#appointmentModal .btn-primary');
    const originalText = saveBtn.textContent;
    saveBtn.textContent = 'Saving...';
    saveBtn.disabled = true;
    
    fetch('../api/appointments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log('Response status:', response.status); // Debug log
        return response.json();
    })
    .then(data => {
        console.log('Response data:', data); // Debug log
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('appointmentModal')).hide();
            location.reload();
        } else {
            alert('Error saving appointment: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error); // Debug log
        alert('Network error: ' + error.message);
    })
    .finally(() => {
        // Reset button state
        saveBtn.textContent = originalText;
        saveBtn.disabled = false;
    });
}

function updateStatus(id, status) {
    if (confirm(`Mark appointment as ${status}?`)) {
        fetch('../api/appointments.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: id, status: status})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

function cancelAppointment(id) {
    const reason = prompt('Please enter cancellation reason:');
    if (reason !== null) {
        fetch('../api/appointments.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: id, reason: reason})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}
</script>

<?php include '../layouts/footer.php'; ?>