<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = 'Edit Inventory Item';

$db = Database::getInstance();
$id = (int) ($_GET['id'] ?? 0);
$item = $db->fetchOne("SELECT * FROM inventory WHERE id = ?", [$id], "i");

if (!$item) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $expiry = $_POST['expiry_date'] ?? '';
    $expiry = ($expiry === '') ? null : $expiry;

    $result = $db->execute(
        "UPDATE inventory SET 
            item_name = ?, category = ?, quantity = ?, unit = ?,
            reorder_level = ?, reorder_quantity = ?, supplier_name = ?, supplier_contact = ?,
            cost_per_unit = ?, expiry_date = ?, sync_status = 'pending'
         WHERE id = ?",
        [
            $_POST['item_name'],
            $_POST['category'] ?? null,
            intval($_POST['quantity']),
            $_POST['unit'] ?? null,
            intval($_POST['reorder_level']),
            intval($_POST['reorder_quantity']),
            $_POST['supplier_name'] ?? null,
            $_POST['supplier_contact'] ?? null,
            floatval($_POST['cost_per_unit']),
            $expiry,
            $id
        ],
        "ssisiissdsi"
    );

    if ($result !== false) {
        sync_push_row_now('inventory', (int) $id);
        logAction('UPDATE', 'inventory', $id, $item, $_POST);
        header('Location: index.php');
        exit;
    } else {
        $error = 'Update failed';
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid bills-page inventory-edit-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-edit me-2 opacity-90" aria-hidden="true"></i>Edit inventory item
                </h2>
                <p class="mb-0 opacity-90"><?php echo htmlspecialchars((string) ($item['item_name'] ?? '')); ?> — update quantity, supplier, and reorder settings.</p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 appointments-add-top-actions">
                <a href="view.php?id=<?php echo $id; ?>" class="btn appointments-add-top-btn appointments-view-header-edit-btn">
                    <i class="fas fa-eye" aria-hidden="true"></i> View item
                </a>
                <a href="index.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Inventory</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars((string) $error); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12">
            <div class="card bills-dash-section-card inventory-add-form-card queue-registration-card inventory-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-clipboard-list me-2" aria-hidden="true"></i>Item details</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">

                    <form method="POST">

                        <div class="row">

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Name *</label>
                                <input type="text" class="form-control" name="item_name" required
                                       value="<?php echo htmlspecialchars($item['item_name'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category">
                                    <option value=""></option>
                                    <option value="Consumable" <?php echo (($item['category'] ?? '') === 'Consumable') ? 'selected' : ''; ?>>Consumable</option>
                                    <option value="Equipment" <?php echo (($item['category'] ?? '') === 'Equipment') ? 'selected' : ''; ?>>Equipment</option>
                                    <option value="Medicine" <?php echo (($item['category'] ?? '') === 'Medicine') ? 'selected' : ''; ?>>Medicine</option>
                                    <option value="Material" <?php echo (($item['category'] ?? '') === 'Material') ? 'selected' : ''; ?>>Material</option>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" class="form-control" name="quantity"
                                       value="<?php echo intval($item['quantity']); ?>" min="0">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Unit</label>
                                <select class="form-select" name="unit">
                                    <option value=""></option>
                                    <?php
                                    $units = ['ml', 'box', 'bottle', 'pcs', 'set', 'pack', 'tube', 'syringe'];
                                    $curUnit = $item['unit'] ?? '';
                                    foreach ($units as $u) {
                                        $sel = ($curUnit === $u) ? ' selected' : '';
                                        echo '<option value="' . htmlspecialchars($u) . '"' . $sel . '>' . htmlspecialchars($u) . '</option>';
                                    }
                                    if ($curUnit && !in_array($curUnit, $units, true)) {
                                        echo '<option value="' . htmlspecialchars($curUnit) . '" selected>' . htmlspecialchars($curUnit) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Reorder Level</label>
                                <input type="number" class="form-control" name="reorder_level"
                                       value="<?php echo intval($item['reorder_level']); ?>" min="0">
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Reorder Quantity</label>
                                <input type="number" class="form-control" name="reorder_quantity"
                                       value="<?php echo intval($item['reorder_quantity']); ?>" min="0">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier Name</label>
                                <input type="text" class="form-control" name="supplier_name"
                                       value="<?php echo htmlspecialchars($item['supplier_name'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Supplier Contact</label>
                                <input type="text" class="form-control" name="supplier_contact"
                                       value="<?php echo htmlspecialchars($item['supplier_contact'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cost per Unit ($)</label>
                                <input type="number" step="0.01" class="form-control" name="cost_per_unit"
                                       value="<?php echo floatval($item['cost_per_unit']); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" name="expiry_date"
                                       value="<?php echo htmlspecialchars($item['expiry_date'] && $item['expiry_date'] !== '0000-00-00' ? $item['expiry_date'] : ''); ?>">
                            </div>

                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-lg-end gap-2 flex-wrap inventory-add-form-actions">
                            <button type="submit" class="btn-green">Update Item</button>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
