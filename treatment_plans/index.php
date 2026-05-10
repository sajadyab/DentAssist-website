<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

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
$planName = trim((string) ($_GET['plan_name'] ?? ''));

// Get patients for filter dropdown
$patients = repo_patient_list_for_select();

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
if ($planName !== '') {
    $where[] = "tp.plan_name LIKE ?";
    $params[] = '%' . $planName . '%';
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

<div class="container-fluid bills-page treatment-plans-index-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-notes-medical me-2 opacity-90" aria-hidden="true"></i>Treatment Plans
                </h2>
                <p class="mb-0 opacity-90">Track multi-step care per patient, priorities, and progress at a glance.</p>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center justify-content-md-end mb-3 mb-md-4 treatment-plans-index-cta-wrap">
        <a href="add.php" class="btn-green staff-cta-mobile-90">
            <i class="fas fa-plus" aria-hidden="true"></i> New Treatment Plan
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
    <div class="card mb-4 treatment-plans-index-filter-card">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end treatment-plans-index-filter-row">
                <div class="col-12 col-lg-3">
                    <label class="form-label">Search by Name</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                        <input type="text" class="form-control" name="plan_name" value="<?php echo htmlspecialchars($planName); ?>" placeholder="Plan name">
                    </div>
                </div>
                <div class="col-12 col-md-6 col-lg-2">
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
                <div class="col-12 col-md-6 col-lg-2">
                    <label class="form-label">Priority</label>
                    <select class="form-select" name="priority">
                        <option value="">All</option>
                        <option value="low" <?php echo $priority == 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $priority == 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $priority == 'high' ? 'selected' : ''; ?>>High</option>
                        <option value="emergency" <?php echo $priority == 'emergency' ? 'selected' : ''; ?>>Emergency</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-3">
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
                <div class="col-12 col-md-6 col-lg-2 treatment-plans-index-filter-apply-col">
                    <label class="form-label treatment-plans-index-filter-apply-label d-none d-md-block">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100 treatment-plans-index-filter-apply-btn">
                        <i class="fas fa-filter" aria-hidden="true"></i> Apply
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
                        <a href="add.php" class="btn-green">Create Treatment Plan</a>
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
                <div class="col-12 col-md-6 col-lg-4 mb-4">
                    <div class="card h-100 shadow-sm treatment-plans-index-card">
                        <div class="card-header treatment-plans-index-card-head d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <h6 class="mb-0">Plan #<?php echo $plan['id']; ?></h6>
                            <span class="badge bg-<?php echo $priorityColor; ?> treatment-plans-index-priority-badge">
                                <i class="fas fa-flag" aria-hidden="true"></i> <?php echo ucfirst($plan['priority']); ?>
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
                        <div class="card-footer bg-transparent border-top treatment-plans-index-card-footer">
                            <div class="treatment-plans-index-card-actions" role="group" aria-label="Plan actions">
                                <a href="view.php?id=<?php echo $plan['id']; ?>"
                                   class="treatment-plans-index-action treatment-plans-index-action--view">
                                    <i class="fas fa-eye" aria-hidden="true"></i><span>View</span>
                                </a>
                                <a href="edit.php?id=<?php echo $plan['id']; ?>"
                                   class="treatment-plans-index-action treatment-plans-index-action--edit">
                                    <i class="fas fa-edit" aria-hidden="true"></i><span>Edit</span>
                                </a>
                                <button type="button" class="treatment-plans-index-action treatment-plans-index-action--delete"
                                        onclick="deletePlan(<?php echo $plan['id']; ?>)">
                                    <i class="fas fa-trash" aria-hidden="true"></i><span>Delete</span>
                                </button>
                                <button type="button" class="treatment-plans-index-action treatment-plans-index-action--print"
                                        onclick="printPlan(<?php echo $plan['id']; ?>)">
                                    <i class="fas fa-print" aria-hidden="true"></i><span>Print</span>
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
