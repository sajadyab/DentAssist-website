<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = 'Create Treatment Plan';

$db = Database::getInstance();
$patientId = $_GET['patient_id'] ?? 0;

// Get patient if specified
$patient = null;
if ($patientId) {
    $patient = $db->fetchOne(
        "SELECT * FROM patients WHERE id = ?",
        [$patientId],
        "i"
    );
}

// Get all patients for dropdown
$patients = $db->fetchAll("SELECT id, full_name FROM patients ORDER BY full_name");

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get logged-in user ID
    $createdBy = $_SESSION['user_id'] ?? 0;
    $planId = $db->insert(
        "INSERT INTO treatment_plans (
            patient_id, plan_name, description, teeth_affected,
            estimated_cost, discount, status, priority,
            start_date, estimated_end_date, notes, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
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
            $createdBy
        ],
        "issdddsssssi" // types: i, s, s, s, d, d, s, s, s, s, s, i
    );

    if ($planId) {
        logAction('INSERT', 'treatment_plans', $planId, null, $_POST);
        $success = 'Treatment plan created successfully';
        // Redirect after a short delay or immediately
        header("Location: view.php?id=$planId");
        exit;
    } else {
        $error = 'Error creating treatment plan';
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Create Treatment Plan</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
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
                        <label class="form-label">Patient *</label>
                        <select class="form-select" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>" 
                                    <?php echo ($patient && $patient['id'] == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Plan Name *</label>
                        <input type="text" class="form-control" name="plan_name" required>
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Teeth Affected</label>
                        <input type="text" class="form-control" name="teeth_affected"
                               placeholder="e.g., 18,19,20">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="proposed" selected>Proposed</option>
                            <option value="approved">Approved</option>
                            <option value="in-progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Priority</label>
                        <select class="form-select" name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estimated Cost ($)</label>
                        <input type="number" step="0.01" class="form-control" name="estimated_cost" value="0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Discount ($)</label>
                        <input type="number" step="0.01" class="form-control" name="discount" value="0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control" name="start_date">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Estimated End Date</label>
                        <input type="date" class="form-control" name="estimated_end_date">
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">Create Plan</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>