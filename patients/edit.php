<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

function normalizeE164Phone(?string $countryCode, ?string $localNumber): array
{
    $code = preg_replace('/[^0-9]/', '', (string) $countryCode);
    $number = preg_replace('/[^0-9]/', '', (string) $localNumber);

    if ($code === '' || $number === '') {
        return ['ok' => false, 'value' => null, 'error' => 'Phone number is required.'];
    }

    $digitRules = [
        '961' => [8],  // Lebanon
        '1' => [10],   // US/Canada (NANP)
        '44' => [10],
        '33' => [9],
        '49' => [10, 11],
        '971' => [9],
        '20' => [10],
        '966' => [9],
        '90' => [10],
        '962' => [9],
    ];

    if (isset($digitRules[$code])) {
        $allowed = $digitRules[$code];
        if (!in_array(strlen($number), $allowed, true)) {
            $hint = implode(' or ', array_map(fn ($n) => (string) $n, $allowed));
            return ['ok' => false, 'value' => null, 'error' => "Invalid phone digits for +{$code}. Expected {$hint} digits."];
        }
    } else {
        if (strlen($number) < 6 || strlen($number) > 15) {
            return ['ok' => false, 'value' => null, 'error' => 'Invalid phone number length.'];
        }
    }

    return ['ok' => true, 'value' => '+' . $code . $number, 'error' => null];
}

$db = Database::getInstance();
$patientId = $_GET['id'] ?? 0;

// Get patient details
$patient = $db->fetchOne(
    "SELECT * FROM patients WHERE id = ?",
    [$patientId],
    "i"
);

if (!$patient) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Edit Patient: ' . $patient['full_name'];
$error = '';
$success = '';

$dentalHistoryImage = $db->fetchOne(
    "SELECT id, file_path FROM xrays
     WHERE patient_id = ? AND xray_type = 'Other' AND notes LIKE 'Dental history (handwritten)%'
     ORDER BY uploaded_at DESC, id DESC
     LIMIT 1",
    [$patientId],
    "i"
);

$existingConditions = [];
$existingMedicalNotes = '';
$decodedMedical = json_decode((string) ($patient['medical_history'] ?? ''), true);
if (is_array($decodedMedical)) {
    $conds = $decodedMedical['conditions'] ?? [];
    if (is_array($conds)) {
        $existingConditions = array_values(array_unique(array_map('strval', $conds)));
    }
    $existingMedicalNotes = (string) ($decodedMedical['notes'] ?? '');
}

