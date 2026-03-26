<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = 'Add Inventory Item';

$db = Database::getInstance();
$conn = $db->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $item_name = $_POST['item_name'];
    $category = $_POST['category'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 0);
    $unit = $_POST['unit'] ?? null;
    $reorder_level = intval($_POST['reorder_level'] ?? 10);
    $reorder_quantity = intval($_POST['reorder_quantity'] ?? 0);
    $supplier_name = $_POST['supplier_name'] ?? null;
    $supplier_contact = $_POST['supplier_contact'] ?? null;
    $cost_per_unit = floatval($_POST['cost_per_unit'] ?? 0);
    $expiry_date = $_POST['expiry_date'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO inventory
        (item_name, category, quantity, unit, reorder_level, reorder_quantity,
        supplier_name, supplier_contact, cost_per_unit, expiry_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ssisiissdsi",
        $item_name,
        $category,
        $quantity,
        $unit,
        $reorder_level,
        $reorder_quantity,
        $supplier_name,
        $supplier_contact,
        $cost_per_unit,
        $expiry_date,
        Auth::userId()
    );

    if (!$stmt->execute()) {
        $error = "Database error: " . $stmt->error;
    } else {

        $id = $stmt->insert_id;

        // Record initial inventory transaction
        if ($quantity > 0) {

            $stmt2 = $conn->prepare("
                INSERT INTO inventory_transactions
                (inventory_id, transaction_type, quantity_change, new_quantity, performed_by)
                VALUES (?, 'purchase', ?, ?, ?)
            ");

            $stmt2->bind_param(
                "iiii",
                $id,
                $quantity,
                $quantity,
                Auth::userId()
            );

            $stmt2->execute();
        }

        logAction('CREATE', 'inventory', $id, null, $_POST);

        header('Location: index.php');
        exit;
    }
}

include '../layouts/header.php';
?>

<style>
    .inventory-card {
        max-width: 75%;
        margin: 0 auto 1rem;
    }

    .inventory-card select.form-select:hover,
    .inventory-card select.form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    }

    .cancel-btn {
        background-color: #dc3545;
        border-color: #dc3545;
        padding: 0.375rem 0.65rem;
        width: auto;
        min-width: 4.25rem;
    }

    .cancel-btn:hover {
        background-color: #c82333;
        border-color: #bd2130;
    }

    @media (max-width: 992px) {
        .inventory-card {
            max-width: 90%;
        }
    }

    @media (max-width: 768px) {
        .inventory-add-title {
            font-size: 1.15rem;
        }

        .inventory-card {
            max-width: 100%;
            border-radius: 12px;
        }

        .inventory-card .card-body {
            padding: 1rem;
        }

        .inventory-card .form-control,
        .inventory-card .form-select {
            padding: 0.6rem 0.75rem;
            font-size: 14px;
        }

        .inventory-card .form-label {
            font-size: 14px;
        }

        .inventory-card .btn {
            padding: 0.55rem 0.85rem;
            font-size: 14px;
        }
    }
</style>

<div class="container-fluid">

<h1 class="h3 mb-4 inventory-add-title">Add Inventory Item</h1>

<?php if ($error): ?>
<div class="alert alert-danger">
<?php echo $error; ?>
</div>
<?php endif; ?>

<div class="card inventory-card">
<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">
<label class="form-label">Item Name *</label>
<input type="text" class="form-control" name="item_name" required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Category</label>
<select class="form-select" name="category">
<option value=""></option>
<option value="Consumable">Consumable</option>
<option value="Equipment">Equipment</option>
<option value="Medicine">Medicine</option>
<option value="Material">Material</option>
</select>
</div>

<div class="col-md-3 mb-3">
<label class="form-label">Quantity</label>
<input type="number" class="form-control" name="quantity" value="0" min="0">
</div>

<div class="col-md-3 mb-3">
<label class="form-label">Unit</label>
<select class="form-select" name="unit">
<option value=""></option>
<option value="ml">ml</option>
<option value="box">box</option>
<option value="bottle">bottle</option>
<option value="pcs">pcs</option>
<option value="set">set</option>
<option value="pack">pack</option>
<option value="tube">tube</option>
<option value="syringe">syringe</option>
</select>
</div>

<div class="col-md-3 mb-3">
<label class="form-label">Reorder Level</label>
<input type="number" class="form-control" name="reorder_level" value="10" min="0">
</div>

<div class="col-md-3 mb-3">
<label class="form-label">Reorder Quantity</label>
<input type="number" class="form-control" name="reorder_quantity" value="0" min="0">
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Supplier Name</label>
<input type="text" class="form-control" name="supplier_name">
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Supplier Contact</label>
<input type="text" class="form-control" name="supplier_contact">
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Cost per Unit ($)</label>
<input type="number" step="0.01" class="form-control" name="cost_per_unit" value="0">
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Expiry Date</label>
<input type="date" class="form-control" name="expiry_date">
</div>

</div>

<hr>

<button type="submit" class="btn btn-primary">
Add Item
</button>

<a href="index.php" class="btn cancel-btn btn-secondary">
Cancel
</a>

</form>

</div>
</div>

</div>

<?php include '../layouts/footer.php'; ?>