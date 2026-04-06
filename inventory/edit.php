<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = 'Edit Inventory Item';

$db = Database::getInstance();
$id = $_GET['id'] ?? 0;
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
            cost_per_unit = ?, expiry_date = ?
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
        logAction('UPDATE', 'inventory', $id, $item, $_POST);
        header('Location: index.php');
        exit;
    } else {
        $error = 'Update failed';
    }
}

include '../layouts/header.php';
?>


<div class="container-fluid">
    <h1 class="h3 mb-4 inventory-add-title">Edit Inventory Item</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <div class="card inventory-card">
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

                <hr>

                <button type="submit" class="btn btn-primary">
                    Update Item
                </button>

                <a href="index.php" class="btn cancel-btn btn-secondary">
                    Cancel
                </a>

            </form>

        </div>
    </div>

</div>

<?php include '../layouts/footer.php'; ?>
