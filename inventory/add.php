<?php
require_once __DIR__ . '/../includes/bootstrap.php';

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
        if ($id > 0) {
            sync_push_row_now('inventory', (int) $id);
        }

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
            $trxId = (int) $stmt2->insert_id;
            if ($trxId > 0) {
                sync_push_row_now('inventory_transactions', $trxId);
            }
        }

        logAction('CREATE', 'inventory', $id, null, $_POST);

        header('Location: index.php');
        exit;
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid bills-page inventory-add-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-box-open me-2 opacity-90" aria-hidden="true"></i>Add inventory item
                </h2>
                <p class="mb-0 opacity-90">Register stock, units, supplier, and reorder levels — saved to your inventory list.</p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 appointments-add-top-actions">
                <a href="index.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Inventory</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
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
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invAddName">Item Name *</label>
                                <input type="text" class="form-control form-control-modern" id="invAddName" name="item_name" required>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invAddCategory">Category</label>
                                <select class="form-select form-control-modern" id="invAddCategory" name="category">
                                    <option value=""></option>
                                    <option value="Consumable">Consumable</option>
                                    <option value="Equipment">Equipment</option>
                                    <option value="Medicine">Medicine</option>
                                    <option value="Material">Material</option>
                                </select>
                            </div>

                            <div class="col-12 col-sm-6 col-md-3 mb-3">
                                <label class="form-label" for="invAddQty">Quantity</label>
                                <input type="number" class="form-control form-control-modern" id="invAddQty" name="quantity" value="0" min="0">
                            </div>

                            <div class="col-12 col-sm-6 col-md-3 mb-3">
                                <label class="form-label" for="invAddUnit">Unit</label>
                                <select class="form-select form-control-modern" id="invAddUnit" name="unit">
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

                            <div class="col-12 col-sm-6 col-md-3 mb-3">
                                <label class="form-label" for="invAddReorder">Reorder Level</label>
                                <input type="number" class="form-control form-control-modern" id="invAddReorder" name="reorder_level" value="10" min="0">
                            </div>

                            <div class="col-12 col-sm-6 col-md-3 mb-3">
                                <label class="form-label" for="invAddReorderQty">Reorder Quantity</label>
                                <input type="number" class="form-control form-control-modern" id="invAddReorderQty" name="reorder_quantity" value="0" min="0">
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invAddSupplier">Supplier Name</label>
                                <input type="text" class="form-control form-control-modern" id="invAddSupplier" name="supplier_name">
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invAddContact">Supplier Contact</label>
                                <input type="text" class="form-control form-control-modern" id="invAddContact" name="supplier_contact">
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invAddCost">Cost per Unit ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" id="invAddCost" name="cost_per_unit" value="0">
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invAddExpiry">Expiry Date</label>
                                <input type="date" class="form-control form-control-modern" id="invAddExpiry" name="expiry_date">
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-lg-end gap-2 flex-wrap inventory-add-form-actions">
                            <button type="submit" class="btn-green">Add Item</button>
                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
