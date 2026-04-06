<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
// patients should not view appointment details for others
if (Auth::hasRole('patient')) {
    header('Location: ../patient/index.php');
    exit;
}

$db = Database::getInstance();
$appointmentId = $_GET['id'] ?? 0;

// Get appointment details
$appointment = $db->fetchOne(
    "SELECT a.*, 
            p.full_name as patient_name, p.date_of_birth, p.phone as patient_phone, 
            p.email as patient_email, p.emergency_contact_name, p.emergency_contact_phone,
            p.medical_history, p.allergies,
            u.full_name as doctor_name, u.email as doctor_email,
            creator.full_name as created_by_name
     FROM appointments a
     JOIN patients p ON a.patient_id = p.id
     JOIN users u ON a.doctor_id = u.id
     LEFT JOIN users creator ON a.created_by = creator.id
     WHERE a.id = ?",
    [$appointmentId],
    "i"
);

if (!$appointment) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Appointment: ' . $appointment['patient_name'];

// Get appointment history
$history = $db->fetchAll(
    "SELECT * FROM audit_log 
     WHERE table_name = 'appointments' AND record_id = ?
     ORDER BY performed_at DESC",
    [$appointmentId],
    "i"
);

include '../layouts/header.php';
?>


<div class="container-fluid">
<div class="d-flex justify-content-between align-items-center mb-4 appointment-view-header flex-wrap gap-2">
        <h1 class="h3 appointment-view-title appointment-view-title-wrap mb-0">
            <i class="fas fa-calendar-check"></i> Appointment Details
        </h1>
        <div class="appointment-view-header-btns">
            <button type="button" class="btn btn-warning" onclick="editAppointment()">
                <i class="fas fa-edit"></i> Edit
            </button>
            <button type="button" class="btn btn-danger" onclick="cancelAppointment()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <a href="index.php" class="btn btn-secondary btn-back-mobile">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <!-- Status Bar -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card appointment-status-card">
                <div class="card-body">
                    <div class="d-flex align-items-center appointment-status-bar-inner">
                        <div class="me-3 status-group">
                            <h6 class="mb-1">Status</h6>
                            <?php echo getStatusBadge($appointment['status']); ?>
                        </div>

                        <div class="ms-md-4 status-group">
                            <h6 class="mb-1">Check-in</h6>
                            <span class="badge bg-<?php echo $appointment['status'] == 'checked-in' ? 'success' : 'secondary'; ?>">
                                <?php echo $appointment['status'] == 'checked-in' ? 'Checked In' : 'Not Checked In'; ?>
                            </span>
                        </div>

                        <div class="ms-md-4 status-group">
                            <h6 class="mb-1">Reminders</h6>
                            <div class="d-flex flex-wrap gap-1">
                            <?php if ($appointment['reminder_sent_48h']): ?>
                                <span class="badge bg-success">48h Sent</span>
                            <?php endif; ?>
                            <?php if ($appointment['reminder_sent_24h']): ?>
                                <span class="badge bg-success">24h Sent</span>
                            <?php endif; ?>
                            <?php if (!$appointment['reminder_sent_48h'] && !$appointment['reminder_sent_24h']): ?>
                                <span class="text-muted small">None sent</span>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row appointment-view-cols">
        <!-- Main Details -->
        <div class="col-md-8 appointment-view-main">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Appointment Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Date:</label>
                            <p class="mb-0"><?php echo formatDate($appointment['appointment_date']); ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Time:</label>
                            <p class="mb-0"><?php echo formatTime($appointment['appointment_time']); ?> 
                               (<?php echo $appointment['duration']; ?> minutes)</p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Doctor:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($appointment['doctor_name']); ?></p>
                            <small><?php echo $appointment['doctor_email']; ?></small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Chair Number:</label>
                            <p class="mb-0"><?php echo $appointment['chair_number'] ?? 'Not assigned'; ?></p>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="fw-bold">Treatment Type:</label>
                            <p class="mb-0"><?php echo $appointment['treatment_type']; ?></p>
                        </div>
                        
                        <?php if ($appointment['description']): ?>
                        <div class="col-12 mb-3">
                            <label class="fw-bold">Description:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment['description'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($appointment['notes']): ?>
                        <div class="col-12 mb-3">
                            <label class="fw-bold">Notes:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($appointment['cancellation_reason']): ?>
                        <div class="col-12 mb-3">
                            <label class="fw-bold text-danger">Cancellation Reason:</label>
                            <p class="mb-0"><?php echo htmlspecialchars($appointment['cancellation_reason']); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Patient Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Patient Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Name:</label>
                            <p class="mb-0">
                                <a href="../patients/view.php?id=<?php echo $appointment['patient_id']; ?>">
                                    <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                </a>
                            </p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Date of Birth:</label>
                            <p class="mb-0"><?php echo formatDate($appointment['date_of_birth']); ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Phone:</label>
                            <p class="mb-0"><?php echo $appointment['patient_phone']; ?></p>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Email:</label>
                            <p class="mb-0"><?php echo $appointment['patient_email']; ?></p>
                        </div>
                        
                        <?php if ($appointment['emergency_contact_name']): ?>
                        <div class="col-12 mb-3">
                            <label class="fw-bold">Emergency Contact:</label>
                            <p class="mb-0"><?php echo $appointment['emergency_contact_name']; ?> 
                               (<?php echo $appointment['emergency_contact_phone']; ?>)</p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($appointment['allergies']): ?>
                        <div class="col-12 mb-3">
                            <label class="fw-bold text-warning">Allergies:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment['allergies'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($appointment['medical_history']): ?>
                        <div class="col-12 mb-3">
                            <label class="fw-bold">Medical History:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment['medical_history'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="../patients/view.php?id=<?php echo $appointment['patient_id']; ?>" 
                       class="btn btn-sm btn-primary">
                        <i class="fas fa-user"></i> View Full Patient Profile
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-4 appointment-view-side">
            <!-- Quick Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-success" onclick="updateStatus('checked-in')">
                            <i class="fas fa-sign-in-alt"></i> Check In Patient
                        </button>
                        <button class="btn btn-warning" onclick="updateStatus('in-treatment')">
                            <i class="fas fa-tooth"></i> Start Treatment
                        </button>
                        <button class="btn btn-info" onclick="updateStatus('completed')">
                            <i class="fas fa-check"></i> Mark Completed
                        </button>
                        <button class="btn btn-primary" onclick="sendReminder()">
                            <i class="fas fa-bell"></i> Send Reminder
                        </button>
                        <button class="btn btn-secondary" onclick="createInvoice()">
                            <i class="fas fa-file-invoice"></i> Create Invoice
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Treatment Instructions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Post-Treatment Instructions</h5>
                </div>
                <div class="card-body">
                    <?php
                    $instructions = $db->fetchOne(
                        "SELECT instructions FROM treatment_instructions 
                         WHERE treatment_type = ? OR is_default = 1 
                         ORDER BY is_default LIMIT 1",
                        [$appointment['treatment_type']],
                        "s"
                    );
                    ?>
                    <pre class="appointment-instructions-pre"><?php echo $instructions['instructions'] ?? 'No instructions available'; ?></pre>
                    <button class="btn btn-sm btn-outline-primary w-100" onclick="printInstructions()">
                        <i class="fas fa-print"></i> Print Instructions
                    </button>
                </div>
            </div>
            
            <!-- Metadata -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Metadata</h5>
                </div>
                <div class="card-body">
                    <p class="mb-1"><small><strong>Created:</strong> <?php echo formatDate($appointment['created_at'], 'M d, Y g:i A'); ?></small></p>
                    <p class="mb-1"><small><strong>Created by:</strong> <?php echo $appointment['created_by_name']; ?></small></p>
                    <p class="mb-1"><small><strong>Last updated:</strong> <?php echo formatDate($appointment['updated_at'], 'M d, Y g:i A'); ?></small></p>
                    <p class="mb-0"><small><strong>Invoice ID:</strong> <?php echo $appointment['invoice_id'] ?? 'Not generated'; ?></small></p>
                </div>
            </div>
            
           
<script>
function editAppointment() {
    window.location.href = 'edit.php?id=<?php echo $appointmentId; ?>';
}

function cancelAppointment() {
    const reason = prompt('Please enter cancellation reason:');
    if (reason) {
        fetch('../api/appointments.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: <?php echo $appointmentId; ?>,
                reason: reason
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    }
}

function updateStatus(status) {
    fetch('../api/appointments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: <?php echo $appointmentId; ?>,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (status === 'completed' && data.post_treatment_whatsapp) {
                const w = data.post_treatment_whatsapp;
                if (w.skipped_whatsapp) {
                    alert(w.message || 'Appointment marked complete. No matching treatment instructions — WhatsApp not sent.');
                } else if (w.ok) {
                    let msg = w.message || 'Post-treatment instructions sent via WhatsApp.';
                    if (w.sid) {
                        msg += '\n\nMessage ID: ' + w.sid;
                    }
                    alert(msg);
                } else {
                    alert(
                        'Appointment marked as completed.\n\nWhatsApp (post-treatment instructions):\n' +
                        (w.message || 'Not sent.') +
                        (w.error ? '\n\n' + w.error : '')
                    );
                }
            }
            location.reload();
        } else {
            alert(data.message || 'Could not update status.');
        }
    })
    .catch(function () {
        alert('Network error while updating status.');
    });
}

function sendReminder() {
    if (confirm('Send appointment reminder to patient?')) {
        fetch('../api/send_reminder.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                appointment_id: <?php echo $appointmentId; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            alert(data.message);
        });
    }
}

function createInvoice() {
    window.location.href = '../billing/create_invoice.php?appointment_id=<?php echo $appointmentId; ?>';
}

function printInstructions() {
    const instructions = document.querySelector('.card-body pre').textContent;
    const printWindow = window.open('', '', 'width=600,height=400');
    printWindow.document.write(`
        <html>
        <head>
            <title>Treatment Instructions</title>
            <style>body{font-family:Arial,sans-serif;padding:20px}h1{color:#334155}pre{white-space:pre-wrap}</style>
</head>
        <body>
            <h1>Post-Treatment Instructions</h1>
            <p><strong>Patient:</strong> <?php echo $appointment['patient_name']; ?></p>
            <p><strong>Treatment:</strong> <?php echo $appointment['treatment_type']; ?></p>
            <p><strong>Date:</strong> <?php echo formatDate($appointment['appointment_date']); ?></p>
            <hr>
            <pre>${instructions}</pre>
            <hr>
            <p><em>Generated on ${new Date().toLocaleString()}</em></p>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>

<?php include '../layouts/footer.php'; ?>