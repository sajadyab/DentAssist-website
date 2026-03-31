<?php
/**
 * treatments.php
 * Manage dental treatments: list, add, edit, delete.
 * Accessible by doctors and admins.
 */

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Only doctors and admins can access
Auth::requireLogin();
if ($_SESSION['role'] != 'doctor' && !Auth::isAdmin()) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();
$action = $_GET['action'] ?? '';
$treatmentId = (int) ($_GET['id'] ?? 0);
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Add Treatment
    if (isset($_POST['add_treatment'])) {
        $name = trim($_POST['name'] ?? '');
        $cost = floatval($_POST['cost'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        if (empty($name) || $cost <= 0) {
            $error = __('invalid_treatment_data');
        } else {
            $result = $db->execute(
                "INSERT INTO treatments (name, cost, description) VALUES (?, ?, ?)",
                [$name, $cost, $description],
                "sds"
            );
            if ($result) {
                $success = __('treatment_added');
                logAction('CREATE', 'treatments', $db->lastInsertId(), null, $_POST);
                header('Location: treatments.php?success=added');
                exit;
            } else {
                $error = __('error_adding_treatment');
            }
        }
    }
    // Handle Edit Treatment
    elseif (isset($_POST['edit_treatment'])) {
        // The ID comes from the URL (GET) when editing
        $treatmentId = (int) ($_GET['id'] ?? 0);
        if ($treatmentId <= 0) {
            $error = __('invalid_treatment_id');
        } else {
            $name = trim($_POST['name'] ?? '');
            $cost = floatval($_POST['cost'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if (empty($name) || $cost <= 0) {
                $error = __('invalid_treatment_data');
            } else {
                $result = $db->execute(
                    "UPDATE treatments SET name = ?, cost = ?, description = ? WHERE id = ?",
                    [$name, $cost, $description, $treatmentId],
                    "sdsi"
                );
                if ($result) {
                    $success = __('treatment_updated');
                    logAction('UPDATE', 'treatments', $treatmentId, null, $_POST);
                    header('Location: treatments.php?success=updated');
                    exit;
                } else {
                    $error = __('error_updating_treatment');
                }
            }
        }
    }
    // Handle Delete Treatment
    elseif (isset($_POST['delete_treatment'])) {
        // The ID comes from the hidden field in the modal form
        $treatmentId = (int) ($_POST['treatment_id'] ?? 0);
        if ($treatmentId <= 0) {
            $error = __('invalid_treatment_id');
        } else {
            $result = $db->execute("DELETE FROM treatments WHERE id = ?", [$treatmentId], "i");
            if ($result) {
                $success = __('treatment_deleted');
                logAction('DELETE', 'treatments', $treatmentId, null, null);
                header('Location: treatments.php?success=deleted');
                exit;
            } else {
                $error = __('error_deleting_treatment');
            }
        }
    }
}

// Check for success message from redirect
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'added':
            $success = __('treatment_added');
            break;
        case 'updated':
            $success = __('treatment_updated');
            break;
        case 'deleted':
            $success = __('treatment_deleted');
            break;
    }
}

// Get treatment for editing (if requested)
$editTreatment = null;
if ($action === 'edit' && $treatmentId > 0) {
    $editTreatment = $db->fetchOne("SELECT * FROM treatments WHERE id = ?", [$treatmentId], "i");
    if (!$editTreatment) {
        header('Location: treatments.php');
        exit;
    }
}

// Fetch all treatments for listing
$treatments = $db->fetchAll("SELECT * FROM treatments ORDER BY name");

$pageTitle = __('manage_treatments');
include 'layouts/header.php'; // This should include your HTML head, navbar, etc.
?>

<style>
    .treatments-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 30px;
        color: white;
    }

    .form-card {
        border-radius: 20px;
        border: none;
        box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .form-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px 25px;
    }

    .table-modern {
        border-radius: 15px;
        overflow: hidden;
    }

    .table-modern thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .table-modern tbody tr:hover {
        background: #f8f9fa;
        transition: background 0.3s ease;
    }

    .cost-badge {
        font-size: 18px;
        font-weight: bold;
        color: #28a745;
    }

    .btn-action {
        padding: 5px 12px;
        border-radius: 8px;
        font-size: 12px;
        transition: all 0.3s ease;
    }

    .btn-action:hover {
        transform: translateY(-2px);
    }
</style>

