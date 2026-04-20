<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();
$pageTitle = 'Create Treatment Plan';

$db = Database::getInstance();
$patientId = (int) ($_GET['patient_id'] ?? 0);

// Get patient if specified
$patient = null;
if ($patientId > 0) {
    $patient = $db->fetchOne(
        'SELECT * FROM patients WHERE id = ?',
        [$patientId],
        'i'
    );
}

// Get all patients for dropdown
$patients = repo_patient_list_for_select();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get logged-in user ID
    $createdBy = $_SESSION['user_id'] ?? 0;
    $columns = [
        'patient_id', 'plan_name', 'description', 'teeth_affected',
        'estimated_cost', 'discount', 'status', 'priority',
        'start_date', 'estimated_end_date', 'notes', 'created_by',
    ];
    $values = [
        $_POST['patient_id'],
        $_POST['plan_name'],
        $_POST['description'] ?? null,
        $_POST['teeth_affected'] ?? null,
        $_POST['estimated_cost'] ?? 0,
        $_POST['discount'] ?? 0,
        $_POST['status'] ?? 'proposed',
        $_POST['priority'] ?? 'medium',
        $_POST['start_date'] ?? null,
        $_POST['estimated_end_date'] ?? null,
        $_POST['notes'] ?? null,
        $createdBy,
    ];
    $types = 'issdddsssssi';
    if (dbColumnExists('treatment_plans', 'sync_status')) {
        $columns[] = 'sync_status';
        $values[] = 'pending';
        $types .= 's';
    }

    $planId = $db->insert(
        'INSERT INTO treatment_plans (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
        $values,
        $types
    );

    if ($planId) {
        sync_push_row_now('treatment_plans', (int) $planId);
        logAction('INSERT', 'treatment_plans', $planId, null, $_POST);
        $success = 'Treatment plan created successfully';
        header('Location: view.php?id=' . (int) $planId);
        exit;
    } else {
        $error = 'Error creating treatment plan';
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid bills-page treatment-plans-add-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold treatment-plans-add-title-wrap">
                    <i class="fas fa-clipboard-list me-2 opacity-90" aria-hidden="true"></i>Create treatment plan
                </h2>
                <p class="mb-0 opacity-90">
                    <?php if ($patient): ?>
                        <?php echo htmlspecialchars((string) ($patient['full_name'] ?? '')); ?> — define the plan, costs, and timeline below.
                    <?php else: ?>
                        Select a patient and define the plan, costs, and timeline below.
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 appointments-add-top-actions">
                <a href="index.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Treatment Plans</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12">
            <div class="card bills-dash-section-card treatment-plans-add-form-card queue-registration-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-folder-open me-2" aria-hidden="true"></i>Treatment plan details</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpAddPatient">Patient *</label>
                                <select class="form-select form-control-modern" id="tpAddPatient" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?php echo (int) $p['id']; ?>"
                                            <?php echo ($patient && (int) $patient['id'] === (int) $p['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpAddPlanName">Plan Name *</label>
                                <input type="text" class="form-control form-control-modern" id="tpAddPlanName" name="plan_name" required>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label" for="tpAddDescription">Description</label>
                                <textarea class="form-control form-control-modern" id="tpAddDescription" name="description" rows="3"></textarea>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpAddTeeth">Teeth Affected</label>
                                <input type="text" class="form-control form-control-modern" id="tpAddTeeth" name="teeth_affected"
                                       placeholder="e.g., 18,19,20">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpAddStatus">Status</label>
                                <select class="form-select form-control-modern" id="tpAddStatus" name="status">
                                    <option value="proposed" selected>Proposed</option>
                                    <option value="approved">Approved</option>
                                    <option value="in-progress">In Progress</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpAddPriority">Priority</label>
                                <select class="form-select form-control-modern" id="tpAddPriority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="emergency">Emergency</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpAddCost">Estimated Cost ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" id="tpAddCost" name="estimated_cost" value="0">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpAddDiscount">Discount ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" id="tpAddDiscount" name="discount" value="0">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpAddStart">Start Date</label>
                                <input type="date" class="form-control form-control-modern" id="tpAddStart" name="start_date">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpAddEnd">Estimated End Date</label>
                                <input type="date" class="form-control form-control-modern" id="tpAddEnd" name="estimated_end_date">
                            </div>

                            <div class="col-12 mb-0">
                                <label class="form-label" for="tpAddNotes">Notes</label>
                                <textarea class="form-control form-control-modern" id="tpAddNotes" name="notes" rows="3"></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-lg-end gap-2 flex-wrap treatment-plans-add-form-actions">
                            <button type="submit" class="btn-green">Create Plan</button>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
