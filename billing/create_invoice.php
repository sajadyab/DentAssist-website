<?php
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireLogin();
$pageTitle = 'Create Invoice';

$db = Database::getInstance();

$appointmentId = $_GET['appointment_id'] ?? 0;
$patientId = $_GET['patient_id'] ?? 0;

// If appointment_id is given, fetch patient from that
if ($appointmentId) {
    $apt = $db->fetchOne(
        "SELECT patient_id FROM appointments WHERE id = ?",
        [$appointmentId],
        "i"
    );
    if ($apt) {
        $patientId = $apt['patient_id'];
    }
}

$patients = PatientRepository::listForSelect();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Calculate totals
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $discountType = $_POST['discount_type'] ?? 'fixed';
    $discountValue = floatval($_POST['discount_value'] ?? 0);
    $taxRate = floatval($_POST['tax_rate'] ?? 0);
    
    // Generate invoice number
    $invoiceNumber = generateInvoiceNumber();
    
    $invoiceId = $db->insert(
        "INSERT INTO invoices (
            invoice_number, patient_id, appointment_id, invoice_date, due_date,
            subtotal, discount_type, discount_value, tax_rate, notes,
            payment_status, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
        [
            $invoiceNumber,
            $_POST['patient_id'],
            $_POST['appointment_id'] ?: null,
            $_POST['invoice_date'],
            $_POST['due_date'],
            $subtotal,
            $discountType,
            $discountValue,
            $taxRate,
            $_POST['notes'] ?? null,
            Auth::userId()
        ],
        "siissdssdsi"
    );
    
    if ($invoiceId) {
        logAction('CREATE', 'invoices', $invoiceId, null, $_POST);
        $success = 'Invoice created successfully';
        // Redirect to view
        header("Location: invoice_view.php?id=$invoiceId");
        exit;
    } else {
        $error = 'Error creating invoice';
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Create New Invoice</h1>
        <a href="invoices.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
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
                                <option value="<?php echo $p['id']; ?>" <?php echo $patientId == $p['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Appointment (optional)</label>
                        <select class="form-select" name="appointment_id">
                            <option value="">None</option>
                            <?php
                            if ($patientId) {
                                $appointments = $db->fetchAll(
                                    "SELECT id, appointment_date, treatment_type FROM appointments WHERE patient_id = ? ORDER BY appointment_date DESC",
                                    [$patientId],
                                    "i"
                                );
                                foreach ($appointments as $a):
                                ?>
                                    <option value="<?php echo $a['id']; ?>" <?php echo $appointmentId == $a['id'] ? 'selected' : ''; ?>>
                                        <?php echo formatDate($a['appointment_date']); ?> - <?php echo $a['treatment_type']; ?>
                                    </option>
                                <?php endforeach;
                            } ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Invoice Date *</label>
                        <input type="date" class="form-control" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Due Date *</label>
                        <input type="date" class="form-control" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Subtotal ($)</label>
                        <input type="number" step="0.01" class="form-control" name="subtotal" id="subtotal" value="0" onchange="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Discount Type</label>
                        <select class="form-select" name="discount_type" id="discount_type" onchange="calculateTotal()">
                            <option value="fixed">Fixed ($)</option>
                            <option value="percentage">Percentage (%)</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Discount Value</label>
                        <input type="number" step="0.01" class="form-control" name="discount_value" id="discount_value" value="0" onchange="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" step="0.01" class="form-control" name="tax_rate" id="tax_rate" value="0" onchange="calculateTotal()">
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Total Amount</label>
                        <input type="text" class="form-control" id="total" readonly value="$0.00">
                    </div>

                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">Create Invoice</button>
                    <a href="invoices.php" class="btn btn-secondary">Cancel</a>
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
