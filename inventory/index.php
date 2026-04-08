<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = 'Inventory Management';

$db = Database::getInstance();

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $db->execute("DELETE FROM inventory WHERE id = ?", [$id], "i");
    header('Location: index.php');
    exit;
}

// Get all inventory items
$items = $db->fetchAll(
    "SELECT * FROM inventory ORDER BY 
        CASE 
            WHEN expiry_date IS NOT NULL AND expiry_date < CURDATE() THEN 1
            WHEN quantity <= reorder_level THEN 2
            ELSE 3
        END, item_name"
);

include '../layouts/header.php';
?>


<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 inventory-page-header">
        <h1 class="h3 inventory-page-title">Inventory Management</h1>
        <a href="add.php" class="btn btn-add-inventory">
            <i class="fas fa-plus-circle"></i> Add New Item
        </a>
    </div>

    <!-- Alerts summary: Expired | Expiring Soon | Low Stock (shared card system) -->
    <div class="row mb-4 g-3 inv-summary-cards">
        <div class="col-md-4">
            <div class="card summary-card summary-expired">
                <div class="card-body">
                    <h5 class="card-title">Expired Items</h5>
                    <h2><?php
                        $expired = $db->fetchOne("SELECT COUNT(*) as count FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date <> '0000-00-00' AND expiry_date < CURDATE()");
                        echo (int) ($expired['count'] ?? 0);
                    ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card summary-card summary-expiring">
                <div class="card-body">
                    <h5 class="card-title">Expiring Soon (7 days)</h5>
                    <h2><?php
                        $expiring = $db->fetchOne("SELECT COUNT(*) as count FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date <> '0000-00-00' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
                        echo (int) ($expiring['count'] ?? 0);
                    ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card summary-card summary-low">
                <div class="card-body">
                    <h5 class="card-title">Low Stock</h5>
                    <h2><?php
                        $lowstock = $db->fetchOne("SELECT COUNT(*) as count FROM inventory WHERE quantity <= reorder_level AND quantity > 0");
                        echo (int) ($lowstock['count'] ?? 0);
                    ?></h2>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Table -->
    <div class="card">
        <div class="card-body inventory-table-wrap">
            <div class="table-responsive">
                <table class="table table-hover inventory-list-table mb-0">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Quantity</th>
                            <th>Unit</th>
                            <th>Reorder Level</th>
                            <th>Expiry Date</th>
                            <th>Supplier</th>
                            <th>Sell price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item):
                            $today = date('Y-m-d');
                            $expiryDate = $item['expiry_date'];
                            $hasExpiry = !empty($expiryDate) && $expiryDate !== '0000-00-00' && strtotime($expiryDate) !== false;

                            $statusItems = [];
                            $statusClasses = [];

                            if ($hasExpiry && $expiryDate < $today) {
                                $statusItems[] = 'Expired';
                                $statusClasses['Expired'] = 'badge-inv-expired';
                            }

                            if ($hasExpiry && $expiryDate > $today && $expiryDate <= date('Y-m-d', strtotime('+7 days'))) {
                                $statusItems[] = 'Expiring soon';
                                $statusClasses['Expiring soon'] = 'badge-inv-expiring';
                            }

                            if ($item['quantity'] == 0) {
                                $statusItems[] = 'Out of stock';
                                $statusClasses['Out of stock'] = 'badge-inv-out';
                            }

                            if ($item['quantity'] > 0 && $item['quantity'] <= $item['reorder_level']) {
                                $statusItems[] = 'Low stock';
                                $statusClasses['Low stock'] = 'badge-inv-low';
                            }

                            if (empty($statusItems)) {
                                $statusItems[] = 'OK';
                                $statusClasses['OK'] = 'badge-inv-ok';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($item['unit'] ?? '-'); ?></td>
                            <td><?php echo $item['reorder_level']; ?></td>
                            <td><?php
                                $expiry = $item['expiry_date'];
                                $validExpiry = (!empty($expiry) && $expiry !== '0000-00-00' && strtotime($expiry) !== false);
                                if ($validExpiry) {
                                    echo date("M d, Y", strtotime($expiry));
                                } else {
                                    echo "-";
                                }
                            ?></td>
                            <td><?php echo htmlspecialchars($item['supplier_name'] ?? '-'); ?></td>
                            <td><?php echo isset($item['selling_price']) && $item['selling_price'] !== null && $item['selling_price'] !== '' ? formatCurrency((float) $item['selling_price']) : '-'; ?></td>
                            <td>
                                <?php foreach ($statusItems as $statusLabel): ?>
                                    <span class="badge <?php echo $statusClasses[$statusLabel]; ?> me-1 mb-1"><?php echo $statusLabel; ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <div class="inventory-actions">
                                    <a href="view.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info inventory-btn-view-soft" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="transaction.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-success" title="Add Transaction">
                                        <i class="fas fa-exchange-alt"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger btn-delete-inventory" title="Delete"
                                       data-id="<?php echo (int) $item['id']; ?>"
                                       data-name="<?php echo htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteInventoryModal" tabindex="-1" aria-labelledby="deleteInventoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteInventoryModalLabel">Remove item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Delete <strong id="deleteInventoryItemName"></strong> from inventory? This cannot be undone.</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-confirm-cancel" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-confirm-delete" id="confirmDeleteInventory">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php
$additionalScripts = <<<'INV_DELETE_JS'
<script>
(function () {
    function initInventoryDeleteModal() {
        var modalEl = document.getElementById('deleteInventoryModal');
        if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
        var deleteModal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var deleteId = null;
        var nameEl = document.getElementById('deleteInventoryItemName');
        document.querySelectorAll('.btn-delete-inventory').forEach(function (btn) {
            btn.addEventListener('click', function () {
                deleteId = this.getAttribute('data-id');
                if (nameEl) nameEl.textContent = this.getAttribute('data-name') || '';
                deleteModal.show();
            });
        });
        var confirmBtn = document.getElementById('confirmDeleteInventory');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (deleteId) {
                    window.location.href = 'index.php?delete=' + encodeURIComponent(deleteId);
                }
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInventoryDeleteModal);
    } else {
        initInventoryDeleteModal();
    }
})();
</script>
INV_DELETE_JS;
include '../layouts/footer.php';
?>
