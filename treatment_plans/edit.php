<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();

$db = Database::getInstance();
$planId = (int) ($_GET['id'] ?? 0);

$plan = $db->fetchOne(
    'SELECT * FROM treatment_plans WHERE id = ?',
    [$planId],
    'i'
);

if (!$plan) {
    header('Location: index.php');
    exit;
}

$patientRow = $db->fetchOne('SELECT full_name FROM patients WHERE id = ?', [(int) $plan['patient_id']], 'i');
$patientName = (string) ($patientRow['full_name'] ?? '');

$pageTitle = 'Edit Treatment Plan';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rawTeeth = trim((string) ($_POST['teeth_affected'] ?? ''));
    $parts = preg_split('/\s*,\s*/', $rawTeeth) ?: [];
    $validTeeth = [];
    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '' || !ctype_digit($part)) {
            continue;
        }
        $number = (int) $part;
        if ($number < 0 || $number > 32) {
            continue;
        }
        $value = (string) $number;
        if (!in_array($value, $validTeeth, true)) {
            $validTeeth[] = $value;
        }
    }
    $normalizedTeeth = $validTeeth !== [] ? implode(',', $validTeeth) : null;

    $setParts = [
        'plan_name = ?',
        'description = ?',
        'teeth_affected = ?',
        'estimated_cost = ?',
        'discount = ?',
        'status = ?',
        'priority = ?',
        'start_date = ?',
        'estimated_end_date = ?',
        'notes = ?',
    ];
    $values = [
        $_POST['plan_name'],
        $_POST['description'] ?? null,
        $normalizedTeeth !== '' ? $normalizedTeeth : null,
        $_POST['estimated_cost'] ?? 0,
        $_POST['discount'] ?? 0,
        $_POST['status'],
        $_POST['priority'],
        $_POST['start_date'] ?? null,
        $_POST['estimated_end_date'] ?? null,
        $_POST['notes'] ?? null,
    ];
    $types = 'sssddsssss';
    if (dbColumnExists('treatment_plans', 'sync_status')) {
        $setParts[] = "sync_status = 'pending'";
    }
    $values[] = $planId;
    $types .= 'i';

    $result = $db->execute(
        'UPDATE treatment_plans SET ' . implode(', ', $setParts) . ' WHERE id = ?',
        $values,
        $types
    );

    if ($result !== false) {
        sync_push_row_now('treatment_plans', (int) $planId);
        logAction('UPDATE', 'treatment_plans', $planId, $plan, $_POST);
        $success = 'Treatment plan updated successfully';
        // Refresh plan
        $plan = $db->fetchOne("SELECT * FROM treatment_plans WHERE id = ?", [$planId], "i");
    } else {
        $error = 'Error updating treatment plan';
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid bills-page treatment-plans-add-page treatment-plans-edit-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold treatment-plans-add-title-wrap">
                    <i class="fas fa-edit me-2 opacity-90" aria-hidden="true"></i>Edit treatment plan
                </h2>
                <p class="mb-0 opacity-90">
                    <?php echo htmlspecialchars($patientName !== '' ? $patientName : 'Patient'); ?>
                    · <?php echo htmlspecialchars((string) ($plan['plan_name'] ?? '')); ?>
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 flex-wrap appointments-add-top-actions">
                <a href="index.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Treatment Plans</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars((string) $error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars((string) $success); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12 col-xl-9 col-lg-10">
            <div class="card bills-dash-section-card form-card treatment-plans-add-form-card queue-registration-card treatment-plans-edit-main-card">
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
                                <label class="form-label" for="tpEditPlanName">Plan Name *</label>
                                <input type="text" class="form-control form-control-modern" id="tpEditPlanName" name="plan_name"
                                       value="<?php echo htmlspecialchars((string) ($plan['plan_name'] ?? '')); ?>" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpEditPatientName">Patient</label>
                                <input type="text" class="form-control form-control-modern" id="tpEditPatientName"
                                       value="<?php echo htmlspecialchars($patientName); ?>" readonly tabindex="-1" aria-readonly="true">
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label" for="tpEditDescription">Description</label>
                                <textarea class="form-control form-control-modern" id="tpEditDescription" name="description" rows="3"><?php echo htmlspecialchars((string) ($plan['description'] ?? '')); ?></textarea>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpEditTeeth">Teeth Affected</label>
                                <input type="text" class="form-control form-control-modern" id="tpEditTeeth" name="teeth_affected"
                                       inputmode="numeric" pattern="[0-9]+(?:,[0-9]+)*" title="Enter comma-separated tooth numbers from 0 to 32, e.g. 18,19,20"
                                       oninput="this.value = this.value.replace(/[^0-9,]/g, '').replace(/,{2,}/g, ',').replace(/^,|,$/g, '').split(',').map(function(v){return v.trim();}).filter(function(v){return v !== '' && /^[0-9]+$/.test(v) && parseInt(v,10) >= 0 && parseInt(v,10) <= 32;}).map(function(v){return String(parseInt(v,10));}).join(',');"
                                       value="<?php echo htmlspecialchars((string) ($plan['teeth_affected'] ?? '')); ?>"
                                       placeholder="e.g., 18,19,20">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpEditStatus">Status</label>
                                <select class="form-select form-control-modern" id="tpEditStatus" name="status">
                                    <option value="proposed" <?php echo $plan['status'] == 'proposed' ? 'selected' : ''; ?>>Proposed</option>
                                    <option value="approved" <?php echo $plan['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="in-progress" <?php echo $plan['status'] == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $plan['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $plan['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpEditPriority">Priority</label>
                                <select class="form-select form-control-modern" id="tpEditPriority" name="priority">
                                    <option value="low" <?php echo $plan['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $plan['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $plan['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="emergency" <?php echo $plan['priority'] == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpEditCost">Estimated Cost ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" id="tpEditCost" name="estimated_cost"
                                       value="<?php echo htmlspecialchars((string) ($plan['estimated_cost'] ?? '0')); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpEditDiscount">Discount ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" id="tpEditDiscount" name="discount"
                                       value="<?php echo htmlspecialchars((string) ($plan['discount'] ?? '0')); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpEditStart">Start Date</label>
                                <input type="date" class="form-control form-control-modern" id="tpEditStart" name="start_date"
                                       value="<?php echo htmlspecialchars((string) ($plan['start_date'] ?? '')); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="tpEditEnd">Estimated End Date</label>
                                <input type="date" class="form-control form-control-modern" id="tpEditEnd" name="estimated_end_date"
                                       value="<?php echo htmlspecialchars((string) ($plan['estimated_end_date'] ?? '')); ?>">
                            </div>

                            <div class="col-12 mb-0">
                                <label class="form-label" for="tpEditNotes">Notes</label>
                                <textarea class="form-control form-control-modern" id="tpEditNotes" name="notes" rows="3"><?php echo htmlspecialchars((string) ($plan['notes'] ?? '')); ?></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-lg-end gap-2 flex-wrap treatment-plans-add-form-actions">
                            <button type="submit" class="btn-green">Save changes</button>
                            <a href="view.php?id=<?php echo $planId; ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
