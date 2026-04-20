<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();

$db = Database::getInstance();
$invoiceId = (int) ($_GET['id'] ?? 0);

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

$patients = repo_patient_list_for_select();

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
        sync_push_row_now('invoices', $invoiceId);
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

<div class="container-fluid bills-page billing-edit-invoice-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-file-invoice-dollar me-2 opacity-90" aria-hidden="true"></i>Edit invoice
                    #<?php echo htmlspecialchars((string) $invoice['invoice_number']); ?>
                </h2>
                <p class="mb-0 opacity-90">
                    <?php echo htmlspecialchars((string) $invoice['patient_name']); ?>
                    · update dates, amounts, and notes — the total preview refreshes as you change fields.
                </p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex flex-column align-items-stretch align-items-lg-end justify-content-center billing-invoice-view-hero-actions-wrap">
                <div class="billing-invoice-view-hero-actions" role="group" aria-label="Invoice navigation">
                    <a href="invoices.php" class="btn billing-invoice-view-hero-btn">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                        <span class="d-none d-sm-inline">Back to Invoices</span><span class="d-sm-none">Back</span>
                    </a>
                    <a href="invoice_view.php?id=<?php echo $invoiceId; ?>" class="btn billing-invoice-view-hero-btn">
                        <i class="fas fa-eye" aria-hidden="true"></i> View
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12">
            <div class="card bills-dash-section-card billing-create-invoice-form-card queue-registration-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-file-invoice me-2" aria-hidden="true"></i>Invoice details</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invEditPatient">Patient *</label>
                                <select class="form-select form-control-modern" id="invEditPatient" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?php echo (int) $p['id']; ?>" <?php echo (int) $invoice['patient_id'] === (int) $p['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invEditInvDate">Invoice Date *</label>
                                <input type="date" class="form-control form-control-modern" id="invEditInvDate" name="invoice_date"
                                       value="<?php echo htmlspecialchars((string) $invoice['invoice_date']); ?>" required>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invEditDue">Due Date *</label>
                                <input type="date" class="form-control form-control-modern" id="invEditDue" name="due_date"
                                       value="<?php echo htmlspecialchars((string) $invoice['due_date']); ?>" required>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="subtotal">Subtotal ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" name="subtotal" id="subtotal"
                                       value="<?php echo htmlspecialchars((string) $invoice['subtotal']); ?>" onchange="calculateTotal()">
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="discount_type">Discount Type</label>
                                <select class="form-select form-control-modern" name="discount_type" id="discount_type" onchange="calculateTotal()">
                                    <option value="fixed" <?php echo ($invoice['discount_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed ($)</option>
                                    <option value="percentage" <?php echo ($invoice['discount_type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage (%)</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="discount_value">Discount Value</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" name="discount_value" id="discount_value"
                                       value="<?php echo htmlspecialchars((string) ($invoice['discount_value'] ?? '0')); ?>" onchange="calculateTotal()">
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="tax_rate">Tax Rate (%)</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" name="tax_rate" id="tax_rate"
                                       value="<?php echo htmlspecialchars((string) ($invoice['tax_rate'] ?? '0')); ?>" onchange="calculateTotal()">
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="total">Total Amount</label>
                                <input type="text" class="form-control form-control-modern" id="total" readonly
                                       value="<?php echo htmlspecialchars(formatCurrency((float) ($invoice['total_amount'] ?? 0))); ?>">
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label" for="invEditNotes">Notes</label>
                                <textarea class="form-control form-control-modern" name="notes" id="invEditNotes" rows="3"><?php echo htmlspecialchars((string) ($invoice['notes'] ?? '')); ?></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-lg-end gap-2 flex-wrap billing-create-invoice-form-actions">
                            <button type="submit" class="btn-green">Update Invoice</button>
                            <a href="invoice_view.php?id=<?php echo $invoiceId; ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
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
