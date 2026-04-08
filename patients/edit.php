<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();

$patientId = (int) ($_GET['id'] ?? 0);

// Fetch patient data
$patient = repo_patient_find_by_id($patientId);
if (!$patient) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Edit Patient: ' . htmlspecialchars($patient['full_name']);
$error = '';
$success = '';

// Fetch existing dental history image (handwritten)
$dentalHistoryImage = repo_xray_find_latest_dental_history_handwritten($patientId);

// Decode medical history JSON
$existingConditions = [];
$existingMedicalNotes = '';
$decodedMedical = json_decode((string)($patient['medical_history'] ?? ''), true);
if (is_array($decodedMedical)) {
    $conds = $decodedMedical['conditions'] ?? [];
    if (is_array($conds)) {
        $existingConditions = array_values(array_unique(array_map('strval', $conds)));
    }
    $existingMedicalNotes = (string)($decodedMedical['notes'] ?? '');
}

// ------------------------------------------------------------------
// Handle form submission
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $address = trim((string)($_POST['address'] ?? ''));
    $phoneE164 = trim((string)($_POST['phone'] ?? ''));

    // Basic E.164 validation
    if (!preg_match('/^\+[1-9][0-9]{6,14}$/', $phoneE164)) {
        $error = 'Invalid phone number. Please enter a full international number (e.g., +96181234567).';
    }

    // Process medical conditions
    $conditions = $_POST['medical_conditions'] ?? [];
    if (!is_array($conditions)) $conditions = [];
    $conditions = array_values(array_unique(array_filter(array_map('strval', $conditions))));
    $additionalNotes = trim((string)($_POST['medical_additional_notes'] ?? ''));
    $medicalHistoryPayload = json_encode([
        'conditions' => $conditions,
        'notes' => $additionalNotes,
    ], JSON_UNESCAPED_UNICODE);
    if ($medicalHistoryPayload === false) $medicalHistoryPayload = null;

    if (empty($error)) {
        $updatePayload = [
            'full_name' => $_POST['full_name'] ?? null,
            'date_of_birth' => $_POST['date_of_birth'] ?? null,
            'gender' => $_POST['gender'] ?? null,
            'phone' => $phoneE164,
            'email' => !empty($_POST['email']) ? $_POST['email'] : null,
            'emergency_contact_name' => $_POST['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $_POST['emergency_contact_phone'] ?? null,
            'emergency_contact_relation' => $_POST['emergency_contact_relation'] ?? null,
            'insurance_provider' => $_POST['insurance_provider'] ?? null,
            'insurance_id' => $_POST['insurance_id'] ?? null,
            'insurance_type' => $_POST['insurance_type'] ?? 'None',
            'insurance_coverage' => (int) ($_POST['insurance_coverage'] ?? 0),
            'medical_history' => $medicalHistoryPayload,
            'allergies' => $_POST['allergies'] ?? null,
            'current_medications' => $_POST['current_medications'] ?? null,
            'dental_history' => $_POST['dental_history'] ?? null,
            'last_visit_date' => normalizePatientOptionalDate($_POST['last_visit_date'] ?? null),
            'address' => $address !== '' ? $address : null,
            'country' => 'LB',
        ];

        $ok = repo_patient_update_from_edit_payload($patientId, $updatePayload);

        if ($ok) {
            logAction('UPDATE', 'patients', $patientId, $patient, $_POST);
            $success = 'Patient updated successfully';

            // Handle removal of existing dental history image
            if (isset($_POST['remove_dental_history_image']) && $_POST['remove_dental_history_image'] === '1' && $dentalHistoryImage && !empty($dentalHistoryImage['id'])) {
                $path = (string)($dentalHistoryImage['file_path'] ?? '');
                repo_xray_delete_by_id_for_patient((int) $dentalHistoryImage['id'], $patientId);
                if ($path !== '' && file_exists($path)) @unlink($path);
            }

            // Upload new handwritten dental history image
            if (isset($_FILES['dental_history_image']) && is_array($_FILES['dental_history_image']) && $_FILES['dental_history_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['dental_history_image'];
                $uploadDir = UPLOAD_PATH . 'dental-history/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

                $uploadResult = uploadFile($file, $uploadDir, ['image/jpeg', 'image/png']);
                if ($uploadResult['success']) {
                    $path = (string)($uploadResult['path'] ?? '');
                    repo_xray_insert_dental_history_handwritten_from_edit_form(
                        $patientId,
                        basename($path),
                        $path,
                        (int) ($file['size'] ?? 0),
                        (string) ($file['type'] ?? ''),
                        (int) Auth::userId()
                    );
                } else {
                    $error = $uploadResult['message'] ?? 'Dental history image upload failed.';
                }
            }

            // Refresh patient data
            $patient = repo_patient_find_by_id($patientId);
            $dentalHistoryImage = repo_xray_find_latest_dental_history_handwritten($patientId);
        } else {
            $error = 'Database error: could not update patient.';
        }
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Edit Patient: <?php echo htmlspecialchars($patient['full_name']); ?></h1>
        <div>
            <a href="view.php?id=<?php echo $patientId; ?>" class="btn btn-info">
                <i class="fas fa-eye"></i> View Patient
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data" id="patientForm">
                <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button">Patient Info</button></li>
                    <li class="nav-item"><button class="nav-link" id="dental-tab" data-bs-toggle="tab" data-bs-target="#dental" type="button">Dental History</button></li>
                </ul>

                <div class="tab-content">
                    <!-- Tab 1: Patient Info -->
                    <div class="tab-pane active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($patient['full_name']); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" name="date_of_birth" value="<?php echo htmlspecialchars($patient['date_of_birth']); ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select</option>
                                    <option value="male" <?php echo $patient['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $patient['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                    <option value="other" <?php echo $patient['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <!-- Phone with intl-tel-input -->
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="tel" id="phone_display" class="form-control" autocomplete="off">
                                <input type="hidden" name="phone" id="phone_hidden">
                                <small class="text-muted">International format with country code</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($patient['email'] ?? ''); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($patient['address'] ?? ''); ?>">
                            </div>

                            <div class="col-12"><h5 class="mt-3">Emergency Contact</h5><hr></div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name" value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_contact_phone" value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Relationship</label>
                                <input type="text" class="form-control" name="emergency_contact_relation" value="<?php echo htmlspecialchars($patient['emergency_contact_relation'] ?? ''); ?>">
                            </div>

                            <div class="col-12"><h5 class="mt-3">Insurance</h5><hr></div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance Provider</label>
                                <input type="text" class="form-control" name="insurance_provider" value="<?php echo htmlspecialchars($patient['insurance_provider'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance ID</label>
                                <input type="text" class="form-control" name="insurance_id" value="<?php echo htmlspecialchars($patient['insurance_id'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance Type</label>
                                <select class="form-select" name="insurance_type">
                                    <option value="None" <?php echo ($patient['insurance_type'] ?? 'None') == 'None' ? 'selected' : ''; ?>>None</option>
                                    <option value="Private" <?php echo ($patient['insurance_type'] ?? '') == 'Private' ? 'selected' : ''; ?>>Private</option>
                                    <option value="Social Security" <?php echo ($patient['insurance_type'] ?? '') == 'Social Security' ? 'selected' : ''; ?>>Social Security</option>
                                    <option value="Medicaid" <?php echo ($patient['insurance_type'] ?? '') == 'Medicaid' ? 'selected' : ''; ?>>Medicaid</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Coverage %</label>
                                <input type="number" class="form-control" name="insurance_coverage" min="0" max="100" value="<?php echo (int)($patient['insurance_coverage'] ?? 0); ?>">
                            </div>

                            <div class="col-12"><h5 class="mt-3">Medical History</h5><hr></div>
                            <div class="col-12 mb-2">
                                <label class="form-label">Medical History</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Cardiovascular Diseases" id="mh_cardio" <?php echo in_array('Cardiovascular Diseases', $existingConditions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_cardio">Cardiovascular Diseases</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Hypertension" id="mh_htn" <?php echo in_array('Hypertension', $existingConditions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_htn">Hypertension</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Autoimmune diseases" id="mh_autoimmune" <?php echo in_array('Autoimmune diseases', $existingConditions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_autoimmune">Autoimmune diseases</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Immunosuppression" id="mh_immuno" <?php echo in_array('Immunosuppression', $existingConditions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_immuno">Immunosuppression</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Diabetes" id="mh_diabetes" <?php echo in_array('Diabetes', $existingConditions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_diabetes">Diabetes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Stroke history" id="mh_stroke" <?php echo in_array('Stroke history', $existingConditions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_stroke">Stroke history</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Osteoporosis" id="mh_osteo" <?php echo in_array('Osteoporosis', $existingConditions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_osteo">Osteoporosis</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Epilepsy" id="mh_epilepsy" <?php echo in_array('Epilepsy', $existingConditions) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_epilepsy">Epilepsy</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Allergies</label>
                                <textarea class="form-control" name="allergies" rows="3"><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Medications</label>
                                <textarea class="form-control" name="current_medications" rows="3"><?php echo htmlspecialchars($patient['current_medications'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Additional Notes</label>
                                <textarea class="form-control" name="medical_additional_notes" rows="2"><?php echo htmlspecialchars($existingMedicalNotes); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Dental History -->
                    <div class="tab-pane" id="dental" role="tabpanel">
                        <div class="row">
                            <?php if ($dentalHistoryImage && !empty($dentalHistoryImage['file_path'])): ?>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Handwritten Dental History Image</label>
                                    <div class="mb-2">
                                        <img src="<?php echo htmlspecialchars(UPLOAD_URL . 'dental-history/' . basename((string)$dentalHistoryImage['file_path'])); ?>" style="max-width:100%; height:auto; border:1px solid #e5e5e5; border-radius:6px;">
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_dental_history_image" value="1" id="removeDentalImg">
                                        <label class="form-check-label" for="removeDentalImg">Remove this image</label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="col-12 mb-3">
                                <label class="form-label">Dental History</label>
                                <textarea class="form-control" name="dental_history" rows="3"><?php echo htmlspecialchars($patient['dental_history'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Visit Date</label>
                                <input type="date" class="form-control" name="last_visit_date" value="<?php echo htmlspecialchars(normalizePatientOptionalDate($patient['last_visit_date'] ?? null) ?? ''); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Handwritten Dental History Image</label>
                                <input type="file" class="form-control" name="dental_history_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                <div class="form-text">Optional. Upload a photo/scan of handwritten notes.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="mt-3">
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Patient</button>
                    <a href="view.php?id=<?php echo $patientId; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css">
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/intlTelInput.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const existingPhone = <?php echo json_encode((string)($patient['phone'] ?? '')); ?>;
    const phoneDisplay = document.querySelector('#phone_display');
    const iti = window.intlTelInput(phoneDisplay, {
        initialCountry: "lb",
        separateDialCode: true,
        preferredCountries: ["lb", "ae", "sa", "fr", "us"],
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js"
    });
    if (existingPhone && existingPhone.trim() !== '') {
        iti.setNumber(existingPhone);
    }

    const form = document.getElementById('patientForm');
    const phoneHidden = document.getElementById('phone_hidden');
    form.addEventListener('submit', function(e) {
        const fullNumber = iti.getNumber();
        if (fullNumber && fullNumber.trim() !== '') {
            phoneHidden.value = fullNumber;
        } else {
            phoneHidden.value = '';
            e.preventDefault();
            alert('Please enter a valid phone number (international format).');
        }
    });

    // Optional: search box in country dropdown
    phoneDisplay.addEventListener('click', insertSearchBox);
    phoneDisplay.addEventListener('focus', insertSearchBox);
    function insertSearchBox() {
        setTimeout(() => {
            const list = document.querySelector('.iti__country-list');
            if (!list || list.querySelector('.country-search')) return;
            const search = document.createElement('input');
            search.className = 'country-search form-control';
            search.placeholder = 'Search country...';
            search.type = 'text';
            search.style.margin = '8px';
            search.style.width = 'calc(100% - 16px)';
            list.prepend(search);
            search.focus();
            search.addEventListener('keydown', e => e.stopPropagation());
            search.addEventListener('keyup', e => e.stopPropagation());
            search.addEventListener('input', function() {
                const filter = this.value.toLowerCase();
                const countries = list.querySelectorAll('.iti__country');
                countries.forEach(country => {
                    const nameEl = country.querySelector('.iti__country-name');
                    if (!nameEl) return;
                    const rawName = nameEl.innerText;
                    const name = rawName.toLowerCase();
                    const dial = (country.querySelector('.iti__dial-code') || {}).innerText || '';
                    const match = name.includes(filter) || dial.includes(filter);
                    if (match) {
                        country.style.display = '';
                        const start = name.indexOf(filter);
                        if (filter !== '' && start !== -1) {
                            const before = rawName.substring(0, start);
                            const middle = rawName.substring(start, start + filter.length);
                            const after = rawName.substring(start + filter.length);
                            nameEl.innerHTML = before + '<strong>' + middle + '</strong>' + after;
                        } else {
                            nameEl.textContent = rawName;
                        }
                    } else {
                        country.style.display = 'none';
                        nameEl.textContent = rawName;
                    }
                });
            });
        }, 200);
    }
});
</script>
