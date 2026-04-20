<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$planId = $_GET['id'] ?? 0;

$plan = $db->fetchOne(
    "SELECT * FROM treatment_plans WHERE id = ?",
    [$planId],
    "i"
);

if (!$plan) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Edit Treatment Plan';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
        $_POST['teeth_affected'] ?? null,
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

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Edit Treatment Plan</h1>
        <div>
            <a href="view.php?id=<?php echo $planId; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> View Plan
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

    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Plan Name *</label>
                        <input type="text" class="form-control" name="plan_name" 
                               value="<?php echo htmlspecialchars($plan['plan_name']); ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient</label>
                        <p class="form-control-static">
                            <?php
                            $patient = $db->fetchOne("SELECT full_name FROM patients WHERE id = ?", [$plan['patient_id']], "i");
                            echo htmlspecialchars($patient['full_name']);
                            ?>
                        </p>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($plan['description']); ?></textarea>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teeth Affected</label>
                        <input type="text" class="form-control" name="teeth_affected" 
                               value="<?php echo htmlspecialchars($plan['teeth_affected']); ?>"
                               placeholder="e.g., 18,19,20">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="proposed" <?php echo $plan['status'] == 'proposed' ? 'selected' : ''; ?>>Proposed</option>
                            <option value="approved" <?php echo $plan['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="in-progress" <?php echo $plan['status'] == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo $plan['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $plan['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Priority</label>
                        <select class="form-select" name="priority">
                            <option value="low" <?php echo $plan['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="medium" <?php echo $plan['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="high" <?php echo $plan['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="emergency" <?php echo $plan['priority'] == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estimated Cost ($)</label>
                        <input type="number" step="0.01" class="form-control" name="estimated_cost" 
                               value="<?php echo $plan['estimated_cost']; ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Discount ($)</label>
                        <input type="number" step="0.01" class="form-control" name="discount" 
                               value="<?php echo $plan['discount']; ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date" 
                               value="<?php echo $plan['start_date']; ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estimated End Date</label>
                        <input type="date" class="form-control" name="estimated_end_date" 
                               value="<?php echo $plan['estimated_end_date']; ?>">
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($plan['notes']); ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">Update Plan</button>
                    <a href="view.php?id=<?php echo $planId; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
