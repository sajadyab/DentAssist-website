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
                $treatmentId = $db->lastInsertId();
                $success = __('treatment_added');
                logAction('CREATE', 'treatments', $treatmentId, null, $_POST);
                sync_push_row_now('treatments', $treatmentId);
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
                    sync_push_row_now('treatments', $treatmentId);
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
                queueCloudDeletion('treatments', $treatmentId);
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


<div class="container-fluid bills-page treatments-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-tooth me-2 opacity-90" aria-hidden="true"></i><?php echo __('manage_treatments'); ?>
                </h2>
                <p class="mb-0 opacity-90"><?php echo __('treatments_description'); ?></p>
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

    <div class="row g-3 g-md-4">
        <!-- Left column: Add/Edit form -->
        <div class="col-lg-4">
            <div class="card bills-dash-section-card treatments-form-card h-100">
                <div class="card-header bills-arrivals-header bills-arrivals-header--invoices border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0">
                                <?php echo $editTreatment ? __('edit_treatment') : __('add_new_treatment'); ?>
                            </h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body p-3 p-md-4">
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary small mb-1">
                                <i class="fas fa-tooth text-primary me-1"></i> <?php echo __('treatment_name'); ?> *
                            </label>
                            <input type="text" class="form-control" name="name"
                                   value="<?php echo htmlspecialchars($editTreatment['name'] ?? ''); ?>"
                                   placeholder="<?php echo __('treatment_name_placeholder'); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-secondary small mb-1">
                                <i class="fas fa-dollar-sign text-primary me-1"></i> <?php echo __('treatment_cost'); ?> *
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control" name="cost"
                                       value="<?php echo htmlspecialchars($editTreatment['cost'] ?? ''); ?>"
                                       placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-secondary small mb-1">
                                <i class="fas fa-align-left text-primary me-1"></i> <?php echo __('description'); ?>
                            </label>
                            <textarea class="form-control" name="description" rows="4"
                                      placeholder="<?php echo __('description_placeholder'); ?>"><?php
                                echo htmlspecialchars($editTreatment['description'] ?? '');
                            ?></textarea>
                        </div>

                        <?php if ($editTreatment): ?>
                            <div class="d-grid gap-2 treatments-form-edit-actions">
                                <button type="submit" name="edit_treatment" class="btn btn-green w-100">
                                    <i class="fas fa-save" aria-hidden="true"></i> <?php echo __('update_treatment'); ?>
                                </button>
                                <a href="treatments.php" class="btn btn-outline-secondary w-100 treatments-form-cancel-btn">
                                    <i class="fas fa-times" aria-hidden="true"></i> <?php echo __('cancel'); ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="d-grid gap-2">
                                <button type="submit" name="add_treatment" class="btn btn-green w-100">
                                    <i class="fas fa-plus" aria-hidden="true"></i> <?php echo __('save_treatment'); ?>
                                </button>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right column: Treatments list -->
        <div class="col-lg-8">
            <div class="card bills-dash-section-card treatments-list-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-list me-2" aria-hidden="true"></i><?php echo __('treatments_list'); ?></h5>
                        </div>
                        <div class="flex-shrink-0" style="min-width:1px" aria-hidden="true"></div>
                    </div>
                </div>
                <div class="card-body p-0">
            <?php if (empty($treatments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-tooth fa-4x text-muted mb-3"></i>
                    <p class="text-muted"><?php echo __('no_treatments'); ?></p>
                    <p><?php echo __('add_first_treatment'); ?></p>
                </div>
            <?php else: ?>
                <div class="table-responsive treatments-table-wrap">
                    <table class="table table-hover table-sm mb-0 treatments-index-table">
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
                                    <td><?php echo htmlspecialchars($treatment['name']); ?></td>
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
                                        <div class="table-card-actions treatments-list-row-actions" role="group" aria-label="<?php echo htmlspecialchars(__('actions')); ?>">
                                            <a href="treatments.php?action=edit&id=<?php echo $treatment['id']; ?>"
                                               class="btn btn-sm table-action-btn action-yellow"
                                               title="<?php echo htmlspecialchars(__('edit')); ?>">
                                                <i class="fas fa-edit" aria-hidden="true"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm table-action-btn action-red"
                                                    onclick="deleteTreatment(<?php echo $treatment['id']; ?>, '<?php echo htmlspecialchars($treatment['name'], ENT_QUOTES, 'UTF-8'); ?>')"
                                                    title="<?php echo htmlspecialchars(__('delete')); ?>">
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
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white border-0">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-1"></i> <?php echo __('confirm_delete'); ?>
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
