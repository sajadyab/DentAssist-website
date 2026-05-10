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


<div class="container-fluid bills-page appointments-view-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold appointment-view-title-wrap">
                    <i class="fas fa-calendar-check me-2 opacity-90" aria-hidden="true"></i>Appointment details
                </h2>
                <p class="mb-0 opacity-90">
                    <?php echo htmlspecialchars($appointment['patient_name']); ?>
                    · <?php echo formatDate($appointment['appointment_date']); ?>
                    · <?php echo formatTime($appointment['appointment_time']); ?>
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 appointments-add-top-actions appointments-view-top-actions">
                <button type="button" class="btn appointments-add-top-btn appointments-view-header-edit-btn" onclick="editAppointment()">
                    <i class="fas fa-edit" aria-hidden="true"></i> Edit
                </button>
                <a href="index.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Appointments</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <div class="row appointment-view-cols g-3">
        <!-- Main Details -->
        <div class="col-md-8 appointment-view-main">
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-calendar-day me-2" aria-hidden="true"></i>Appointment information</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Status</label>
                            <p class="mb-0"><?php echo getStatusBadge($appointment['status']); ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Check-in</label>
                            <p class="mb-0">
                                <span class="badge bg-<?php echo $appointment['status'] === 'checked-in' ? 'success' : 'secondary'; ?>">
                                    <?php echo $appointment['status'] === 'checked-in' ? 'Checked In' : 'Not Checked In'; ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Reminders</label>
                            <p class="mb-0 d-flex flex-wrap align-items-center gap-1">
                                <?php if (!empty($appointment['reminder_sent_48h'])): ?>
                                    <span class="badge bg-success">48h Sent</span>
                                <?php endif; ?>
                                <?php if (!empty($appointment['reminder_sent_24h'])): ?>
                                    <span class="badge bg-success">24h Sent</span>
                                <?php endif; ?>
                                <?php if (empty($appointment['reminder_sent_48h']) && empty($appointment['reminder_sent_24h'])): ?>
                                    <span class="text-muted small">None sent</span>
                                <?php endif; ?>
                            </p>
                        </div>

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
                        
                        
                        <div class="col-6 mb-3">
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
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-user me-2" aria-hidden="true"></i>Patient information</h5>
                        </div>
                    </div>
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
                            <label class="fw-bold appointments-view-allergies-label">Allergies:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment['allergies'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php
                        $medicalHistoryDisplay = formatPatientMedicalHistoryDisplay($appointment['medical_history'] ?? null);
                        if (trim($medicalHistoryDisplay) !== ''): ?>
                        <div class="col-12 mb-3">
                            <label class="fw-bold">Medical History:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($medicalHistoryDisplay)); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white border-top appointments-view-patient-footer">
                    <a href="../patients/view.php?id=<?php echo $appointment['patient_id']; ?>"
                       class="btn btn-green btn-sm">
                        <i class="fas fa-user" aria-hidden="true"></i> View Full Patient Profile
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-4 appointment-view-side">
            <!-- Quick Actions -->
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-bolt me-2" aria-hidden="true"></i>Quick actions</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2 appointments-view-quick-actions">
                        <button type="button" class="btn btn-success" onclick="updateStatus('checked-in')">
                            <i class="fas fa-sign-in-alt" aria-hidden="true"></i> Check In Patient
                        </button>
                        <button type="button" class="btn btn-warning" onclick="updateStatus('in-treatment')">
                            <i class="fas fa-tooth" aria-hidden="true"></i> Start Treatment
                        </button>
                        <button type="button" class="btn btn-info" onclick="updateStatus('completed')">
                            <i class="fas fa-check" aria-hidden="true"></i> Mark Completed
                        </button>
                        <button type="button" class="btn btn-primary" onclick="sendReminder()">
                            <i class="fas fa-bell" aria-hidden="true"></i> Send Reminder
                        </button>
                        <button type="button" class="btn appointments-view-qa-invoice" onclick="createInvoice()">
                            <i class="fas fa-file-invoice" aria-hidden="true"></i> Create Invoice
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Treatment Instructions -->
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-notes-medical me-2" aria-hidden="true"></i>Post-treatment instructions</h5>
                        </div>
                    </div>
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
            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2" aria-hidden="true"></i>Metadata</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-1"><small><strong>Created:</strong> <?php echo formatDate($appointment['created_at'], 'M d, Y g:i A'); ?></small></p>
                    <p class="mb-1"><small><strong>Created by:</strong> <?php echo htmlspecialchars((string) ($appointment['created_by_name'] ?? '')); ?></small></p>
                    <p class="mb-1"><small><strong>Last updated:</strong> <?php echo formatDate($appointment['updated_at'], 'M d, Y g:i A'); ?></small></p>
                    <p class="mb-0"><small><strong>Invoice ID:</strong> <?php echo $appointment['invoice_id'] ?? 'Not generated'; ?></small></p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editAppointment() {
    window.location.href = 'edit.php?id=<?php echo $appointmentId; ?>';
}
function updateStatus(status) {
    const appointmentId = <?php echo json_encode($appointmentId); ?>;

    fetch('../api/appointments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            id: appointmentId,
            status: status
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert(data.message || 'Could not update status.');
            return;
        }

        // Handle WhatsApp post-treatment notification (only for 'completed' status)
        if (status === 'completed' && data.post_treatment_whatsapp) {
            const w = data.post_treatment_whatsapp;

            if (w.skipped_whatsapp) {
                alert(w.message || 'Appointment marked complete. No matching treatment instructions — WhatsApp not sent.');
            } else if (w.ok) {
                let msg = w.message || 'Post-treatment instructions sent via WhatsApp.';
                if (w.sid) msg += '\n\nMessage ID: ' + w.sid;
                alert(msg);
            } else {
                alert('Appointment marked as completed.\n\nWhatsApp (post-treatment instructions):\n' +
                      (w.message || 'Not sent.') + (w.error ? '\n\n' + w.error : ''));
            }
        }

        // Redirect based on status
        if (status === 'completed') {
            // For completed appointments, go to create invoice page
            window.location.href = '<?php echo url("billing/create_invoice.php"); ?>?appointment_id=' + appointmentId;
        } else {
            // For all other status changes, go back to appointments list
            window.location.href = '<?php echo url("appointments/index.php"); ?>';
        }
    })
    .catch(error => {
        console.error('Error updating status:', error);
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
    const pre = document.querySelector('.appointment-instructions-pre');
    const instructions = pre ? pre.textContent : '';
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
