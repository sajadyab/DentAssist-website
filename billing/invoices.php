<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();
$pageTitle = 'Invoices';

$db = Database::getInstance();

// Filters
$status = $_GET['status'] ?? '';
$patientId = $_GET['patient_id'] ?? '';

// Get patients for filter
$patients = repo_patient_list_for_select();

$where = ["1=1"];
$params = [];
$types = "";

if (!empty($status)) {
    $where[] = "payment_status = ?";
    $params[] = $status;
    $types .= "s";
}

if (!empty($patientId)) {
    $where[] = "patient_id = ?";
    $params[] = $patientId;
    $types .= "i";
}

$whereClause = implode(" AND ", $where);

$invoices = $db->fetchAll(
    "SELECT i.*, p.full_name as patient_name
     FROM invoices i
     JOIN patients p ON i.patient_id = p.id
     WHERE $whereClause
     ORDER BY i.invoice_date DESC",
    $params,
    $types
);

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Invoices</h1>
        <a href="create_invoice.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Invoice
        </a>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Payment Status</label>
                    <select class="form-select" name="status">
                        <option value="">All</option>
                        <option value="paid" <?php echo $status == 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="partial" <?php echo $status == 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="overdue" <?php echo $status == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Patient</label>
                    <select class="form-select" name="patient_id">
                        <option value="">All Patients</option>
                        <?php foreach ($patients as $patient): ?>
                            <option value="<?php echo $patient['id']; ?>" <?php echo $patientId == $patient['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($patient['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Invoices List -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($invoices)): ?>
                <p class="text-muted text-center py-4">No invoices found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Invoice #</th>
                                <th>Patient</th>
                                <th>Date</th>
                                <th>Due Date</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                                <?php
                                $totalAmount = (float) ($inv['total_amount'] ?? $inv['subtotal'] ?? 0);
                                $paidAmount = (float) ($inv['paid_amount'] ?? 0);
                                $balanceDue = (float) ($inv['balance_due'] ?? max(0, $totalAmount - $paidAmount));
                                ?>
                                <tr>
                                    <td><strong><?php echo $inv['invoice_number']; ?></strong></td>
                                    <td>
                                        <a href="../patients/view.php?id=<?php echo $inv['patient_id']; ?>">
                                            <?php echo htmlspecialchars($inv['patient_name']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo formatDate($inv['invoice_date']); ?></td>
                                    <td><?php echo formatDate($inv['due_date']); ?></td>
                                    <td><?php echo formatCurrency($totalAmount); ?></td>
                                    <td><?php echo formatCurrency($paidAmount); ?></td>
                                    <td><?php echo formatCurrency($balanceDue); ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'paid' => 'success',
                                            'partial' => 'warning',
                                            'pending' => 'secondary',
                                            'overdue' => 'danger',
                                            'cancelled' => 'dark'
                                        ];
                                        $color = $statusColors[$inv['payment_status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($inv['payment_status']); ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="invoice_view.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_invoice.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn btn-sm btn-success" onclick="recordPayment(<?php echo $inv['id']; ?>)">
                                                <i class="fas fa-dollar-sign"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="paymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="invoice_id" name="invoice_id">
                    <div class="mb-3">
                        <label class="form-label">Amount</label>
                        <input type="number" step="0.01" class="form-control" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-select" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="card">Card</option>
                            <option value="insurance">Insurance</option>
                            <option value="online">Online</option>
                            <option value="check">Check</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reference Number (optional)</label>
                        <input type="text" class="form-control" name="reference_number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePayment()">Record Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
function recordPayment(invoiceId) {
    document.getElementById('invoice_id').value = invoiceId;
    document.getElementById('paymentForm').reset();
    new bootstrap.Modal(document.getElementById('paymentModal')).show();
}

function savePayment() {
    const form = document.getElementById('paymentForm');
    const formData = new FormData(form);
    const data = Object.fromEntries(formData.entries());
    
    fetch('../api/record_payment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            location.reload();
        } else {
            alert('Error recording payment');
        }
    });
}
</script>

<?php include '../layouts/footer.php'; ?>
