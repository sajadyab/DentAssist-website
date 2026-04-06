<?php
require_once __DIR__ . '/../includes/bootstrap.php';

Auth::requireLogin();

$patientId = (int) ($_GET['id'] ?? 0);

// Get patient details (+ linked login username)
$patient = PatientRepository::findWithAccountUsername($patientId);

if (!$patient) {
    header('Location: index.php');
    exit;
}

$whatsappNotice = $_SESSION['patient_add_whatsapp_notice'] ?? null;
unset($_SESSION['patient_add_whatsapp_notice']);

$pageTitle = 'Patient: ' . $patient['full_name'];

// Remove dental history image (handwritten) from xrays + disk
$removeMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_dental_history_xray_id'])) {
    $removeId = (int) ($_POST['remove_dental_history_xray_id'] ?? 0);
    if ($removeId > 0) {
        $rowToDelete = XrayRepository::findDentalHistoryHandwrittenById($removeId, $patientId);

        if ($rowToDelete) {
            $path = (string) ($rowToDelete['file_path'] ?? '');
            XrayRepository::deleteByIdForPatient($removeId, $patientId);

            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }

            $removeMessage = 'Image removed successfully.';
        } else {
            $removeMessage = 'Image not found or not removable.';
        }
    }
}

// Medical history: JSON from add.php { "conditions": [...], "notes": "..." } or legacy plain text
$medicalConditions = [];
$medicalAdditionalNotes = '';
$medicalHistoryLegacyText = null;
$decodedMedical = json_decode((string) ($patient['medical_history'] ?? ''), true);
if (is_array($decodedMedical) && (isset($decodedMedical['conditions']) || array_key_exists('notes', $decodedMedical))) {
    $c = $decodedMedical['conditions'] ?? [];
    if (is_array($c)) {
        $medicalConditions = array_values(array_filter(array_map('strval', $c)));
    }
    $medicalAdditionalNotes = trim((string) ($decodedMedical['notes'] ?? ''));
} elseif (trim((string) ($patient['medical_history'] ?? '')) !== '') {
    $medicalHistoryLegacyText = trim((string) $patient['medical_history']);
}

// Handwritten dental history images (same source as add.php / edit.php)
$dentalHistoryImages = XrayRepository::listDentalHistoryHandwrittenImages($patientId);

/** Public URL for an xrays row (handles xrays/ vs dental-history/ uploads). */
function patient_upload_url_for_xray(array $row): string
{
    $path = (string) ($row['file_path'] ?? '');
    $name = (string) ($row['file_name'] ?? '');
    if ($path !== '' && stripos($path, 'dental-history') !== false) {
        return UPLOAD_URL . 'dental-history/' . rawurlencode(basename($path));
    }
    return UPLOAD_URL . 'xrays/' . rawurlencode($name !== '' ? $name : basename($path));
}

// Get appointments
$appointments = AppointmentRepository::listForPatient($patientId);

// Get treatment plans
$treatmentPlans = TreatmentPlanRepository::listForPatient($patientId);

// Get X-rays
$xrays = XrayRepository::listForPatientExcludingDentalHistory($patientId);

