<?php
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireLogin();

$db = Database::getInstance();
$invoiceId = $_GET['id'] ?? 0;

$invoice = $db->fetchOne(
    "SELECT i.*, p.full_name as patient_name
     FROM invoices i
     JOIN patients p ON i.patient_id = p.id
     WHERE i.id = ?",
    [$invoiceId],
    "i"
);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

$pageTitle = 'Edit Invoice';

$patients = PatientRepository::listForSelect();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Calculate totals
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $discountType = $_POST['discount_type'] ?? 'fixed';
    $discountValue = floatval($_POST['discount_value'] ?? 0);
    $taxRate = floatval($_POST['tax_rate'] ?? 0);

    $result = $db->execute(
        "UPDATE invoices SET
            patient_id = ?, invoice_date = ?, due_date = ?,
            subtotal = ?, discount_type = ?, discount_value = ?, tax_rate = ?,
            notes = ?
         WHERE id = ?",
        [
            $_POST['patient_id'],
            $_POST['invoice_date'],
            $_POST['due_date'],
            $subtotal,
            $discountType,
            $discountValue,
            $taxRate,
            $_POST['notes'] ?? null,
            $invoiceId
        ],
        "issdssdsi"
    );

    if ($result !== false) {
        logAction('UPDATE', 'invoices', $invoiceId, $invoice, $_POST);
        $success = 'Invoice updated successfully';
        // Refresh invoice
        $invoice = $db->fetchOne(
            "SELECT i.*, p.full_name as patient_name FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE i.id = ?",
            [$invoiceId],
            "i"
        );
    } else {
        $error = 'Error updating invoice';
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Edit Invoice #<?php echo $invoice['invoice_number']; ?></h1>
        <div>
            <a href="invoice_view.php?id=<?php echo $invoiceId; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="invoices.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient *</label>
                        <select class="form-select" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $invoice['patient_id'] == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Invoice Date *</label>
                        <input type="date" class="form-control" name="invoice_date" 
                               value="<?php echo $invoice['invoice_date']; ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Due Date *</label>
                        <input type="date" class="form-control" name="due_date" 
                               value="<?php echo $invoice['due_date']; ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subtotal ($)</label>
                        <input type="number" step="0.01" class="form-control" name="subtotal" id="subtotal" 
                               value="<?php echo $invoice['subtotal']; ?>" onchange="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Discount Type</label>
                        <select class="form-select" name="discount_type" id="discount_type" onchange="calculateTotal()">
                            <option value="fixed" <?php echo $invoice['discount_type'] == 'fixed' ? 'selected' : ''; ?>>Fixed ($)</option>
                            <option value="percentage" <?php echo $invoice['discount_type'] == 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Discount Value</label>
                        <input type="number" step="0.01" class="form-control" name="discount_value" id="discount_value" 
                               value="<?php echo $invoice['discount_value']; ?>" onchange="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="tax_rate" id="tax_rate" 
                               value="<?php echo $invoice['tax_rate']; ?>" onchange="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="text" class="form-control" id="total" readonly value="<?php echo formatCurrency($invoice['total_amount']); ?>">
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($invoice['notes']); ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">Update Invoice</button>
                    <a href="invoice_view.php?id=<?php echo $invoiceId; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calculateTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const discountType = document.getElementById('discount_type').value;
    const discountValue = parseFloat(document.getElementById('discount_value').value) || 0;
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;

    let discountAmount = 0;
    if (discountType === 'percentage') {
        discountAmount = subtotal * discountValue / 100;
    } else {
        discountAmount = discountValue;
    }

    const afterDiscount = subtotal - discountAmount;
    const taxAmount = afterDiscount * taxRate / 100;
    const total = afterDiscount + taxAmount;

    document.getElementById('total').value = '$' + total.toFixed(2);
}
</script>

<?php include '../layouts/footer.php'; ?><?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$invoiceId = $_GET['id'] ?? 0;

