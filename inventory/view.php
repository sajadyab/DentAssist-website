<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$id = (int) ($_GET['id'] ?? 0);
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

<div class="container-fluid bills-page inventory-view-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-box me-2 opacity-90" aria-hidden="true"></i><?php echo htmlspecialchars((string) $item['item_name']); ?>
                </h2>
                <p class="mb-0 opacity-90">
                    <?php echo htmlspecialchars((string) ($item['category'] ?: 'Uncategorized')); ?>
                    · <?php echo (int) $item['quantity']; ?> <?php echo htmlspecialchars((string) ($item['unit'] ?? '')); ?> in stock
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex flex-column align-items-stretch align-items-lg-end justify-content-center treatment-plans-view-hero-actions-wrap">
                <div class="treatment-plans-view-hero-actions">
                    <div class="treatment-plans-view-hero-row treatment-plans-view-hero-row--primary">
                        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-secondary treatment-plans-view-hero-btn">
                            <i class="fas fa-edit" aria-hidden="true"></i> Edit
                        </a>
                        <a href="transaction.php?id=<?php echo $id; ?>" class="btn btn-secondary treatment-plans-view-hero-btn">
                            <i class="fas fa-exchange-alt" aria-hidden="true"></i> Transaction
                        </a>
                    </div>
                    <div class="treatment-plans-view-hero-row treatment-plans-view-hero-row--delete">
                        <a href="index.php" class="btn btn-secondary treatment-plans-view-hero-btn">
                            <i class="fas fa-arrow-left" aria-hidden="true"></i>
                            <span class="d-none d-sm-inline">Back to Inventory</span><span class="d-sm-none">Back</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 inventory-cols">
        <div class="col-12 col-lg-4 inventory-view-detail-col">
            <div class="card bills-dash-section-card mb-4 inventory-view-table">
                        <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                            <div class="bills-arrivals-section-header__inner align-items-center">
                                <div>
                                    <h5 class="card-title mb-0"><i class="fas fa-clipboard-list me-2" aria-hidden="true"></i>Item details</h5>
                                </div>
                            </div>
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

            <div class="card bills-dash-section-card inventory-view-table mb-0">
                        <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                            <div class="bills-arrivals-section-header__inner align-items-center">
                                <div>
                                    <h5 class="card-title mb-0"><i class="fas fa-truck me-2" aria-hidden="true"></i>Supplier information</h5>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr><th>Supplier</th><td><?php echo htmlspecialchars($item['supplier_name'] ?? '-'); ?></td></tr>
                                <tr><th>Contact</th><td><?php echo htmlspecialchars($item['supplier_contact'] ?? '-'); ?></td></tr>
                                <tr><th>Cost per Unit</th><td><?php echo formatCurrency($item['cost_per_unit']); ?></td></tr>
                                <tr><th>Selling price</th><td><?php echo ($item['selling_price'] !== null && $item['selling_price'] !== '') ? formatCurrency((float) $item['selling_price']) : '-'; ?></td></tr>
                            </table>
                        </div>
            </div>
        </div>

        <div class="col-12 col-lg-8 inventory-view-history-col">
            <div class="card bills-dash-section-card transaction-history-card">
                        <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                            <div class="bills-arrivals-section-header__inner align-items-center">
                                <div>
                                    <h5 class="card-title mb-0"><i class="fas fa-history me-2" aria-hidden="true"></i>Transaction history</h5>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($transactions)): ?>
                                <p class="text-muted mb-0">No transactions yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm transaction-history-table mb-0">
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
                                                    <td><?php echo ucfirst((string) $t['transaction_type']); ?></td>
                                                    <td class="<?php echo $t['quantity_change'] > 0 ? 'tx-change-pos' : ($t['quantity_change'] < 0 ? 'tx-change-neg' : ''); ?>">
                                                        <?php echo $t['quantity_change'] > 0 ? '+' : ''; ?><?php echo $t['quantity_change']; ?>
                                                    </td>
                                                    <td><?php echo $t['new_quantity']; ?></td>
                                                    <td><?php echo htmlspecialchars((string) ($t['reason'] ?? '')); ?></td>
                                                    <td><?php echo htmlspecialchars((string) ($t['user_name'] ?? 'System')); ?></td>
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
