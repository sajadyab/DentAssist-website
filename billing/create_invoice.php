<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();
if (
    !Auth::isAdmin()
    && !hasPermission((int) Auth::userId(), 'manage_billing')
    && !Auth::hasRole('doctor')
    && !Auth::hasRole('assistant')
) {
    http_response_code(403);
    exit('Access denied.');
}
$pageTitle = 'Create Invoice';

$db = Database::getInstance();
$paymentMethodOptions = [
    'cash' => 'Cash',
    'card' => 'Card',
    'insurance' => 'Insurance',
    'online' => 'Online',
    'check' => 'Check',
];

$appointmentId = $_GET['appointment_id'] ?? 0;
$patientId = $_GET['patient_id'] ?? 0;
$selectedTreatmentId = (int) ($_POST['treatment_id'] ?? 0);
$subtotal = floatval($_POST['subtotal'] ?? 0);

// If appointment_id is given, fetch patient from that
if ($appointmentId) {
    $apt = $db->fetchOne(
        "SELECT patient_id, treatment_type FROM appointments WHERE id = ?",
        [$appointmentId],
        "i"
    );
    if ($apt) {
        $patientId = $apt['patient_id'];
        if ($selectedTreatmentId === 0 && trim((string) ($apt['treatment_type'] ?? '')) !== '') {
            $treatmentMatch = $db->fetchOne(
                "SELECT id, cost FROM treatments WHERE name = ? LIMIT 1",
                [trim((string) $apt['treatment_type'])],
                "s"
            );
            if ($treatmentMatch) {
                $selectedTreatmentId = (int) $treatmentMatch['id'];
                $subtotal = floatval($treatmentMatch['cost']);
            }
        }
    }
}

