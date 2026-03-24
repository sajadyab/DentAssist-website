<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = 'Stock Transaction';

$db = Database::getInstance();
$itemId = $_GET['id'] ?? 0;
$item = $db->fetchOne("SELECT * FROM inventory WHERE id = ?", [$itemId], "i");

if (!$item) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['type'];
    $quantity = intval($_POST['quantity']);
    $reason = $_POST['reason'] ?? '';

    if ($quantity <= 0) {
        $error = 'Quantity must be positive';
    } else {
        $newQuantity = $item['quantity'];
        if ($type == 'purchase' || $type == 'return') {
            $newQuantity += $quantity;
        } elseif ($type == 'use' || $type == 'adjustment') {
            $newQuantity -= $quantity;
            if ($newQuantity < 0) {
                $error = 'Insufficient stock';
            }
        }

        if (!$error) {
            $db->insert(
                "INSERT INTO inventory_transactions (inventory_id, transaction_type, quantity_change, new_quantity, reason, performed_by)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$itemId, $type, ($type == 'purchase' || $type == 'return' ? $quantity : -$quantity), $newQuantity, $reason, Auth::userId()],
                "isiiis"
            );

            $db->execute(
                "UPDATE inventory SET quantity = ? WHERE id = ?",
                [$newQuantity, $itemId],
                "ii"
            );

            logAction('TRANSACTION', 'inventory', $itemId, null, $_POST);
            header('Location: view.php?id=' . $itemId);
            exit;
        }
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Stock Transaction: <?php echo htmlspecialchars($item['item_name']); ?></h1>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <p>Current Quantity: <strong><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></strong></p>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Transaction Type</label>
                    <select class="form-select" name="type" required>
                        <option value="purchase">Purchase (add stock)</option>
                        <option value="use">Use (remove stock)</option>
                        <option value="adjustment">Adjustment (manual)</option>
                        <option value="return">Return (add stock)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-control" name="quantity" min="1" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason / Notes</label>
                    <textarea class="form-control" name="reason" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Record Transaction</button>
                <a href="view.php?id=<?php echo $itemId; ?>" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>