$invoice = $db->fetchOne(
    "SELECT i.*, p.full_name as patient_name
     FROM invoices i
     JOIN patients p ON i.patient_id = p.id
     WHERE i.id = ?",
    [$invoiceId],
    "i"
);

if (!$invoice) {
    header('Location: invoices.php');
    exit;
}

$pageTitle = 'Edit Invoice';

$patients = PatientRepository::listForSelect();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Calculate totals
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $discountType = $_POST['discount_type'] ?? 'fixed';
    $discountValue = floatval($_POST['discount_value'] ?? 0);
    $taxRate = floatval($_POST['tax_rate'] ?? 0);

    $result = $db->execute(
        "UPDATE invoices SET
            patient_id = ?, invoice_date = ?, due_date = ?,
            subtotal = ?, discount_type = ?, discount_value = ?, tax_rate = ?,
            notes = ?
         WHERE id = ?",
        [
            $_POST['patient_id'],
            $_POST['invoice_date'],
            $_POST['due_date'],
            $subtotal,
            $discountType,
            $discountValue,
            $taxRate,
            $_POST['notes'] ?? null,
            $invoiceId
        ],
        "issdssdsi"
    );

    if ($result !== false) {
        logAction('UPDATE', 'invoices', $invoiceId, $invoice, $_POST);
        $success = 'Invoice updated successfully';
        // Refresh invoice
        $invoice = $db->fetchOne(
            "SELECT i.*, p.full_name as patient_name FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE i.id = ?",
            [$invoiceId],
            "i"
        );
    } else {
        $error = 'Error updating invoice';
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Edit Invoice #<?php echo $invoice['invoice_number']; ?></h1>
        <div>
            <a href="invoice_view.php?id=<?php echo $invoiceId; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="invoices.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Patient *</label>
                        <select class="form-select" name="patient_id" required>
                            <option value="">Select Patient</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo $invoice['patient_id'] == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Invoice Date *</label>
                        <input type="date" class="form-control" name="invoice_date" 
                               value="<?php echo $invoice['invoice_date']; ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Due Date *</label>
                        <input type="date" class="form-control" name="due_date" 
                               value="<?php echo $invoice['due_date']; ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Subtotal ($)</label>
                        <input type="number" step="0.01" class="form-control" name="subtotal" id="subtotal" 
                               value="<?php echo $invoice['subtotal']; ?>" onchange="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Discount Type</label>
                        <select class="form-select" name="discount_type" id="discount_type" onchange="calculateTotal()">
                            <option value="fixed" <?php echo $invoice['discount_type'] == 'fixed' ? 'selected' : ''; ?>>Fixed ($)</option>
                            <option value="percentage" <?php echo $invoice['discount_type'] == 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Discount Value</label>
                        <input type="number" step="0.01" class="form-control" name="discount_value" id="discount_value" 
                               value="<?php echo $invoice['discount_value']; ?>" onchange="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="tax_rate" id="tax_rate" 
                               value="<?php echo $invoice['tax_rate']; ?>" onchange="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="text" class="form-control" id="total" readonly value="<?php echo formatCurrency($invoice['total_amount']); ?>">
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars($invoice['notes']); ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">Update Invoice</button>
                    <a href="invoice_view.php?id=<?php echo $invoiceId; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function calculateTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal').value) || 0;
    const discountType = document.getElementById('discount_type').value;
    const discountValue = parseFloat(document.getElementById('discount_value').value) || 0;
    const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;

    let discountAmount = 0;
    if (discountType === 'percentage') {
        discountAmount = subtotal * discountValue / 100;
    } else {
        discountAmount = discountValue;
    }

    const afterDiscount = subtotal - discountAmount;
    const taxAmount = afterDiscount * taxRate / 100;
    const total = afterDiscount + taxAmount;

    document.getElementById('total').value = '$' + total.toFixed(2);
}
</script>

<?php include '../layouts/footer.php'; ?>
