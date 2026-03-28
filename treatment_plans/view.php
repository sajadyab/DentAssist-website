<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$planId = $_GET['id'] ?? 0;

// Fetch treatment plan with patient and creator info
$plan = $db->fetchOne(
    "SELECT tp.*, p.full_name as patient_name, p.date_of_birth, p.phone, p.email,
            u.full_name as created_by_name
     FROM treatment_plans tp
     JOIN patients p ON tp.patient_id = p.id
     LEFT JOIN users u ON tp.created_by = u.id
     WHERE tp.id = ?",
    [$planId],
    "i"
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
    "SELECT * FROM treatment_steps WHERE plan_id = ? ORDER BY step_number",
    [$planId],
    "i"
);

$pageTitle = 'Treatment Plan: ' . $plan['plan_name'];

include '../layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header with Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="fas fa-notes-medical"></i> Treatment Plan: <?php echo htmlspecialchars($plan['plan_name']); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $planId; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="delete.php?id=<?php echo $planId; ?>" class="btn btn-danger" 
               onclick="return confirm('Are you sure you want to delete this treatment plan? This will also delete all associated steps.');">
                <i class="fas fa-trash"></i> Delete
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Main Content: Plan Details and Steps -->
        <div class="col-md-8">
            <!-- Plan Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Plan Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Patient:</label>
                            <p class="mb-0">
                                <a href="../patients/view.php?id=<?php echo $plan['patient_id']; ?>">
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
                                    'cancelled' => 'danger'
                                ];
                                $color = $statusColors[$plan['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($plan['status']); ?></span>
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
                                    'emergency' => 'danger'
                                ];
                                $pColor = $priorityColors[$plan['priority']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $pColor; ?>"><?php echo ucfirst($plan['priority']); ?></span>
                            </p>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="fw-bold">Teeth Affected:</label>
                            <p class="mb-0"><?php echo $plan['teeth_affected'] ?? 'None specified'; ?></p>
                        </div>
                    </div>

                    <?php if ($plan['description']): ?>
                        <div class="mb-3">
                            <label class="fw-bold">Description:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></p>
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
                            <p class="mb-0"><?php echo $plan['start_date'] ? formatDate($plan['start_date']) : 'Not set'; ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Estimated End:</label>
                            <p class="mb-0"><?php echo $plan['estimated_end_date'] ? formatDate($plan['estimated_end_date']) : 'Not set'; ?></p>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="fw-bold">Actual End:</label>
                            <p class="mb-0"><?php echo $plan['actual_end_date'] ? formatDate($plan['actual_end_date']) : 'Not set'; ?></p>
                        </div>
                    </div>

                    <?php if ($plan['notes']): ?>
                        <div class="mb-3">
                            <label class="fw-bold">Notes:</label>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($plan['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Treatment Steps Card -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Treatment Steps</h5>
                    <button class="btn btn-sm btn-primary" onclick="openStepModal()">
                        <i class="fas fa-plus"></i> Add Step
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($steps)): ?>
                        <p class="text-muted">No steps have been added to this treatment plan yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-sm">
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
                                            'skipped' => 'warning'
                                        ];
                                        $sColor = $stepColors[$step['status']] ?? 'secondary';
                                        ?>
                                        <tr>
                                            <td><?php echo $step['step_number']; ?></td>
                                            <td><?php echo htmlspecialchars($step['procedure_name']); ?></td>
                                            <td><?php echo $step['tooth_numbers']; ?></td>
                                            <td><?php echo $step['duration_minutes']; ?> min</td>
                                            <td><?php echo formatCurrency($step['cost']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $sColor; ?>"><?php echo ucfirst($step['status']); ?></span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" onclick="editStep(<?php echo $step['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" onclick="updateStepStatus(<?php echo $step['id']; ?>, 'completed')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteStep(<?php echo $step['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
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

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Patient Quick Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Patient Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($plan['patient_name']); ?></p>
                    <p><strong>DOB:</strong> <?php echo formatDate($plan['date_of_birth']); ?></p>
                    <p><strong>Phone:</strong> <?php echo $plan['phone']; ?></p>
                    <p><strong>Email:</strong> <?php echo $plan['email']; ?></p>
                    <a href="../patients/view.php?id=<?php echo $plan['patient_id']; ?>" class="btn btn-sm btn-primary w-100">
                        View Full Profile
                    </a>
                </div>
            </div>

            <!-- Approval Status -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Approval</h5>
                </div>
                <div class="card-body">
                    <p>
                        <strong>Patient Approved:</strong>
                        <?php if ($plan['patient_approved']): ?>
                            <span class="badge bg-success">Yes</span><br>
                            <small>Approved on <?php echo formatDate($plan['approval_date']); ?></small>
                        <?php else: ?>
                            <span class="badge bg-warning">Pending</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($plan['approval_signature']): ?>
                        <p><strong>Signature:</strong> <img src="<?php echo $plan['approval_signature']; ?>" alt="Signature" style="max-width: 100%;"></p>
                    <?php endif; ?>
                    <?php if (!$plan['patient_approved']): ?>
                        <button class="btn btn-success w-100" onclick="markApproved(<?php echo $planId; ?>)">
                            <i class="fas fa-check"></i> Mark Approved
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Metadata -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Metadata</h5>
                </div>
                <div class="card-body">
                    <p><small><strong>Created:</strong> <?php echo formatDate($plan['created_at'], 'M d, Y g:i A'); ?></small></p>
                    <p><small><strong>Created by:</strong> <?php echo $plan['created_by_name'] ?? 'System'; ?></small></p>
                    <p><small><strong>Last updated:</strong> <?php echo formatDate($plan['updated_at'], 'M d, Y g:i A'); ?></small></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Add/Edit Step -->
<div class="modal fade" id="stepModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stepModalTitle">Add Step</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="stepForm">
                    <input type="hidden" id="stepId" name="id">
                    <input type="hidden" name="plan_id" value="<?php echo $planId; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Step Number *</label>
                        <input type="number" class="form-control" name="step_number" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Procedure Name *</label>
                        <input type="text" class="form-control" name="procedure_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tooth Numbers</label>
                        <input type="text" class="form-control" name="tooth_numbers" placeholder="e.g., 18,19,20">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" name="duration_minutes">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cost ($)</label>
                            <input type="number" step="0.01" class="form-control" name="cost">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="pending">Pending</option>
                            <option value="in-progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="skipped">Skipped</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveStep()">Save Step</button>
            </div>
        </div>
    </div>
</div>

<script>
// Step management functions
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
                document.querySelector('[name="step_number"]').value = step.step_number;
                document.querySelector('[name="procedure_name"]').value = step.procedure_name;
                document.querySelector('[name="description"]').value = step.description;
                document.querySelector('[name="tooth_numbers"]').value = step.tooth_numbers;
                document.querySelector('[name="duration_minutes"]').value = step.duration_minutes;
                document.querySelector('[name="cost"]').value = step.cost;
                document.querySelector('[name="status"]').value = step.status;
                document.querySelector('[name="notes"]').value = step.notes;
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