<div class="container-fluid">
    <!-- Back button to dashboard -->
    <div class="mb-3">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <?php echo __('back_to_dashboard'); ?>
        </a>
    </div>

    <!-- Page Header -->
    <div class="treatments-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="fas fa-tooth"></i> <?php echo __('manage_treatments'); ?>
                </h2>
                <p class="mb-0"><?php echo __('treatments_description'); ?></p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white text-dark rounded p-2">
                    <small><?php echo __('total_treatments'); ?></small>
                    <h3 class="mb-0"><?php echo count($treatments); ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left column: Add/Edit form -->
        <div class="col-md-4">
            <div class="form-card">
                <div class="form-header">
                    <h4 class="mb-0">
                        <i class="fas fa-<?php echo $editTreatment ? 'edit' : 'plus'; ?>"></i>
                        <?php echo $editTreatment ? __('edit_treatment') : __('add_new_treatment'); ?>
                    </h4>
                    <p class="mb-0 mt-2 opacity-75">
                        <?php echo $editTreatment ? __('edit_treatment_instruction') : __('add_treatment_instruction'); ?>
                    </p>
                </div>
                <div class="card-body p-4">
                    <form method="post">
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-tooth text-primary me-2"></i> <?php echo __('treatment_name'); ?> *
                            </label>
                            <input type="text" class="form-control form-control-lg" name="name"
                                   value="<?php echo htmlspecialchars($editTreatment['name'] ?? ''); ?>"
                                   placeholder="<?php echo __('treatment_name_placeholder'); ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-dollar-sign text-primary me-2"></i> <?php echo __('treatment_cost'); ?> *
                            </label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control" name="cost"
                                       value="<?php echo htmlspecialchars($editTreatment['cost'] ?? ''); ?>"
                                       placeholder="0.00" required>
                            </div>
                            <small class="text-muted"><?php echo __('cost_instruction'); ?></small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-align-left text-primary me-2"></i> <?php echo __('description'); ?>
                            </label>
                            <textarea class="form-control" name="description" rows="4"
                                      placeholder="<?php echo __('description_placeholder'); ?>"><?php
                                echo htmlspecialchars($editTreatment['description'] ?? '');
                            ?></textarea>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" name="<?php echo $editTreatment ? 'edit_treatment' : 'add_treatment'; ?>"
                                    class="btn btn-primary btn-lg flex-grow-1">
                                <i class="fas fa-<?php echo $editTreatment ? 'save' : 'plus'; ?>"></i>
                                <?php echo $editTreatment ? __('update_treatment') : __('save_treatment'); ?>
                            </button>
                            <?php if ($editTreatment): ?>
                                <a href="treatments.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right column: Treatments list -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list"></i> <?php echo __('treatments_list'); ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($treatments)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-tooth fa-4x text-muted mb-3"></i>
                            <p class="text-muted"><?php echo __('no_treatments'); ?></p>
                            <p><?php echo __('add_first_treatment'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-modern mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo __('treatment_name'); ?></th>
                                        <th><?php echo __('treatment_cost'); ?></th>
                                        <th><?php echo __('description'); ?></th>
                                        <th><?php echo __('actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($treatments as $index => $treatment): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($treatment['name']); ?></strong>
                                                <br>
                                                <small class="text-muted">ID: #<?php echo $treatment['id']; ?></small>
                                            </td>
                                            <td>
                                                <span class="cost-badge"><?php echo formatCurrency($treatment['cost']); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $desc = htmlspecialchars($treatment['description'] ?? '');
                                                echo strlen($desc) > 60 ? substr($desc, 0, 60) . '...' : ($desc ?: '-');
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="treatments.php?action=edit&id=<?php echo $treatment['id']; ?>"
                                                       class="btn btn-warning btn-action" title="<?php echo __('edit'); ?>">
                                                        <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
                                                    </a>
                                                    <button type="button" class="btn btn-danger btn-action"
                                                            onclick="deleteTreatment(<?php echo $treatment['id']; ?>, '<?php echo htmlspecialchars($treatment['name']); ?>')"
                                                            title="<?php echo __('delete'); ?>">
                                                        <i class="fas fa-trash"></i> <?php echo __('delete'); ?>
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
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo __('confirm_delete'); ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><?php echo __('delete_treatment_confirmation'); ?></p>
                <div class="alert alert-warning">
                    <strong><span id="deleteTreatmentName"></span></strong>
                </div>
                <p class="text-danger small">
                    <i class="fas fa-info-circle"></i> <?php echo __('delete_warning'); ?>
                </p>
            </div>
            <div class="modal-footer">
                <form method="post" id="deleteForm">
                    <input type="hidden" name="treatment_id" id="deleteTreatmentId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> <?php echo __('cancel'); ?>
                    </button>
                    <button type="submit" name="delete_treatment" class="btn btn-danger">
                        <i class="fas fa-trash"></i> <?php echo __('delete_permanently'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function deleteTreatment(id, name) {
        document.getElementById('deleteTreatmentId').value = id;
        document.getElementById('deleteTreatmentName').innerHTML = name;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>

<?php include 'layouts/footer.php'; ?>