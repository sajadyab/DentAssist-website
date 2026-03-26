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
    <style>
        .appointment-view-title-wrap {
            min-width: 0;
        }

        .appointment-view-header-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .appointment-status-bar-inner {
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .appointment-status-bar-inner .status-group {
            flex: 1 1 auto;
            min-width: 0;
        }

        .appointment-view-main .card-header,
        .appointment-view-side .card-header {
            padding: 0.65rem 1rem;
        }

        @media (max-width: 768px) {
            .appointment-view-title {
                font-size: 1.1rem;
                line-height: 1.35;
            }

            .appointment-view-header {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }

            .appointment-view-header > h1 {
                margin-bottom: 0 !important;
            }

            .appointment-view-header-btns {
                display: grid;
                grid-template-columns: 1fr 1fr;
                justify-content: stretch;
            }

            .appointment-view-header-btns .btn {
                width: 100%;
                padding: 0.5rem 0.6rem;
                font-size: 14px;
            }

            .appointment-view-header-btns .btn-secondary {
                grid-column: 1 / -1;
            }

            .appointment-view-header-btns .btn-back-mobile {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
                color: #fff;
            }

            .appointment-view-header-btns .btn-back-mobile:hover {
                background-color: #2980b9;
                border-color: #2980b9;
                color: #fff;
            }

            .appointment-status-card .card-body {
                padding: 0.85rem 1rem;
            }

            .appointment-status-bar-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .appointment-status-bar-inner .ms-4 {
                margin-left: 0 !important;
            }

            .appointment-view-main .card-body,
            .appointment-view-side .card-body,
            .appointment-view-side .card-footer {
                padding: 0.85rem 1rem;
            }

            .appointment-view-cols > .col-md-8,
            .appointment-view-cols > .col-md-4 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .appointment-view-main .row .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .appointment-view-side .d-grid .btn {
                padding: 0.55rem 0.75rem;
                font-size: 14px;
            }
        }
    </style>

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
                    <pre style="white-space: pre-wrap;"><?php echo $instructions['instructions'] ?? 'No instructions available'; ?></pre>
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
            
            <!-- History -->
            <?php if (!empty($history)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">History</h5>
                </div>
                <div class="card-body">
                    <div class="timeline-sm">
                        <?php foreach ($history as $entry): ?>
                            <div class="timeline-item">
                                <div class="timeline-date"><?php echo formatDate($entry['performed_at'], 'g:i A'); ?></div>
                                <div class="timeline-content">
                                    <strong><?php echo $entry['action']; ?></strong>
                                    <p class="small mb-0"><?php echo $entry['ip_address']; ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.timeline-sm {
    position: relative;
    padding-left: 20px;
}

.timeline-sm:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-sm .timeline-item {
    position: relative;
    margin-bottom: 15px;
}

.timeline-sm .timeline-item:before {
    content: '';
    position: absolute;
    left: -24px;
    top: 0;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--primary-color);
}

.timeline-sm .timeline-date {
    font-size: 11px;
    color: #6c757d;
}
</style>

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
            location.reload();
        }
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
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h1 { color: #333; }
                pre { white-space: pre-wrap; }
            </style>
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