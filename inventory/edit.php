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
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $result = $db->execute(
        "UPDATE inventory SET 
            item_name = ?, category = ?, description = ?, quantity = ?, unit = ?,
            reorder_level = ?, reorder_quantity = ?, supplier_name = ?, supplier_contact = ?,
            supplier_phone = ?, supplier_email = ?, cost_per_unit = ?, selling_price = ?,
            expiry_date = ?, lot_number = ?, location = ?, barcode = ?
         WHERE id = ?",
        [
            $_POST['item_name'],
            $_POST['category'] ?? null,
            $_POST['description'] ?? null,
            intval($_POST['quantity']),
            $_POST['unit'] ?? null,
            intval($_POST['reorder_level']),
            intval($_POST['reorder_quantity']),
            $_POST['supplier_name'] ?? null,
            $_POST['supplier_contact'] ?? null,
            $_POST['supplier_phone'] ?? null,
            $_POST['supplier_email'] ?? null,
            floatval($_POST['cost_per_unit']),
            floatval($_POST['selling_price']),
            $_POST['expiry_date'] ?? null,
            $_POST['lot_number'] ?? null,
            $_POST['location'] ?? null,
            $_POST['barcode'] ?? null,
            $id
        ],
        "sssisiissssddssssi"
    );

    if ($result !== false) {
        logAction('UPDATE', 'inventory', $id, $item, $_POST);
        $success = 'Item updated';
        $item = $db->fetchOne("SELECT * FROM inventory WHERE id = ?", [$id], "i");
    } else {
        $error = 'Update failed';
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Edit Inventory Item</h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Item Name *</label>
                        <input type="text" class="form-control" name="item_name" 
                               value="<?php echo htmlspecialchars($item['item_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" class="form-control" name="category" 
                               value="<?php echo htmlspecialchars($item['category'] ?? ''); ?>">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" name="quantity" 
                               value="<?php echo intval($item['quantity']); ?>" min="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Unit</label>
                        <input type="text" class="form-control" name="unit" 
                               value="<?php echo htmlspecialchars($item['unit'] ?? ''); ?>">
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
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Supplier Phone</label>
                        <input type="text" class="form-control" name="supplier_phone" 
                               value="<?php echo htmlspecialchars($item['supplier_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Supplier Email</label>
                        <input type="email" class="form-control" name="supplier_email" 
                               value="<?php echo htmlspecialchars($item['supplier_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location" 
                               value="<?php echo htmlspecialchars($item['location'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Cost per Unit ($)</label>
                        <input type="number" step="0.01" class="form-control" name="cost_per_unit" 
                               value="<?php echo floatval($item['cost_per_unit']); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Selling Price ($)</label>
                        <input type="number" step="0.01" class="form-control" name="selling_price" 
                               value="<?php echo floatval($item['selling_price']); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" class="form-control" name="expiry_date" 
                               value="<?php echo $item['expiry_date'] ?? ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Lot Number</label>
                        <input type="text" class="form-control" name="lot_number" 
                               value="<?php echo htmlspecialchars($item['lot_number'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Barcode</label>
                        <input type="text" class="form-control" name="barcode" 
                               value="<?php echo htmlspecialchars($item['barcode'] ?? ''); ?>">
                    </div>
                </div>
                <hr>
                <button type="submit" class="btn btn-primary">Update Item</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>