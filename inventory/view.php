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
        .inventory-header-btns {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .inventory-view-table .table th,
        .inventory-view-table .table td {
            padding: 0.35rem 0.5rem;
            vertical-align: middle;
        }

        .transaction-history-table th,
        .transaction-history-table td {
            padding: 0.3rem 0.45rem;
            vertical-align: middle;
        }

        .tx-change-pos {
            color: #2d9d6b;
            font-weight: 600;
        }

        .tx-change-neg {
            color: #e07070;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .inventory-view-title {
                font-size: 1.1rem;
                margin-bottom: 0.75rem;
            }

            .inventory-header {
                flex-direction: column;
                align-items: stretch;
                gap: 0.75rem;
            }

            .inventory-header-btns {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 0.45rem;
                justify-content: stretch;
            }

            .inventory-header-btns .btn {
                width: 100%;
                padding: 0.5rem 0.6rem;
                font-size: 14px;
                white-space: nowrap;
            }

            .inventory-header-btns .btn:nth-child(3) {
                grid-column: 1 / -1;
            }

            .inventory-header-btns .btn-back-mobile {
                background-color: var(--primary-color);
                border-color: var(--primary-color);
                color: #fff;
            }

            .inventory-header-btns .btn-back-mobile:hover {
                background-color: #2980b9;
                border-color: #2980b9;
                color: #fff;
            }

            .inventory-cols .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .inventory-header > h1 {
                flex: 1 1 100%;
                margin-bottom: 0;
            }

            .transaction-history-table th,
            .transaction-history-table td {
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .transaction-history-table th.date-col,
            .transaction-history-table td.date-col {
                min-width: 120px;
                max-width: 140px;
            }

            .transaction-history-table td.reason-col {
                max-width: 120px;
            }

            .transaction-history-card .card-body {
                padding: 0.85rem;
            }

            .inventory-view-table .card-body,
            .transaction-history-card .card-body {
                padding: 0.85rem;
            }
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4 inventory-header flex-wrap gap-2">
        <h1 class="h3 inventory-view-title mb-0"><?php echo htmlspecialchars($item['item_name']); ?></h1>
        <div class="inventory-header-btns">
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="transaction.php?id=<?php echo $id; ?>" class="btn btn-success">
                <i class="fas fa-exchange-alt"></i> Transaction
            </a>
            <a href="index.php" class="btn btn-secondary btn-back-mobile">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <div class="row inventory-cols">
        <div class="col-md-6">
            <div class="card mb-4 inventory-view-table">
                <div class="card-header">
                    <h5>Item Details</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th>Category</th><td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td></tr>
                        <tr><th>Quantity</th><td><?php echo $item['quantity']; ?> <?php echo $item['unit']; ?></td></tr>
                        <tr><th>Reorder Level</th><td><?php echo $item['reorder_level']; ?></td></tr>
                        <tr><th>Reorder Quantity</th><td><?php echo $item['reorder_quantity']; ?></td></tr>
                        <tr><th>Expiry Date</th><td><?php echo $item['expiry_date'] ? formatDate($item['expiry_date']) : '-'; ?></td></tr>
                    </table>
                </div>
            </div>

            <div class="card inventory-view-table">
                <div class="card-header">
                    <h5>Supplier Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
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
                                    <td class="<?php echo $t['quantity_change'] > 0 ? 'tx-change-pos' : ($t['quantity_change'] < 0 ? 'tx-change-neg' : ''); ?>">
                                        <?php echo $t['quantity_change'] > 0 ? '+' : ''; ?><?php echo $t['quantity_change']; ?>
                                    </td>
                                    <td><?php echo $t['new_quantity']; ?></td>
                                    <td><?php echo htmlspecialchars($t['reason'] ?? ''); ?></td>
                                    <td><?php echo $t['user_name'] ?? 'System'; ?></td>
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

<?php include '../layouts/footer.php'; ?>