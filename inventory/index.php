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

<style>
    .inventory-page-title {
        margin-bottom: 0.75rem;
    }

    .inventory-page-header {
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .summary-card {
        border: none;
        border-radius: 12px;
        color: #1a1a2e;
    }

    .summary-card.summary-expired {
        background: linear-gradient(135deg,rgb(255, 180, 180) 0%,rgb(247, 51, 29) 100%);
    }

    .summary-card.summary-expiring {
        background: linear-gradient(135deg,rgb(250, 236, 82) 0%,rgb(248, 253, 95) 100%);
    }

    .summary-card.summary-low {
        background: linear-gradient(135deg,rgb(140, 199, 241) 0%, #b8daff 100%);
        color: #1a1a2e;
    }

    .summary-card .card-title {
        font-size: 0.95rem;
        font-weight: 600;
        opacity: 0.9;
    }

    .summary-card h2 {
        font-size: 1.65rem;
        font-weight: 700;
        margin-bottom: 0;
    }

    .inventory-table-wrap .table {
        font-size: 0.875rem;
    }

    .inventory-table-wrap .table thead th {
        padding: 0.45rem 0.5rem;
        vertical-align: middle;
        white-space: nowrap;
    }

    .inventory-table-wrap .table tbody td {
        padding: 0.35rem 0.5rem;
        vertical-align: middle;
    }

    .inventory-table-wrap .table tbody tr {
        height: auto;
    }

    .badge-inv-expired {
        background-color:rgba(253, 52, 72, 0.89);
        color: #58151c;
    }

    .badge-inv-expiring {
        background-color:,rgb(250, 236, 82);
        color:rgb(10, 9, 8);
    }

    .badge-inv-out {
        background-color: #6c757d;
        color: #fff;
    }

    .badge-inv-low {
        background-color:rgb(140, 199, 241);
        color:rgb(7, 13, 23);
    }

    .badge-inv-ok {
        background-color:rgb(40, 167, 69);
        color:rgb(4, 5, 4);
    }

    .inventory-actions {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.35rem;
        max-width: 112px;
    }

    .inventory-actions .btn {
        padding: 0.2rem 0.35rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-add-inventory {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.55rem 1.25rem;
        font-weight: 600;
        border-radius: 10px;
        border: none;
        background: rgb(140, 199, 241 );
        color: black;
        box-shadow: 0 4px 14px rgba(52, 152, 219, 0.35);
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .btn-add-inventory:hover {
        color: #fff;
        transform: translateY(-1px);
        box-shadow: 0 6px 18px rgba(52, 152, 219, 0.45);
    }

    .btn-add-inventory i {
        font-size: 0.9em;
        opacity: 0.95;
    }

    #deleteInventoryModal .modal-dialog {
        max-width: 340px;
    }

    #deleteInventoryModal .modal-content {
        background: linear-gradient(180deg, rgba(52, 152, 219, 0.08) 0%, rgba(52, 152, 219, 0.04) 100%);
        border-radius: 16px;
        border: 1px solid rgba(52, 152, 219, 0.2);
        box-shadow: 0 12px 40px rgba(52, 152, 219, 0.2), 0 2px 8px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    #deleteInventoryModal .modal-header {
        border-bottom: 1px solid rgba(52, 152, 219, 0.18);
        background: rgba(255, 255, 255, 0.65);
        padding: 1rem 1.1rem;
    }

    #deleteInventoryModal .modal-title {
        font-size: 1.05rem;
        font-weight: 700;
        color: var(--primary-color);
    }

    #deleteInventoryModal .modal-body {
        padding: 1.15rem 1.25rem;
        color: #343a40;
        font-size: 0.95rem;
        line-height: 1.5;
    }

    #deleteInventoryModal .modal-body strong {
        color: var(--primary-color);
    }

    #deleteInventoryModal .modal-footer {
        padding: 0.85rem 1.1rem 1.1rem;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.5);
    }

    #deleteInventoryModal .btn-close {
        opacity: 0.55;
    }

    #deleteInventoryModal .btn-confirm-cancel {
        background-color: var(--primary-color);
        border-color: var(--primary-color);
        color: #fff;
        min-width: 88px;
        font-weight: 600;
        border-radius: 8px;
        padding: 0.45rem 0.85rem;
    }

    #deleteInventoryModal .btn-confirm-cancel:hover {
        background-color: #2980b9;
        border-color: #2980b9;
        color: #fff;
    }

    #deleteInventoryModal .btn-confirm-delete {
        background-color: #dc3545;
        border-color: #dc3545;
        color: #fff;
        min-width: 88px;
        font-weight: 600;
        border-radius: 8px;
        padding: 0.45rem 0.85rem;
    }

    #deleteInventoryModal .btn-confirm-delete:hover {
        background-color: #bb2d3b;
        border-color: #b02a37;
        color: #fff;
    }

    @media (max-width: 768px) {
        .inventory-page-title {
            font-size: 1.15rem;
            width: 100%;
        }

        .inventory-page-header .btn {
            width: 100%;
            padding: 0.55rem 0.85rem;
            font-size: 14px;
        }

        .inventory-page-header .btn-add-inventory {
            width: 50%;
            max-width: 280px;
            margin-left: auto;
            margin-right: auto;
            padding: 0.5rem 0.85rem;
        }

        .summary-card {
            border-radius: 12px;
        }

        .summary-card .card-body {
            padding: 1rem;
        }

        .summary-card .card-title {
            font-size: 0.85rem;
        }

        .summary-card h2 {
            font-size: 1.35rem;
        }

        .inventory-table-wrap .table {
            font-size: 13px;
        }

        .inventory-table-wrap .table thead th,
        .inventory-table-wrap .table tbody td {
            padding: 0.3rem 0.35rem;
        }

        .inventory-actions {
            max-width: 100%;
            gap: 0.3rem;
        }
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 inventory-page-header">
        <h1 class="h3 inventory-page-title">Inventory Management</h1>
        <a href="add.php" class="btn btn-add-inventory">
            <i class="fas fa-plus-circle"></i> Add New Item
        </a>
    </div>

    <!-- Alerts Summary -->
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card summary-card summary-expired">
                <div class="card-body">
                    <h5 class="card-title">Expired Items</h5>
                    <h2><?php
                        $expired = $db->fetchOne("SELECT COUNT(*) as count FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE()");
                        echo $expired['count'];
                    ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card summary-card summary-expiring">
                <div class="card-body">
                    <h5 class="card-title">Expiring Soon (7 days)</h5>
                    <h2><?php
                        $expiring = $db->fetchOne("SELECT COUNT(*) as count FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
                        echo $expiring['count'];
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
                        echo $lowstock['count'];
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
                            <th>Location</th>
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
                            <td><?php echo htmlspecialchars($item['location'] ?? '-'); ?></td>
                            <td>
                                <?php foreach ($statusItems as $statusLabel): ?>
                                    <span class="badge <?php echo $statusClasses[$statusLabel]; ?> me-1 mb-1"><?php echo $statusLabel; ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <div class="inventory-actions">
                                    <a href="view.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info" title="View" style="background-color: rgb(140, 199, 241);">
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