$prefillCountry = '961';
$prefillNumber = '';
$existingPhone = trim((string) ($patient['phone'] ?? ''));
if (preg_match('/^\+([0-9]{1,4})([0-9]+)$/', $existingPhone, $m)) {
    $prefillCountry = $m[1];
    $prefillNumber = $m[2];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $address = trim((string) ($_POST['address'] ?? ''));

    $phoneParsed = normalizeE164Phone($_POST['phone_country'] ?? null, $_POST['phone_number'] ?? null);
    if (!$phoneParsed['ok']) {
        $error = (string) $phoneParsed['error'];
    }

    $conditions = $_POST['medical_conditions'] ?? [];
    if (!is_array($conditions)) {
        $conditions = [];
    }
    $conditions = array_values(array_unique(array_filter(array_map('strval', $conditions))));
    $additionalNotes = trim((string) ($_POST['medical_additional_notes'] ?? ''));
    $medicalHistoryPayload = json_encode([
        'conditions' => $conditions,
        'notes' => $additionalNotes,
    ], JSON_UNESCAPED_UNICODE);
    if ($medicalHistoryPayload === false) {
        $medicalHistoryPayload = null;
    }

    if ($error === '') {
        // Update patient
        $result = $db->execute(
            "UPDATE patients SET 
                full_name = ?, date_of_birth = ?, gender = ?, phone = ?, email = ?,
                emergency_contact_name = ?, emergency_contact_phone = ?, emergency_contact_relation = ?,
                insurance_provider = ?, insurance_id = ?, insurance_type = ?, insurance_coverage = ?,
                medical_history = ?, allergies = ?, current_medications = ?, past_surgeries = ?,
                chronic_conditions = ?, dental_history = ?, previous_dentist = ?, last_visit_date = ?,
                address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?
             WHERE id = ?",
            [
                $_POST['full_name'],
                $_POST['date_of_birth'] ?? null,
                $_POST['gender'] ?? null,
                (string) $phoneParsed['value'],
                $_POST['email'],
                $_POST['emergency_contact_name'] ?? null,
                $_POST['emergency_contact_phone'] ?? null,
                $_POST['emergency_contact_relation'] ?? null,
                $_POST['insurance_provider'] ?? null,
                $_POST['insurance_id'] ?? null,
                $_POST['insurance_type'] ?? 'None',
                $_POST['insurance_coverage'] ?? 0,
                $medicalHistoryPayload,
                $_POST['allergies'] ?? null,
                $_POST['current_medications'] ?? null,
                null,
                null,
                $_POST['dental_history'] ?? null,
                null,
                normalizePatientOptionalDate($_POST['last_visit_date'] ?? null),
                $address !== '' ? $address : null,
                null,
                null,
                null,
                null,
                'USA',
                $patientId
            ],
            "ssssssssssiissssssssssssssi"
        );

        if ($result !== false) {
        logAction('UPDATE', 'patients', $patientId, $patient, $_POST);
        $success = 'Patient updated successfully';

        if (isset($_POST['remove_dental_history_image']) && $_POST['remove_dental_history_image'] === '1' && $dentalHistoryImage && !empty($dentalHistoryImage['id'])) {
            $path = (string) ($dentalHistoryImage['file_path'] ?? '');
            $db->execute("DELETE FROM xrays WHERE id = ? AND patient_id = ? LIMIT 1", [(int) $dentalHistoryImage['id'], (int) $patientId], "ii");
            if ($path !== '') {
                @unlink($path);
            }
        }

        // Optional upload: handwritten dental history image stored in xrays table (type "Other")
        if (isset($_FILES['dental_history_image']) && is_array($_FILES['dental_history_image']) && ($_FILES['dental_history_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['dental_history_image'];
            $uploadDir = UPLOAD_PATH . 'dental-history/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $uploadResult = uploadFile($file, $uploadDir, ['image/jpeg', 'image/png']);
            if ($uploadResult['success']) {
                $path = (string) ($uploadResult['path'] ?? '');
                $db->insert(
                    "INSERT INTO xrays (patient_id, file_name, file_path, file_size, mime_type, xray_type, tooth_numbers, findings, notes, uploaded_by)
                     VALUES (?, ?, ?, ?, ?, 'Other', ?, ?, ?, ?)",
                    [
                        $patientId,
                        basename($path),
                        $path,
                        (int) ($file['size'] ?? 0),
                        (string) ($file['type'] ?? ''),
                        null,
                        null,
                        'Dental history (handwritten) uploaded from patient edit form.',
                        Auth::userId(),
                    ],
                    "ississssi"
                );
            } else {
                $error = $uploadResult['message'] ?? 'Dental history image upload failed.';
            }
        }
        
        // Refresh patient data
        $patient = $db->fetchOne("SELECT * FROM patients WHERE id = ?", [$patientId], "i");
        $dentalHistoryImage = $db->fetchOne(
            "SELECT id, file_path FROM xrays
             WHERE patient_id = ? AND xray_type = 'Other' AND notes LIKE 'Dental history (handwritten)%'
             ORDER BY uploaded_at DESC, id DESC
             LIMIT 1",
            [$patientId],
            "i"
        );
    } else {
        $error = 'Error updating patient';
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
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="info-tab" data-bs-toggle="tab"
                                data-bs-target="#info" type="button" role="tab">
                            Patient Info
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="dental-tab" data-bs-toggle="tab"
                                data-bs-target="#dental" type="button" role="tab">
                            Dental History
                        </button>
                    </li>
                </ul>
                
                <!-- Tab panes -->
                <div class="tab-content">
                    <div class="tab-pane active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo htmlspecialchars($patient['full_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" name="date_of_birth" 
                                       value="<?php echo $patient['date_of_birth']; ?>" required>
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

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input
                                    type="tel"
                                    id="phone"
                                    class="form-control"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    placeholder="Enter phone number"
                                    value="<?php echo htmlspecialchars($prefillNumber); ?>"
                                    required
                                >
                                <input type="hidden" name="phone_country" id="phone_country" value="<?php echo htmlspecialchars($prefillCountry); ?>">
                                <input type="hidden" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($prefillNumber); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address"
                                       value="<?php echo htmlspecialchars($patient['address_line1'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-12">
                                <h5 class="mt-3">Emergency Contact</h5>
                                <hr>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name" 
                                       value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_contact_phone" 
                                       value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Relationship</label>
                                <input type="text" class="form-control" name="emergency_contact_relation" 
                                       value="<?php echo htmlspecialchars($patient['emergency_contact_relation'] ?? ''); ?>">
                            </div>

                            <div class="col-12">
                                <h5 class="mt-3">Insurance</h5>
                                <hr>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance Provider</label>
                                <input type="text" class="form-control" name="insurance_provider" 
                                       value="<?php echo htmlspecialchars($patient['insurance_provider'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance ID</label>
                                <input type="text" class="form-control" name="insurance_id" 
                                       value="<?php echo htmlspecialchars($patient['insurance_id'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance Type</label>
                                <select class="form-select" name="insurance_type">
                                    <option value="None" <?php echo $patient['insurance_type'] == 'None' ? 'selected' : ''; ?>>None</option>
                                    <option value="Private" <?php echo $patient['insurance_type'] == 'Private' ? 'selected' : ''; ?>>Private</option>
                                    <option value="Social Security" <?php echo $patient['insurance_type'] == 'Social Security' ? 'selected' : ''; ?>>Social Security</option>
                                    <option value="Medicaid" <?php echo $patient['insurance_type'] == 'Medicaid' ? 'selected' : ''; ?>>Medicaid</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Coverage %</label>
                                <input type="number" class="form-control" name="insurance_coverage" 
                                       min="0" max="100" value="<?php echo $patient['insurance_coverage'] ?? 0; ?>">
                            </div>

                            <div class="col-12">
                                <h5 class="mt-3">Medical History</h5>
                                <hr>
                            </div>
                            <div class="col-12 mb-2">
                                <label class="form-label">Medical History</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Cardiovascular Diseases" id="mh_cardio"
                                                <?php echo in_array('Cardiovascular Diseases', $existingConditions, true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_cardio">Cardiovascular Diseases</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Hypertension" id="mh_htn"
                                                <?php echo in_array('Hypertension', $existingConditions, true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_htn">Hypertension</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Autoimmune diseases" id="mh_autoimmune"
                                                <?php echo in_array('Autoimmune diseases', $existingConditions, true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_autoimmune">Autoimmune diseases</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Immunosuppression" id="mh_immuno"
                                                <?php echo in_array('Immunosuppression', $existingConditions, true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_immuno">Immunosuppression</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Diabetes" id="mh_diabetes"
                                                <?php echo in_array('Diabetes', $existingConditions, true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_diabetes">Diabetes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Stroke history" id="mh_stroke"
                                                <?php echo in_array('Stroke history', $existingConditions, true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_stroke">Stroke history</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Osteoporosis" id="mh_osteo"
                                                <?php echo in_array('Osteoporosis', $existingConditions, true) ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="mh_osteo">Osteoporosis</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Epilepsy" id="mh_epilepsy"
                                                <?php echo in_array('Epilepsy', $existingConditions, true) ? 'checked' : ''; ?>>
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
                    
                    <!-- Dental History -->
                    <div class="tab-pane" id="dental" role="tabpanel">
                        <div class="row">
                            <?php if ($dentalHistoryImage && !empty($dentalHistoryImage['file_path'])): ?>
                                <div class="col-12 mb-3">
                                    <label class="form-label">Handwritten Dental History Image</label>
                                    <div class="mb-2">
                                        <img src="<?php echo htmlspecialchars(UPLOAD_URL . 'dental-history/' . basename((string) $dentalHistoryImage['file_path'])); ?>"
                                             alt="Handwritten dental history" style="max-width: 100%; height: auto; border: 1px solid #e5e5e5; border-radius: 6px;">
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
                                <?php $lastVisitVal = normalizePatientOptionalDate($patient['last_visit_date'] ?? null); ?>
                                <input type="date" class="form-control" name="last_visit_date"
                                       value="<?php echo $lastVisitVal !== null ? htmlspecialchars($lastVisitVal) : ''; ?>">
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">Handwritten Dental History Image</label>
                                <input type="file" class="form-control" name="dental_history_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                <div class="form-text">Optional. Upload a photo/scan of handwritten notes for digitization.</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr class="mt-3">
                
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update Patient
                    </button>
                    <a href="view.php?id=<?php echo $patientId; ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css">
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/intlTelInput.min.js"></script>
<style>
  .iti { width: 100%; }
  .iti input.form-control { width: 100%; }
  .iti--separate-dial-code .iti__selected-flag { background-color: #f8f9fa; border-right: 1px solid #dee2e6; }
  .iti__country-list { z-index: 2000; }
</style>
<script>
const phoneInput = document.querySelector("#phone");
if (phoneInput) {
  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "lb",
    separateDialCode: true,
    preferredCountries: ["lb", "ae", "sa", "fr", "us"],
    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js"
  });

  const dialToIso2 = {
    "961": "lb",
    "971": "ae",
    "966": "sa",
    "33": "fr",
    "1": "us",
    "44": "gb",
    "49": "de",
    "39": "it",
    "34": "es",
    "20": "eg",
    "90": "tr",
    "962": "jo",
    "1-ca": "ca"
  };

  const hiddenDial = document.querySelector("#phone_country");
  const existingDial = hiddenDial ? String(hiddenDial.value || "") : "";
  if (existingDial) {
    const iso2 = dialToIso2[existingDial] || dialToIso2[existingDial + "-ca"] || null;
    if (iso2) {
      try { iti.setCountry(iso2); } catch (e) {}
    }
  }

  const phoneLimits = {
    lb: 8,
    ae: 9,
    sa: 9,
    fr: 9,
    us: 10,
    ca: 10,
    gb: 10,
    de: 11,
    it: 10,
    es: 9
  };

  function enforceLimit() {
    phoneInput.value = phoneInput.value.replace(/[^0-9]/g, "");
    const country = iti.getSelectedCountryData().iso2;
    const max = phoneLimits[country] || 15;
    if (phoneInput.value.length > max) {
      phoneInput.value = phoneInput.value.slice(0, max);
    }
    phoneInput.maxLength = max;
  }

  function syncHidden() {
    const data = iti.getSelectedCountryData();
    const dial = String((data && data.dialCode) ? data.dialCode : "");
    const ccEl = document.querySelector("#phone_country");
    const numEl = document.querySelector("#phone_number");
    if (ccEl) ccEl.value = dial;
    if (numEl) numEl.value = phoneInput.value;
  }

  function onPhoneChange() {
    enforceLimit();
    syncHidden();
  }

  phoneInput.addEventListener("input", onPhoneChange);
  phoneInput.addEventListener("countrychange", onPhoneChange);

  phoneInput.addEventListener("click", insertSearchBox);
  phoneInput.addEventListener("focus", insertSearchBox);

  function insertSearchBox() {
    setTimeout(() => {
      const list = document.querySelector(".iti__country-list");
      if (!list) return;
      if (list.querySelector(".country-search")) return;

      const search = document.createElement("input");
      search.className = "country-search form-control";
      search.placeholder = "Search country...";
      search.type = "text";
      search.style.margin = "8px";
      search.style.width = "calc(100% - 16px)";

      list.prepend(search);
      search.focus();

      search.addEventListener("keydown", e => e.stopPropagation());
      search.addEventListener("keyup", e => e.stopPropagation());

      search.addEventListener("input", function () {
        const filter = this.value.toLowerCase();
        const countries = list.querySelectorAll(".iti__country");

        countries.forEach(country => {
          const nameEl = country.querySelector(".iti__country-name");
          if (!nameEl) return;
          const rawName = nameEl.innerText;
          const name = rawName.toLowerCase();
          const dial = (country.querySelector(".iti__dial-code") || {}).innerText || "";
          const match = name.includes(filter) || dial.includes(filter);

          if (match) {
            country.style.display = "";
            const start = name.indexOf(filter);
            if (filter !== "" && start !== -1) {
              const before = rawName.substring(0, start);
              const middle = rawName.substring(start, start + filter.length);
              const after = rawName.substring(start + filter.length);
              nameEl.innerHTML = before + "<strong>" + middle + "</strong>" + after;
            } else {
              nameEl.textContent = rawName;
            }
          } else {
            country.style.display = "none";
            nameEl.textContent = rawName;
          }
        });
      });
    }, 200);
  }

  const form = phoneInput.closest("form");
  if (form) {
    form.addEventListener("submit", function () {
      onPhoneChange();
    });
  }

  onPhoneChange();
}
</script>