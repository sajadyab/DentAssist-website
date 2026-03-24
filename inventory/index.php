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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Inventory Management</h1>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Item
        </a>
    </div>

    <!-- Alerts Summary -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-danger text-white">
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
            <div class="card bg-warning text-white">
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
            <div class="card bg-info text-white">
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
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
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
                                $statusClasses['Expired'] = 'bg-danger text-white';
                            }

                            if ($hasExpiry && $expiryDate > $today && $expiryDate <= date('Y-m-d', strtotime('+7 days'))) {
                                $statusItems[] = 'Expiring soon';
                                $statusClasses['Expiring soon'] = 'bg-warning';
                            }

                            if ($item['quantity'] == 0) {
                                $statusItems[] = 'Out of stock';
                                $statusClasses['Out of stock'] = 'bg-secondary text-white';
                            }

                            if ($item['quantity'] > 0 && $item['quantity'] <= $item['reorder_level']) {
                                $statusItems[] = 'Low stock';
                                $statusClasses['Low stock'] = 'bg-info';
                            }

                            if (empty($statusItems)) {
                                $statusItems[] = 'OK';
                                $statusClasses['OK'] = 'bg-success text-white';
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
                                    <span class="badge <?php echo $statusClasses[$statusLabel]; ?> me-1"><?php echo $statusLabel; ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <a href="view.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="transaction.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-success" title="Add Transaction">
                                    <i class="fas fa-exchange-alt"></i>
                                </a>
                                <a href="?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this item?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>