// Get invoices
$invoices = InvoiceRepository::listForPatient($patientId);

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">
            <i class="fas fa-user"></i> <?php echo htmlspecialchars($patient['full_name'] ?: 'Patient #' . $patientId); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $patientId; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit Patient
            </a>
            <button class="btn btn-primary" onclick="scheduleAppointment()">
                <i class="fas fa-calendar-plus"></i> New Appointment
            </button>
        </div>
    </div>

    <?php if ($removeMessage): ?>
        <div class="alert alert-info mb-3"><?php echo htmlspecialchars($removeMessage); ?></div>
    <?php endif; ?>

    <?php if (is_array($whatsappNotice ?? null)): ?>
        <?php if (!empty($whatsappNotice['ok'])): ?>
            <div class="alert alert-success mb-3">Welcome message was sent to the patient's WhatsApp number.</div>
        <?php else: ?>
            <div class="alert alert-warning mb-3">
                <strong>WhatsApp not sent.</strong>
                <?php echo htmlspecialchars((string) ($whatsappNotice['error'] ?? 'Unknown error')); ?>
                <br><small>Run <code>npm start</code> for the WhatsApp bridge, scan the QR, then try again if needed.</small>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Patient Summary Card -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <h6>Contact Information</h6>
                            <p class="mb-1"><i class="fas fa-phone"></i> <?php echo $patient['phone']; ?></p>
                            <p class="mb-1"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars((string) ($patient['email'] ?? '')); ?></p>
                            <p class="mb-1"><i class="fas fa-user"></i>
                                <strong>Username:</strong>
                                <?php
                                $uname = trim((string) ($patient['account_username'] ?? ''));
                                echo $uname !== '' ? htmlspecialchars($uname) : '<span class="text-muted">—</span>';
                                ?>
                            </p>
                        </div>
                        
                        <div class="col-md-3">
                            <h6>Personal Info</h6>
                            <p class="mb-1"><strong>DOB:</strong> <?php echo formatDate($patient['date_of_birth']); ?></p>
                            <p class="mb-1"><strong>Gender:</strong> <?php echo ucfirst($patient['gender'] ?? 'Not specified'); ?></p>
                            <p class="mb-1"><strong>Age:</strong> 
                                <?php 
                                if ($patient['date_of_birth']) {
                                    $dob = new DateTime($patient['date_of_birth']);
                                    $now = new DateTime();
                                    echo $dob->diff($now)->y . ' years';
                                }
                                ?>
                            </p>
                        </div>
                        
                        <div class="col-md-3">
                            <h6>Insurance</h6>
                            <p class="mb-1"><strong>Provider:</strong> <?php echo $patient['insurance_provider'] ?? 'None'; ?></p>
                            <p class="mb-1"><strong>ID:</strong> <?php echo $patient['insurance_id'] ?? 'N/A'; ?></p>
                            <p class="mb-1"><strong>Type:</strong> <?php echo $patient['insurance_type'] ?? 'None'; ?></p>
                        </div>
                        
                        <div class="col-md-3">
                            <h6>Emergency Contact</h6>
                            <p class="mb-1"><strong>Name:</strong> <?php echo $patient['emergency_contact_name'] ?? 'Not provided'; ?></p>
                            <p class="mb-1"><strong>Phone:</strong> <?php echo $patient['emergency_contact_phone'] ?? 'N/A'; ?></p>
                            <p class="mb-1"><strong>Relation:</strong> <?php echo $patient['emergency_contact_relation'] ?? 'N/A'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="patientViewTabs" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" id="appointments-tab" data-bs-toggle="tab" 
                                    data-bs-target="#appointments" type="button" role="tab">
                                Appointments
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="treatment-tab" data-bs-toggle="tab" 
                                    data-bs-target="#treatment" type="button" role="tab">
                                Treatment Plans
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="medical-tab" data-bs-toggle="tab" 
                                    data-bs-target="#medical" type="button" role="tab">
                                Medical History
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="dental-history-tab" data-bs-toggle="tab"
                                    data-bs-target="#dental-history" type="button" role="tab">
                                Dental History
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="dental-tab" data-bs-toggle="tab" 
                                    data-bs-target="#dental" type="button" role="tab">
                                Dental Chart
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="xrays-tab" data-bs-toggle="tab" 
                                    data-bs-target="#xrays" type="button" role="tab">
                                X-Rays
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="billing-tab" data-bs-toggle="tab" 
                                    data-bs-target="#billing" type="button" role="tab">
                                Billing
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Appointments Tab -->
                        <div class="tab-pane active" id="appointments" role="tabpanel">
                            <?php if (empty($appointments)): ?>
                                <p class="text-muted">No appointments found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Time</th>
                                                <th>Doctor</th>
                                                <th>Treatment</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($appointments as $apt): ?>
                                                <tr>
                                                    <td><?php echo formatDate($apt['appointment_date']); ?></td>
                                                    <td><?php echo formatTime($apt['appointment_time']); ?></td>
                                                    <td>Dr. <?php echo htmlspecialchars($apt['doctor_name']); ?></td>
                                                    <td><?php echo $apt['treatment_type']; ?></td>
                                                    <td><?php echo getStatusBadge($apt['status']); ?></td>
                                                    <td>
                                                        <a href="../appointments/view.php?id=<?php echo $apt['id']; ?>" 
                                                           class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Treatment Plans Tab -->
                        <div class="tab-pane" id="treatment" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <h5>Treatment Plans</h5>
                                <a href="../treatment_plans/add.php?patient_id=<?php echo $patientId; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus"></i> New Treatment Plan
                                </a>
                            </div>
                            
                            <?php if (empty($treatmentPlans)): ?>
                                <p class="text-muted">No treatment plans found</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($treatmentPlans as $plan): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6><?php echo $plan['plan_name']; ?></h6>
                                                    <p class="mb-1">Status: 
                                                        <span class="badge bg-<?php 
                                                            echo $plan['status'] == 'completed' ? 'success' : 
                                                                ($plan['status'] == 'approved' ? 'info' : 'warning'); 
                                                        ?>">
                                                            <?php echo $plan['status']; ?>
                                                        </span>
                                                    </p>
                                                    <p class="mb-1">Estimated Cost: <?php echo formatCurrency($plan['estimated_cost']); ?></p>
                                                    <a href="../treatment_plans/view.php?id=<?php echo $plan['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary">View Details</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Medical History Tab (matches add.php: conditions + notes + allergies + medications) -->
                        <div class="tab-pane" id="medical" role="tabpanel">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h6>Conditions</h6>
                                    <?php if (!empty($medicalConditions)): ?>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($medicalConditions as $cond): ?>
                                                <span class="badge bg-danger"><?php echo htmlspecialchars($cond); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif ($medicalHistoryLegacyText !== null): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($medicalHistoryLegacyText)); ?></p>
                                        <p class="text-muted small mb-0 mt-1">Legacy format (stored before structured medical history).</p>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No conditions recorded.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6>Allergies</h6>
                                    <?php $allergiesVal = trim((string) ($patient['allergies'] ?? '')); ?>
                                    <p class="mb-0"><?php echo $allergiesVal !== '' ? nl2br(htmlspecialchars($allergiesVal)) : '<span class="text-muted">No allergies recorded</span>'; ?></p>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <h6>Current Medications</h6>
                                    <?php $medsVal = trim((string) ($patient['current_medications'] ?? '')); ?>
                                    <p class="mb-0"><?php echo $medsVal !== '' ? nl2br(htmlspecialchars($medsVal)) : '<span class="text-muted">No medications recorded</span>'; ?></p>
                                </div>
                                <div class="col-12 mb-3">
                                    <h6>Additional Notes</h6>
                                    <?php if ($medicalAdditionalNotes !== ''): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($medicalAdditionalNotes)); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">None.</p>
                                    <?php endif; ?>
                                </div>
                                <!-- Older records removed: columns no longer exist in current schema -->
                            </div>
                        </div>

                        <!-- Dental History Tab (handwritten image + narrative from add.php) -->
                        <div class="tab-pane" id="dental-history" role="tabpanel">
                            <div class="row">
                                <div class="col-12 mb-4">
                                    <h6>Handwritten dental history (scan / screenshot)</h6>

                                    <?php if (!empty($dentalHistoryImages)): ?>
                                        <div class="row">
                                            <?php foreach ($dentalHistoryImages as $img): ?>
                                                <div class="col-md-4 mb-3">
                                                    <div class="card h-100">
                                                        <div class="card-body p-2">
                                                            <?php $imgUrl = patient_upload_url_for_xray($img); ?>
                                                        <button
                                                            type="button"
                                                            class="btn p-0 border-0 bg-transparent"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#dentalHistoryModal"
                                                            data-img="<?php echo htmlspecialchars($imgUrl); ?>"
                                                            data-uploaded-at="<?php echo !empty($img['uploaded_at']) ? htmlspecialchars(formatDate($img['uploaded_at'])) : ''; ?>"
                                                            aria-label="View handwritten dental history image"
                                                        >
                                                                <img
                                                                    src="<?php echo htmlspecialchars($imgUrl); ?>"
                                                                    class="img-fluid rounded border"
                                                                    alt="Handwritten dental history"
                                                                    style="max-height: 240px; width: 100%; object-fit: contain;"
                                                                >
                                                        </button>
                                                            <?php if (!empty($img['uploaded_at'])): ?>
                                                                <div class="text-muted small mt-2">
                                                                    Uploaded <?php echo htmlspecialchars(formatDate($img['uploaded_at'])); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="card-footer bg-white d-flex justify-content-end">
                                                            <form method="POST" action="view.php?id=<?php echo $patientId; ?>" onsubmit="return confirm('Remove this image?');">
                                                                <input type="hidden" name="remove_dental_history_xray_id" value="<?php echo (int) $img['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger">
                                                                    <i class="fas fa-trash"></i> Remove
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No handwritten dental history image on file.</p>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-8 mb-3">
                                    <h6>Dental history</h6>
                                    <?php $dh = trim((string) ($patient['dental_history'] ?? '')); ?>
                                    <?php if ($dh !== ''): ?>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($dh)); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">Not recorded.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <h6>Last visit</h6>
                                    <?php if (patientHasLastVisitDate($patient['last_visit_date'] ?? null)): ?>
                                        <p class="mb-0"><?php echo htmlspecialchars(formatDate(normalizePatientOptionalDate($patient['last_visit_date'] ?? null))); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted mb-0">No visits</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                      <!-- Dental Chart Tab -->
