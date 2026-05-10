<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = 'Stock Transaction';

$db = Database::getInstance();
$itemId = (int) ($_GET['id'] ?? 0);
$item = $db->fetchOne("SELECT * FROM inventory WHERE id = ?", [$itemId], "i");

if (!$item) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'];
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $reason = $_POST['reason'] ?? '';

    $newQuantity = null;
    $quantityChange = null;

    if ($type === 'adjustment') {
        if ($quantity < 0) {
            $error = 'New quantity cannot be negative';
        } else {
            $oldQty = (int) $item['quantity'];
            $newQuantity = $quantity;
            $quantityChange = $newQuantity - $oldQty;
        }
    } else {
        if ($quantity <= 0) {
            $error = 'Quantity must be positive';
        } else {
            $newQuantity = (int) $item['quantity'];
            if ($type == 'purchase' || $type == 'return') {
                $newQuantity += $quantity;
                $quantityChange = $quantity;
            } elseif ($type == 'use') {
                $newQuantity -= $quantity;
                $quantityChange = -$quantity;
                if ($newQuantity < 0) {
                    $error = 'Insufficient stock';
                }
            }
        }
    }

    if (!$error && $newQuantity !== null && $quantityChange !== null) {
        $transactionId = (int) $db->insert(
            "INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity_change, new_quantity, reason, performed_by)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$itemId, $type, $quantityChange, $newQuantity, $reason, Auth::userId()],
            "isiisi"
        );

        $db->execute(
            "UPDATE inventory SET quantity = ?, sync_status = 'pending' WHERE id = ?",
            [$newQuantity, $itemId],
            "ii"
        );
        sync_push_row_now('inventory', (int) $itemId);
        if ($transactionId > 0) {
            sync_push_row_now('inventory_transactions', $transactionId);
        }

        logAction('TRANSACTION', 'inventory', $itemId, null, $_POST);
        header('Location: view.php?id=' . $itemId);
        exit;
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid bills-page inventory-transaction-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-exchange-alt me-2 opacity-90" aria-hidden="true"></i>Stock transaction
                </h2>
                <p class="mb-0 opacity-90">
                    <?php echo htmlspecialchars((string) $item['item_name']); ?>
                    · Current: <?php echo (int) $item['quantity']; ?> <?php echo htmlspecialchars((string) ($item['unit'] ?? '')); ?>
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end patient-add-back-wrap">
                <a href="view.php?id=<?php echo $itemId; ?>" class="btn btn-secondary patient-add-back-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to item</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars((string) $error); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-xl-9 col-lg-10">
            <div class="card bills-dash-section-card transaction-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-clipboard-check me-2" aria-hidden="true"></i>Record transaction</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">

                    <form method="POST" id="transaction-form">
                        <div class="mb-3">
                            <label class="form-label">Transaction Type</label>
                            <select class="form-select" name="type" id="transaction-type" required>
                                <option value="purchase">Purchase (add stock)</option>
                                <option value="use">Use (remove stock)</option>
                                <option value="adjustment">Adjustment (set exact quantity)</option>
                                <option value="return">Return (add stock)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" id="qty-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="transaction-qty" min="1" value="1" required>
                            <small class="text-muted d-block mt-1" id="qty-hint"></small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason / Notes</label>
                            <textarea class="form-control" name="reason" rows="2"></textarea>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn-green">Record Transaction</button>
                            <a href="view.php?id=<?php echo $itemId; ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var typeEl = document.getElementById('transaction-type');
    var qtyEl = document.getElementById('transaction-qty');
    var labelEl = document.getElementById('qty-label');
    var hintEl = document.getElementById('qty-hint');

    function syncQtyField() {
        var t = typeEl.value;
        if (t === 'adjustment') {
            labelEl.textContent = 'New quantity (exact stock level)';
            hintEl.textContent = 'Enter the total quantity this item should have after this adjustment.';
            qtyEl.min = '0';
            qtyEl.removeAttribute('max');
            if (parseInt(qtyEl.value, 10) < 0) qtyEl.value = '0';
        } else {
            labelEl.textContent = 'Quantity';
            hintEl.textContent = '';
            qtyEl.min = '1';
            qtyEl.removeAttribute('max');
            if (parseInt(qtyEl.value, 10) < 1) qtyEl.value = '1';
        }
    }

    typeEl.addEventListener('change', syncQtyField);
    syncQtyField();
})();
</script>

<?php include '../layouts/footer.php'; ?>
