<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$planId = (int) ($_GET['id'] ?? 0);

// Fetch treatment plan with patient and creator info
$plan = $db->fetchOne(
    "SELECT tp.*, p.full_name as patient_name, p.date_of_birth, p.phone, p.email,
            u.full_name as created_by_name
     FROM treatment_plans tp
     JOIN patients p ON tp.patient_id = p.id
     LEFT JOIN users u ON tp.created_by = u.id
     WHERE tp.id = ?",
    [$planId],
    'i'
);

if (!$plan) {
    $_SESSION['error'] = 'Treatment plan not found.';
    header('Location: index.php');
    exit;
}

// Handle success/error messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Get treatment steps
$steps = $db->fetchAll(
    'SELECT * FROM treatment_steps WHERE plan_id = ? ORDER BY step_number',
    [$planId],
    'i'
);

$pageTitle = 'Treatment Plan: ' . $plan['plan_name'];

include '../layouts/header.php';
?>

<div class="container-fluid bills-page treatment-plans-view-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold treatment-plans-view-title-wrap">
                    <i class="fas fa-notes-medical me-2 opacity-90" aria-hidden="true"></i>Treatment plan
                </h2>
                <p class="mb-0 opacity-90">
                    <?php echo htmlspecialchars((string) ($plan['plan_name'] ?? '')); ?>
                    · <?php echo htmlspecialchars((string) ($plan['patient_name'] ?? '')); ?>
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex flex-column align-items-stretch align-items-lg-end justify-content-center treatment-plans-view-hero-actions-wrap">
                <div class="treatment-plans-view-hero-actions">
                    <div class="treatment-plans-view-hero-row treatment-plans-view-hero-row--primary">
                        <a href="index.php" class="btn treatment-plans-view-hero-btn">
                            <i class="fas fa-arrow-left" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline">Back to Plans</span><span class="d-sm-none">Back</span>
                        </a>
                        <a href="edit.php?id=<?php echo (int) $planId; ?>" class="btn treatment-plans-view-hero-btn">
                            <i class="fas fa-edit" aria-hidden="true"></i> Edit
                        </a>
                    </div>
                    <div class="treatment-plans-view-hero-row treatment-plans-view-hero-row--delete">
                        <a href="delete.php?id=<?php echo (int) $planId; ?>" class="btn treatment-plans-view-hero-btn"
                           onclick="return confirm('Are you sure you want to delete this treatment plan? This will also delete all associated steps.');">
                            <i class="fas fa-trash" aria-hidden="true"></i> Delete
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row appointment-view-cols g-3">
        <div class="col-md-8 appointment-view-main">
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-clipboard-list me-2" aria-hidden="true"></i>Plan details</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Patient:</label>
                            <p class="mb-0">
                                <a href="../patients/view.php?id=<?php echo (int) $plan['patient_id']; ?>">
                                    <?php echo htmlspecialchars($plan['patient_name']); ?>
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Status:</label>
                            <p class="mb-0">
                                <?php
                                $statusColors = [
                                    'proposed' => 'warning',
                                    'approved' => 'info',
                                    'in-progress' => 'primary',
                                    'completed' => 'success',
                                    'cancelled' => 'danger',
                                ];
                                $color = $statusColors[$plan['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst((string) $plan['status']); ?></span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Priority:</label>
                            <p class="mb-0">
                                <?php
                                $priorityColors = [
                                    'low' => 'success',
                                    'medium' => 'info',
                                    'high' => 'warning',
                                    'emergency' => 'danger',
                                ];
                                $pColor = $priorityColors[$plan['priority']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $pColor; ?>"><?php echo ucfirst((string) $plan['priority']); ?></span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Teeth Affected:</label>
                            <p class="mb-0"><?php echo htmlspecialchars((string) ($plan['teeth_affected'] ?? 'None specified')); ?></p>
                        </div>
                    </div>

                    <?php if (!empty($plan['description'])): ?>
                        <div class="mb-3">
                            <label class="fw-bold">Description:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars((string) $plan['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Estimated Cost:</label>
                            <p class="mb-0"><?php echo formatCurrency($plan['estimated_cost'] ?? 0); ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Actual Cost:</label>
                            <p class="mb-0"><?php echo formatCurrency($plan['actual_cost'] ?? 0); ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Discount:</label>
                            <p class="mb-0"><?php echo formatCurrency($plan['discount'] ?? 0); ?></p>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Start Date:</label>
                            <p class="mb-0"><?php echo !empty($plan['start_date']) ? formatDate($plan['start_date']) : 'Not set'; ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Estimated End:</label>
                            <p class="mb-0"><?php echo !empty($plan['estimated_end_date']) ? formatDate($plan['estimated_end_date']) : 'Not set'; ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Actual End:</label>
                            <p class="mb-0"><?php echo !empty($plan['actual_end_date']) ? formatDate($plan['actual_end_date']) : 'Not set'; ?></p>
                        </div>
                    </div>

                    <?php if (!empty($plan['notes'])): ?>
                        <div class="mb-0">
                            <label class="fw-bold">Notes:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars((string) $plan['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0 py-2">
                    <div class="bills-arrivals-section-header__inner align-items-center justify-content-between flex-wrap gap-2 w-100">
                        <div class="min-w-0">
                            <h5 class="card-title mb-0"><i class="fas fa-list-ol me-2" aria-hidden="true"></i>Treatment steps</h5>
                        </div>
                        <div class="flex-shrink-0">
                            <button type="button" class="btn btn-sm treatment-plans-add-step-btn" onclick="openStepModal()">
                                <i class="fas fa-plus" aria-hidden="true"></i> Add Step
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($steps)): ?>
                        <p class="text-muted mb-0">No steps have been added to this treatment plan yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Procedure</th>
                                        <th>Tooth</th>
                                        <th>Duration</th>
                                        <th>Cost</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($steps as $step): ?>
                                        <?php
                                        $stepColors = [
                                            'pending' => 'secondary',
                                            'in-progress' => 'primary',
                                            'completed' => 'success',
                                            'skipped' => 'warning',
                                        ];
                                        $sColor = $stepColors[$step['status']] ?? 'secondary';
                                        ?>
                                        <tr>
                                            <td><?php echo (int) $step['step_number']; ?></td>
                                            <td><?php echo htmlspecialchars((string) $step['procedure_name']); ?></td>
                                            <td><?php echo htmlspecialchars((string) $step['tooth_numbers']); ?></td>
                                            <td><?php echo (int) $step['duration_minutes']; ?> min</td>
                                            <td><?php echo formatCurrency($step['cost']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $sColor; ?>"><?php echo ucfirst((string) $step['status']); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="button" class="btn btn-sm btn-info" onclick="editStep(<?php echo (int) $step['id']; ?>)">
                                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-success" onclick="updateStepStatus(<?php echo (int) $step['id']; ?>, 'completed')">
                                                        <i class="fas fa-check" aria-hidden="true"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteStep(<?php echo (int) $step['id']; ?>)">
                                                        <i class="fas fa-trash" aria-hidden="true"></i>
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

        <div class="col-md-4 appointment-view-side">
            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-user me-2" aria-hidden="true"></i>Patient information</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars((string) $plan['patient_name']); ?></p>
                    <p class="mb-2"><strong>DOB:</strong> <?php echo formatDate($plan['date_of_birth']); ?></p>
                    <p class="mb-2"><strong>Phone:</strong> <?php echo htmlspecialchars((string) ($plan['phone'] ?? '')); ?></p>
                    <p class="mb-3"><strong>Email:</strong> <?php echo htmlspecialchars((string) ($plan['email'] ?? '')); ?></p>
                    <a href="../patients/view.php?id=<?php echo (int) $plan['patient_id']; ?>" class="btn btn-sm btn-green w-100">
                        <i class="fas fa-user" aria-hidden="true"></i> View Full Profile
                    </a>
                </div>
            </div>

            <div class="card bills-dash-section-card mb-4">
                <div class="card-header bills-arrivals-header bills-arrivals-header--help border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-check-double me-2" aria-hidden="true"></i>Approval</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        <strong>Patient Approved:</strong>
                        <?php if (!empty($plan['patient_approved'])): ?>
                            <span class="badge bg-success">Yes</span><br>
                            <small>Approved on <?php echo formatDate($plan['approval_date'] ?? null); ?></small>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($plan['approval_signature'])): ?>
                        <p class="mb-2"><strong>Signature:</strong><br><img src="<?php echo htmlspecialchars((string) $plan['approval_signature']); ?>" alt="Signature" class="img-fluid" style="max-width: 100%;"></p>
                    <?php endif; ?>
                    <?php if (empty($plan['patient_approved'])): ?>
                        <button type="button" class="btn btn-green w-100" onclick="markApproved(<?php echo (int) $planId; ?>)">
                            <i class="fas fa-check" aria-hidden="true"></i> Mark Approved
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card bills-dash-section-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-info-circle me-2" aria-hidden="true"></i>Metadata</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-1"><small><strong>Created:</strong> <?php echo formatDate($plan['created_at'], 'M d, Y g:i A'); ?></small></p>
                    <p class="mb-1"><small><strong>Created by:</strong> <?php echo htmlspecialchars((string) ($plan['created_by_name'] ?? 'System')); ?></small></p>
                    <p class="mb-0"><small><strong>Last updated:</strong> <?php echo formatDate($plan['updated_at'], 'M d, Y g:i A'); ?></small></p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="stepModal" tabindex="-1" aria-labelledby="stepModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bills-dash-section-card border-0 queue-registration-card overflow-hidden">
            <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0 rounded-0">
                <div class="bills-arrivals-section-header__inner align-items-center w-100">
                    <div class="min-w-0 flex-grow-1">
                        <h5 class="card-title mb-0" id="stepModalTitle">Add Step</h5>
                    </div>
                </div>
            </div>
            <div class="modal-body card-body pt-3 pb-0">
                <form id="stepForm">
                    <input type="hidden" id="stepId" name="id">
                    <input type="hidden" name="plan_id" value="<?php echo (int) $planId; ?>">

                    <div class="mb-3">
                        <label class="form-label" for="stepNumberInput">Step Number *</label>
                        <input type="number" class="form-control form-control-modern" id="stepNumberInput" name="step_number" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="stepProcedureInput">Procedure Name *</label>
                        <input type="text" class="form-control form-control-modern" id="stepProcedureInput" name="procedure_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="stepDescInput">Description</label>
                        <textarea class="form-control form-control-modern" id="stepDescInput" name="description" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="stepToothInput">Tooth Numbers</label>
                        <input type="text" class="form-control form-control-modern" id="stepToothInput" name="tooth_numbers" placeholder="e.g., 18,19,20">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="stepDurationInput">Duration (minutes)</label>
                            <input type="number" class="form-control form-control-modern" id="stepDurationInput" name="duration_minutes">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="stepCostInput">Cost ($)</label>
                            <input type="number" step="0.01" class="form-control form-control-modern" id="stepCostInput" name="cost">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="stepStatusSelect">Status</label>
                        <select class="form-select form-control-modern" id="stepStatusSelect" name="status">
                            <option value="pending">Pending</option>
                            <option value="in-progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="skipped">Skipped</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="stepNotesInput">Notes</label>
                        <textarea class="form-control form-control-modern" id="stepNotesInput" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-top bg-white">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn-green" onclick="saveStep()">Save Step</button>
            </div>
        </div>
    </div>
</div>

<script>
function openStepModal() {
    document.getElementById('stepModalTitle').innerText = 'Add Step';
    document.getElementById('stepForm').reset();
    document.getElementById('stepId').value = '';
    new bootstrap.Modal(document.getElementById('stepModal')).show();
}

function editStep(id) {
    fetch(`../api/get_step.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('stepModalTitle').innerText = 'Edit Step';
                const step = data.step;
                document.getElementById('stepId').value = step.id;
                document.querySelector('#stepForm [name="step_number"]').value = step.step_number;
                document.querySelector('#stepForm [name="procedure_name"]').value = step.procedure_name;
                document.querySelector('#stepForm [name="description"]').value = step.description;
                document.querySelector('#stepForm [name="tooth_numbers"]').value = step.tooth_numbers;
                document.querySelector('#stepForm [name="duration_minutes"]').value = step.duration_minutes;
                document.querySelector('#stepForm [name="cost"]').value = step.cost;
                document.querySelector('#stepForm [name="status"]').value = step.status;
                document.querySelector('#stepForm [name="notes"]').value = step.notes;
                new bootstrap.Modal(document.getElementById('stepModal')).show();
            } else {
                alert('Error loading step details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while loading step details');
        });
}

function saveStep() {
    const form = document.getElementById('stepForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());

    fetch('../api/save_step.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('stepModal')).hide();
            location.reload();
        } else {
            alert('Error saving step: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the step');
    });
}

function updateStepStatus(stepId, status) {
    fetch('../api/update_step_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({id: stepId, status: status})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error updating step status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating step status');
    });
}

function deleteStep(stepId) {
    if (confirm('Are you sure you want to delete this step?')) {
        fetch('../api/delete_step.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: stepId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting step');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting the step');
        });
    }
}

function markApproved(planId) {
    if (confirm('Mark this treatment plan as approved by the patient?')) {
        fetch('../api/approve_plan.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: planId})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error marking plan as approved');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while marking approval');
        });
    }
}
</script>

<?php include '../layouts/footer.php'; ?>