<div class="tab-pane" id="dental" role="tabpanel">
    <div class="mb-3 p-3 bg-light rounded">
        <h6 class="mb-1"><i class="fas fa-mouse"></i> How to Use 3D Tooth Chart:</h6>
        <small class="text-muted">
            <ul class="mb-0">
                <li>Click and drag to rotate the teeth</li>
                <li>Scroll to zoom in/out</li>
                <li>Click on any tooth to view/edit details</li>
            </ul>
        </small>
    </div>
    <div id="tooth-chart-container" class="tooth-chart-container">
        <div class="text-center py-5">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading 3D tooth chart...</span>
            </div>
        </div>
    </div>
</div>
                        
                        <!-- X-Rays Tab -->
                        <div class="tab-pane" id="xrays" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <h5>X-Rays & Images</h5>
                                <a href="../xrays/upload.php?patient_id=<?php echo $patientId; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-upload"></i> Upload X-Ray
                                </a>
                            </div>
                            
                            <?php if (empty($xrays)): ?>
                                <p class="text-muted">No X-rays uploaded</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($xrays as $xray): ?>
                                        <div class="col-md-3 mb-3">
                                            <div class="card">
                                                <?php
                                                $isDentalHandwritten = (isset($xray['notes']) && strpos((string) $xray['notes'], 'Dental history (handwritten)') === 0);
                                                $imgUrl = patient_upload_url_for_xray($xray);
                                                ?>
                                                <img src="<?php echo htmlspecialchars($imgUrl); ?>"
                                                     class="card-img-top" alt="<?php echo $isDentalHandwritten ? 'Dental history' : 'X-Ray'; ?>">
                                                <div class="card-body">
                                                    <h6><?php echo $xray['xray_type']; ?></h6>
                                                    <p class="small"><?php echo $xray['findings']; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Billing Tab -->
                        <div class="tab-pane" id="billing" role="tabpanel">
                            <div class="d-flex justify-content-between mb-3">
                                <h5>Invoices</h5>
                                <a href="../billing/create_invoice.php?patient_id=<?php echo $patientId; ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-file-invoice"></i> Create Invoice
                                </a>
                            </div>
                            
                            <?php if (empty($invoices)): ?>
                                <p class="text-muted">No invoices found</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Invoice #</th>
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
                                            <?php foreach ($invoices as $invoice): ?>
                                                <tr>
                                                    <td><?php echo $invoice['invoice_number']; ?></td>
                                                    <td><?php echo formatDate($invoice['invoice_date']); ?></td>
                                                    <td><?php echo formatDate($invoice['due_date']); ?></td>
                                                    <td><?php echo formatCurrency($invoice['total_amount']); ?></td>
                                                    <td><?php echo formatCurrency($invoice['paid_amount']); ?></td>
                                                    <td><?php echo formatCurrency($invoice['balance_due']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $invoice['payment_status'] == 'paid' ? 'success' : 
                                                                ($invoice['payment_status'] == 'partial' ? 'warning' : 'danger'); 
                                                        ?>">
                                                            <?php echo $invoice['payment_status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="../billing/invoice_view.php?id=<?php echo $invoice['id']; ?>" 
                                                           class="btn btn-sm btn-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
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
            </div>
        </div>
    </div>
</div>

<!-- Tooth Chart Modal (added) -->
<div class="modal fade" id="toothModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tooth <span id="modal-tooth-number"></span> Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="tooth-number-input">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="tooth-status">
                        <option value="healthy">Healthy</option>
                        <option value="cavity">Cavity</option>
                        <option value="filled">Filled</option>
                        <option value="crown">Crown</option>
                        <option value="root-canal">Root Canal</option>
                        <option value="missing">Missing</option>
                        <option value="implant">Implant</option>
                        <option value="bridge">Bridge</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Diagnosis</label>
                    <textarea class="form-control" id="tooth-diagnosis" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Treatment</label>
                    <textarea class="form-control" id="tooth-treatment" rows="2"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea class="form-control" id="tooth-notes" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="delete-tooth-btn" class="btn btn-danger" onclick="toothChart.deleteTooth()">Mark as Missing</button>
                <button type="button" class="btn btn-primary" onclick="toothChart.saveTooth()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Include Three.js library for 3D tooth chart -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>

<!-- Include tooth chart JavaScript -->
<script src="<?php echo url('assets/js/tooth-chart-3d.js'); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        toothChart.init(<?php echo $patientId; ?>);
    });
