<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$id = $_GET['id'] ?? 0;
$item = $db->fetchOne("SELECT * FROM inventory WHERE id = ?", [$id], "i");

if (!$item) {
    header('Location: index.php');
    exit;
}

$transactions = $db->fetchAll(
    "SELECT t.*, u.full_name as user_name 
     FROM inventory_transactions t
     LEFT JOIN users u ON t.performed_by = u.id
     WHERE t.inventory_id = ?
     ORDER BY t.performed_at DESC",
    [$id],
    "i"
);

$pageTitle = 'Inventory: ' . $item['item_name'];

include '../layouts/header.php';
?>

<div class="container-fluid">
    <style>
        @media (max-width: 768px) {
            .inventory-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .inventory-header .btn {
                display: inline-block;
                width: auto;
                min-width: unset;
                padding: 0.375rem 0.65rem;
                white-space: nowrap;
            }

            .inventory-header .btn + .btn {
                margin-left: 0.35rem;
            }

            .inventory-cols .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .inventory-header {
                flex-wrap: wrap;
            }

            .inventory-header > h1 {
                flex: 1 1 100%;
                margin-bottom: 0.5rem;
            }

            .inventory-header > div {
                flex: 1 1 auto;
            }

            .transaction-history-table th,
            .transaction-history-table td {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .transaction-history-table th.date-col,
            .transaction-history-table td.date-col {
                min-width: 140px;
                max-width: 160px;
            }

            .transaction-history-table td.reason-col {
                max-width: 160px;
            }

            .transaction-history-card {
                padding: 0.75rem;
            }
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4 inventory-header">
        <h1 class="h3"><?php echo htmlspecialchars($item['item_name']); ?></h1>
        <div>
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="transaction.php?id=<?php echo $id; ?>" class="btn btn-success">
                <i class="fas fa-exchange-alt"></i> New Transaction
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row inventory-cols">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Item Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th>Category</th><td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td></tr>
                        <tr><th>Quantity</th><td><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></td></tr>
                        <tr><th>Reorder Level</th><td><?php echo $item['reorder_level']; ?></td></tr>
                        <tr><th>Reorder Quantity</th><td><?php echo $item['reorder_quantity']; ?></td></tr>
                        <tr><th>Expiry Date</th><td><?php echo $item['expiry_date'] ? formatDate($item['expiry_date']) : '-'; ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Supplier Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tr><th>Supplier</th><td><?php echo htmlspecialchars($item['supplier_name'] ?? '-'); ?></td></tr>
                        <tr><th>Contact</th><td><?php echo htmlspecialchars($item['supplier_contact'] ?? '-'); ?></td></tr>
                        <tr><th>Cost per Unit</th><td><?php echo formatCurrency($item['cost_per_unit']); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card transaction-history-card">
                <div class="card-header">
                    <h5>Transaction History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($transactions)): ?>
                        <p class="text-muted">No transactions yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm transaction-history-table">
                                <thead>
                                    <tr>
                                        <th class="date-col">Date</th>
                                        <th>Type</th>
                                        <th>Change</th>
                                        <th>New Qty</th>
                                        <th class="reason-col">Reason</th>
                                        <th>User</th>
                                    </tr>
                                </thead>
                            <tbody>
                                <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td><?php echo formatDate($t['performed_at'], 'M d, H:i'); ?></td>
                                    <td><?php echo ucfirst($t['transaction_type']); ?></td>
                                    <td class="<?php echo $t['quantity_change'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo $t['quantity_change'] > 0 ? '+' : ''; ?><?php echo $t['quantity_change']; ?>
                                    </td>
                                    <td><?php echo $t['new_quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($t['reason'] ?? ''); ?></td>
                                    <td><?php echo $t['user_name'] ?? 'System'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>