$patients = repo_patient_list_for_select();
$treatments = [];
if (dbTableExists('treatments')) {
    $treatments = $db->fetchAll('SELECT id, name, cost FROM treatments ORDER BY name', [], '');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Calculate totals
    $selectedTreatmentId = (int) ($_POST['treatment_id'] ?? 0);
    if ($selectedTreatmentId > 0) {
        $selectedTreatment = $db->fetchOne(
            "SELECT id, name, cost FROM treatments WHERE id = ?",
            [$selectedTreatmentId],
            "i"
        );
        if ($selectedTreatment) {
            $subtotal = floatval($selectedTreatment['cost']);
        }
    }

    $subtotal = floatval($subtotal);
    $discountType = $_POST['discount_type'] ?? 'fixed';
    $discountValue = floatval($_POST['discount_value'] ?? 0);
    $taxRate = floatval($_POST['tax_rate'] ?? 0);
    $paymentMethod = trim((string) ($_POST['payment_method'] ?? ''));

    if ($paymentMethod !== '' && !array_key_exists($paymentMethod, $paymentMethodOptions)) {
        $error = 'Invalid payment method selected.';
    }

    // Generate invoice number
    if ($error === '') {
        $invoiceNumber = generateInvoiceNumber();

        $invoiceId = $db->insert(
            "INSERT INTO invoices (
                invoice_number, patient_id, appointment_id, invoice_date, due_date,
                subtotal, discount_type, discount_value, tax_rate, payment_method, notes,
                payment_status, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
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
                $paymentMethod !== '' ? $paymentMethod : null,
                $_POST['notes'] ?? null,
                Auth::userId()
            ],
            "siissdssdssi"
        );

        if ($invoiceId) {
            logAction('CREATE', 'invoices', $invoiceId, null, $_POST);
            sync_push_row_now('invoices', $invoiceId);
            $success = 'Invoice created successfully';
            // Redirect to view
            header("Location: invoice_view.php?id=$invoiceId");
            exit;
        } else {
            $error = 'Error creating invoice';
        }
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid bills-page billing-create-invoice-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-file-invoice-dollar me-2 opacity-90" aria-hidden="true"></i>Create invoice
                </h2>
                <p class="mb-0 opacity-90">Choose patient and dates, set subtotal, discount, and tax — the total updates as you go.</p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 appointments-add-top-actions">
                <a href="invoices.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Invoices</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
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
                                <label class="form-label" for="invCreatePatient">Patient *</label>
                                <select class="form-select form-control-modern" id="invCreatePatient" name="patient_id" required>
                                    <option value="">Select Patient</option>
                                    <?php foreach ($patients as $p): ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo $patientId == $p['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invCreateAppt">Appointment (optional)</label>
                                <select class="form-select form-control-modern" id="invCreateAppt" name="appointment_id">
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
                                                <?php echo formatDate($a['appointment_date']); ?> - <?php echo htmlspecialchars((string) $a['treatment_type']); ?>
                                            </option>
                                        <?php endforeach;
                                    } ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invCreateInvDate">Invoice Date *</label>
                                <input type="date" class="form-control form-control-modern" id="invCreateInvDate" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="col-12 col-md-6 mb-3">
                                <label class="form-label" for="invCreateDue">Due Date *</label>
                                <input type="date" class="form-control form-control-modern" id="invCreateDue" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="invoiceTreatment">Treatment</label>
                                <select class="form-select form-control-modern" id="invoiceTreatment" name="treatment_id">
                                    <option value="">Select treatment</option>
                                    <?php foreach ($treatments as $treatment): ?>
                                        <option value="<?php echo (int) $treatment['id']; ?>"
                                                data-cost="<?php echo htmlspecialchars((string) $treatment['cost']); ?>"
                                                <?php echo $selectedTreatmentId === (int) $treatment['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars((string) $treatment['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="subtotal">Subtotal ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" name="subtotal" id="subtotal" value="<?php echo htmlspecialchars(number_format($subtotal, 2, '.', '')); ?>" onchange="calculateTotal()">
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="discount_type">Discount Type</label>
                                <select class="form-select form-control-modern" name="discount_type" id="discount_type" onchange="calculateTotal()">
                                    <option value="fixed">Fixed ($)</option>
                                    <option value="percentage">Percentage (%)</option>
                                </select>
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="discount_value">Discount Value</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" name="discount_value" id="discount_value" value="0" onchange="calculateTotal()">
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="tax_rate">Tax Rate (%)</label>
                                <input type="number" step="0.01" class="form-control form-control-modern" name="tax_rate" id="tax_rate" value="0" onchange="calculateTotal()">
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="invCreatePaymentMethod">Payment Method</label>
                                <select class="form-select form-control-modern" id="invCreatePaymentMethod" name="payment_method">
                                    <option value="">Select payment method</option>
                                    <?php foreach ($paymentMethodOptions as $value => $label): ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo (($_POST['payment_method'] ?? '') === $value) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 col-md-4 mb-3">
                                <label class="form-label" for="total">Total Amount</label>
                                <input type="text" class="form-control form-control-modern" id="total" readonly value="$0.00">
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label" for="invCreateNotes">Notes</label>
                                <textarea class="form-control form-control-modern" name="notes" id="invCreateNotes" rows="3"></textarea>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="d-flex justify-content-lg-end gap-2 flex-wrap billing-create-invoice-form-actions">
                            <button type="submit" class="btn-green">Create Invoice</button>
                            <a href="invoices.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function syncSubtotalFromTreatment() {
    const treatmentSelect = document.getElementById('invoiceTreatment');
    const subtotalInput = document.getElementById('subtotal');
    if (!treatmentSelect || !subtotalInput) {
        return;
    }

    const selectedOption = treatmentSelect.options[treatmentSelect.selectedIndex];
    if (!selectedOption || selectedOption.value === '') {
        return;
    }

    const cost = selectedOption.getAttribute('data-cost');
    if (cost !== null && cost !== '') {
        subtotalInput.value = cost;
        calculateTotal();
    }
}

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

document.getElementById('invoiceTreatment')?.addEventListener('change', syncSubtotalFromTreatment);
calculateTotal();
if (document.getElementById('invoiceTreatment')?.value) {
    syncSubtotalFromTreatment();
}
</script>

<?php include '../layouts/footer.php'; ?>