</script>

<script>
function scheduleAppointment() {
    // Redirect to appointment scheduling page with patient ID
    window.location.href = '../appointments/add.php?patient_id=<?php echo $patientId; ?>';
}
</script>

<!-- Dental History Image Modal -->
<div class="modal fade" id="dentalHistoryModal" tabindex="-1" aria-labelledby="dentalHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dentalHistoryModalLabel">Handwritten Dental History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center">
                    <img
                        id="dentalHistoryModalImg"
                        src=""
                        alt="Handwritten dental history"
                        class="img-fluid rounded border"
                        style="max-height: 70vh; width: auto; object-fit: contain;"
                    >
                </div>
                <div id="dentalHistoryModalUploadedAt" class="text-muted small mt-2"></div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        const modalEl = document.getElementById('dentalHistoryModal');
        if (!modalEl) return;

        modalEl.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const imgUrl = button ? button.getAttribute('data-img') : '';
            const uploadedAt = button ? button.getAttribute('data-uploaded-at') : '';

            const imgEl = document.getElementById('dentalHistoryModalImg');
            const uploadedEl = document.getElementById('dentalHistoryModalUploadedAt');

            if (imgEl) imgEl.src = imgUrl || '';
            if (uploadedEl) uploadedEl.textContent = uploadedAt ? ('Uploaded ' + uploadedAt) : '';
        });
    })();
</script>

<?php include '../layouts/footer.php'; ?>
