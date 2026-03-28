<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = 'Treatment Plans';

$db = Database::getInstance();

// Handle session messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Filters
$status = $_GET['status'] ?? '';
$patientId = $_GET['patient_id'] ?? '';
$priority = $_GET['priority'] ?? '';

// Get patients for filter dropdown
$patients = $db->fetchAll("SELECT id, full_name FROM patients ORDER BY full_name");

// Build query with filters
$where = ["1=1"];
$params = [];
$types = "";

if (!empty($status)) {
    $where[] = "tp.status = ?";
    $params[] = $status;
    $types .= "s";
}
if (!empty($patientId)) {
    $where[] = "tp.patient_id = ?";
    $params[] = $patientId;
    $types .= "i";
}
if (!empty($priority)) {
    $where[] = "tp.priority = ?";
    $params[] = $priority;
    $types .= "s";
}

$whereClause = implode(" AND ", $where);

// Get treatment plans with related data
$plans = $db->fetchAll(
    "SELECT tp.*, 
            p.full_name as patient_name,
            p.phone as patient_phone,
            (SELECT COUNT(*) FROM treatment_steps WHERE plan_id = tp.id) as total_steps,
            (SELECT COUNT(*) FROM treatment_steps WHERE plan_id = tp.id AND status = 'completed') as completed_steps
     FROM treatment_plans tp
     JOIN patients p ON tp.patient_id = p.id
     WHERE $whereClause
     ORDER BY tp.created_at DESC",
    $params,
    $types
);

include '../layouts/header.php';
?>

<div class="container-fluid">
    <!-- Header with New Plan Button -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><i class="fas fa-notes-medical"></i> Treatment Plans</h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Treatment Plan
        </a>
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

    <!-- Filter Card -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <option value="proposed" <?php echo $status == 'proposed' ? 'selected' : ''; ?>>Proposed</option>
                        <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="in-progress" <?php echo $status == 'in-progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Priority</label>
                    <select class="form-select" name="priority">
                        <option value="">All</option>
                        <option value="low" <?php echo $priority == 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $priority == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $priority == 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="emergency" <?php echo $priority == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Patient</label>
                    <select class="form-select" name="patient_id">
                        <option value="">All Patients</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" <?php echo $patientId == $patient['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Plans Grid -->
    <div class="row">
        <?php if (empty($plans)): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-notes-medical fa-3x text-muted mb-3"></i>
                        <h5>No Treatment Plans Found</h5>
                        <p class="text-muted">Create your first treatment plan to get started</p>
                        <a href="add.php" class="btn btn-primary">Create Treatment Plan</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($plans as $plan): ?>
                <?php
                // Priority colors
                $priorityColors = [
                    'low' => 'success',
                    'medium' => 'info',
                    'high' => 'warning',
                    'emergency' => 'danger'
                ];
                $priorityColor = $priorityColors[$plan['priority']] ?? 'secondary';

                // Status colors
                $statusColors = [
                    'proposed' => 'warning',
                    'approved' => 'info',
                    'in-progress' => 'primary',
                    'completed' => 'success',
                    'cancelled' => 'danger'
                ];
                $statusColor = $statusColors[$plan['status']] ?? 'secondary';

                // Progress percentage
                $progress = $plan['total_steps'] > 0 
                    ? round(($plan['completed_steps'] / $plan['total_steps']) * 100) 
                    : 0;
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">Plan #<?php echo $plan['id']; ?></h6>
                            <span class="badge bg-<?php echo $priorityColor; ?>">
                                <i class="fas fa-flag"></i> <?php echo ucfirst($plan['priority']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($plan['plan_name']); ?></h5>
                            <p class="card-text small text-muted">
                                <?php echo htmlspecialchars($plan['description'] ?? 'No description'); ?>
                            </p>

                            <div class="mb-2">
                                <strong>Patient:</strong>
                                <a href="../patients/view.php?id=<?php echo $plan['patient_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($plan['patient_name']); ?>
                                </a>
                            </div>

                            <div class="mb-2">
                                <strong>Status:</strong>
                                <span class="badge bg-<?php echo $statusColor; ?>">
                                    <?php echo ucfirst($plan['status']); ?>
                                </span>
                            </div>

                            <div class="mb-2">
                                <strong>Progress:</strong>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?php echo $statusColor; ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $progress; ?>%"
                                         aria-valuenow="<?php echo $progress; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                                <small class="text-muted"><?php echo $progress; ?>% (<?php echo $plan['completed_steps']; ?>/<?php echo $plan['total_steps']; ?> steps)</small>
                            </div>

                            <div class="mb-1">
                                <strong>Estimated Cost:</strong>
                                <?php echo formatCurrency($plan['estimated_cost'] ?? 0); ?>
                            </div>

                            <?php if ($plan['start_date']): ?>
                                <div class="mb-0">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt"></i> Started: <?php echo formatDate($plan['start_date']); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-transparent">
                            <div class="btn-group w-100">
                                <a href="view.php?id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="edit.php?id=<?php echo $plan['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button class="btn btn-sm btn-danger" onclick="deletePlan(<?php echo $plan['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="printPlan(<?php echo $plan['id']; ?>)">
                                    <i class="fas fa-print"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function deletePlan(id) {
    if (confirm('Are you sure you want to delete this treatment plan? This will also delete all associated steps. This action cannot be undone.')) {
        window.location.href = `delete.php?id=${id}`;
    }
}

function printPlan(id) {
    window.open(`print.php?id=${id}`, '_blank', 'width=800,height=600');
}
</script>

<?php include '../layouts/footer.php'; ?>