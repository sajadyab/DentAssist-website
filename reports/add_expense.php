<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'staff') {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$userId = Auth::userId();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $expenseType = $_POST['expense_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $expenseDate = $_POST['expense_date'] ?? '';
    $vendorSupplier = trim($_POST['vendor_supplier'] ?? '');
    $invoiceNumber = trim($_POST['invoice_number'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? 'cash';
    $paymentStatus = $_POST['payment_status'] ?? 'paid';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($expenseType) || empty($description) || $amount <= 0 || empty($expenseDate)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            $hasExpenseSync = dbColumnExists('expenses', 'sync_status');
            $columns = ['expense_type', 'description', 'amount', 'expense_date', 'vendor_supplier', 'invoice_number', 'payment_method', 'payment_status', 'notes', 'created_by'];
            $values = [$expenseType, $description, $amount, $expenseDate, $vendorSupplier, $invoiceNumber, $paymentMethod, $paymentStatus, $notes, $userId];
            $types = 'ssdssssssi';
            if ($hasExpenseSync) {
                $columns[] = 'sync_status';
                $values[] = 'pending';
                $types .= 's';
            }
            $newExpenseId = (int) $db->insert(
                'INSERT INTO expenses (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                $values,
                $types
            );
            if ($newExpenseId > 0 && $hasExpenseSync) {
                sync_push_row_now('expenses', $newExpenseId);
            }
            $success = 'Expense recorded successfully.';
        } catch (Exception $e) {
            $error = 'Error recording expense: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add Expense';
include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Add New Expense</h1>
        <a href="financial.php" class="btn btn-secondary">Back to Financial Dashboard</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Expense Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expense_type" class="form-label">Expense Type *</label>
                                    <select class="form-select" id="expense_type" name="expense_type" required>
                                        <option value="">Select type</option>
                                        <option value="stock">Stock/Materials</option>
                                        <option value="salary">Salary</option>
                                        <option value="utilities">Utilities</option>
                                        <option value="rent">Rent</option>
                                        <option value="equipment">Equipment</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Amount *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description *</label>
                            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="expense_date" class="form-label">Expense Date *</label>
                                    <input type="date" class="form-control" id="expense_date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="vendor_supplier" class="form-label">Vendor/Supplier</label>
                                    <input type="text" class="form-control" id="vendor_supplier" name="vendor_supplier">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="invoice_number" class="form-label">Invoice/Receipt Number</label>
                                    <input type="text" class="form-control" id="invoice_number" name="invoice_number">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="payment_method" class="form-label">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method">
                                        <option value="cash">Cash</option>
                                        <option value="card">Credit/Debit Card</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="check">Check</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="payment_status">
                                <option value="paid">Paid</option>
                                <option value="pending">Pending</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save Expense</button>
                            <a href="financial.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Stats</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get current month expenses
                    $currentMonth = date('Y-m-01');
                    $nextMonth = date('Y-m-01', strtotime('+1 month'));

                    $monthExpenses = $db->fetchOne(
                        "SELECT SUM(amount) as total FROM expenses WHERE payment_status = 'paid' AND expense_date >= ? AND expense_date < ?",
                        [$currentMonth, $nextMonth]
                    )['total'] ?? 0;

                    $pendingExpenses = $db->fetchOne(
                        "SELECT SUM(amount) as total FROM expenses WHERE payment_status = 'pending'",
                        []
                    )['total'] ?? 0;
                    ?>

                    <div class="mb-3">
                        <strong>This Month's Expenses:</strong><br>
                        <span class="h4 text-danger">$<?php echo number_format($monthExpenses, 2); ?></span>
                    </div>

                    <div class="mb-3">
                        <strong>Pending Payments:</strong><br>
                        <span class="h4 text-warning">$<?php echo number_format($pendingExpenses, 2); ?></span>
                    </div>

                    <hr>

                    <h6>Expense Categories (This Month)</h6>
                    <?php
                    $categoryExpenses = $db->fetchAll(
                        "SELECT expense_type, SUM(amount) as total FROM expenses WHERE payment_status = 'paid' AND expense_date >= ? AND expense_date < ? GROUP BY expense_type ORDER BY total DESC",
                        [$currentMonth, $nextMonth]
                    );

                    if (!empty($categoryExpenses)):
                        foreach ($categoryExpenses as $cat):
                    ?>
                        <div class="d-flex justify-content-between">
                            <span><?php echo ucfirst($cat['expense_type']); ?>:</span>
                            <span>$<?php echo number_format($cat['total'], 2); ?></span>
                        </div>
                    <?php
                        endforeach;
                    else:
                        echo '<p class="text-muted">No expenses this month.</p>';
                    endif;
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
