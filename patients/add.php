<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

function normalizeE164Phone(?string $countryCode, ?string $localNumber): array
{
    $code = preg_replace('/[^0-9]/', '', (string) $countryCode);
    $number = preg_replace('/[^0-9]/', '', (string) $localNumber);

    if ($code === '' || $number === '') {
        return ['ok' => false, 'value' => null, 'error' => 'Phone number is required.'];
    }

    // Digit validation for known countries (extend as needed)
    $digitRules = [
        '961' => [8],  // Lebanon
        '1' => [10],   // US/Canada (NANP)
        '44' => [10],  // UK (common length; varies, keep strict for now)
        '33' => [9],   // France (excluding leading 0)
        '49' => [10, 11], // Germany (varies)
        '971' => [9],  // UAE (often 9)
        '20' => [10],  // Egypt (often 10)
        '966' => [9],  // Saudi Arabia (often 9)
        '90' => [10],  // Turkey (often 10)
        '962' => [9],  // Jordan (often 9)
    ];

    if (isset($digitRules[$code])) {
        $allowed = $digitRules[$code];
        if (!in_array(strlen($number), $allowed, true)) {
            $hint = implode(' or ', array_map(fn ($n) => (string) $n, $allowed));
            return ['ok' => false, 'value' => null, 'error' => "Invalid phone digits for +{$code}. Expected {$hint} digits."];
        }
    } else {
        // Basic sanity for unknown countries
        if (strlen($number) < 6 || strlen($number) > 15) {
            return ['ok' => false, 'value' => null, 'error' => 'Invalid phone number length.'];
        }
    }

    return ['ok' => true, 'value' => '+' . $code . $number, 'error' => null];
}

/**
 * Patient login username: sanitized full name (spaces replaced with underscores).
 * Retries with number suffix until no other patient (and no other user) already uses that username.
 */
function generateUniquePatientUsername(Database $db, string $fullName): string
{
    // Create base username from full name: replace spaces with underscores, remove special chars
    $baseSlug = strtolower(preg_replace('/[^a-zA-Z0-9\s]+/', '', $fullName));
    $baseSlug = preg_replace('/\s+/', '_', trim($baseSlug));

    if ($baseSlug === '') {
        $baseSlug = 'patient';
    }
    if (strlen($baseSlug) > 50) {
        $baseSlug = substr($baseSlug, 0, 50);
    }

    $candidate = $baseSlug;
    $counter = 1;

    while (true) {
        $otherPatient = $db->fetchOne(
            "SELECT p.id FROM patients p
             INNER JOIN users u ON u.id = p.user_id
             WHERE u.username = ?",
            [$candidate],
            's'
        );
        if (!$otherPatient) {
            $anyUser = $db->fetchOne('SELECT id FROM users WHERE username = ?', [$candidate], 's');
            if (!$anyUser) {
                return $candidate;
            }
        }

        // If base username exists, append counter
        $candidate = $baseSlug . '_' . $counter;
        $counter++;

        // Prevent infinite loop
        if ($counter > 1000) {
            throw new Exception('Unable to generate unique username after 1000 attempts.');
        }
    }

    throw new Exception('Could not generate a unique username. Please try again.');
}

Auth::requireLogin();
$pageTitle = 'Add Patient';

$db = Database::getInstance();
$conn = $db->getConnection();
$error = '';
$success = '';

// Check for generated credentials in session (for display after redirect)
$generatedUsername = $_SESSION['generated_username'] ?? null;
$generatedPassword = $_SESSION['generated_password'] ?? null;
unset($_SESSION['generated_username'], $_SESSION['generated_password']);

$whatsappNotice = $_SESSION['patient_add_whatsapp_notice'] ?? null;
unset($_SESSION['patient_add_whatsapp_notice']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate required fields
    $required = ['full_name', 'date_of_birth', 'phone_country', 'phone_number', 'email'];
    $missing = [];
    foreach ($required as $field) {
        if ($field === 'email') {
            if (trim((string) ($_POST['email'] ?? '')) === '') {
                $missing[] = $field;
            }
        } elseif (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        $labels = [
            'full_name' => 'full name',
            'date_of_birth' => 'date of birth',
            'phone_country' => 'phone country code',
            'phone_number' => 'phone number',
            'email' => 'email',
        ];
        $missingLabels = array_map(function ($f) use ($labels) {
            return $labels[$f] ?? $f;
        }, $missing);
        $error = 'Please fill in all required fields: ' . implode(', ', $missingLabels);
    }

    if (empty($error)) {
        try {
            // Start transaction (users + patients must both succeed)
            $conn->begin_transaction();

            $emailInput = trim((string) ($_POST['email'] ?? ''));
            if ($emailInput === '') {
                throw new Exception('Email is required.');
            }

            // Check if email is already taken by another user
            $existingUser = $db->fetchOne('SELECT id FROM users WHERE email = ?', [$emailInput], 's');
            if ($existingUser) {
                throw new Exception('This email is already registered. Please use a different email.');
            }

            $patientsEmail = $emailInput;
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $phoneParsed = normalizeE164Phone($_POST['phone_country'] ?? null, $_POST['phone_number'] ?? null);
            if (!$phoneParsed['ok']) {
                throw new Exception((string) $phoneParsed['error']);
            }
            $phone = (string) $phoneParsed['value'];
            $address = trim((string) ($_POST['address'] ?? ''));

            $dentalUploadPath = null;

            // Always create user account for the patient (unique username: name + 4 random digits)
            $username = generateUniquePatientUsername($db, $fullName);

            // Use the actual email provided by the user for the users table
            $usersEmail = $emailInput;

            $generatedPassword = generateRandomPassword();
            $passwordHash = Auth::hashPassword($generatedPassword);

            $userId = $db->insert(
                "INSERT INTO users (username, email, password_hash, full_name, role, phone, is_active)
                 VALUES (?, ?, ?, ?, 'patient', ?, 1)",
                [
                    $username,
                    $usersEmail,
                    $passwordHash,
                    $fullName,
                    $phone,
                ],
                "sssss"
            );

            if (!$userId) {
                throw new Exception('Database insert failed (user)');
            }

            sync_push_row_now('users', (int) $userId);

            // Optional: upload handwritten dental history image (stored in xrays table as type "Other")
            if (isset($_FILES['dental_history_image']) && is_array($_FILES['dental_history_image']) && ($_FILES['dental_history_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $file = $_FILES['dental_history_image'];
                $uploadDir = UPLOAD_PATH . 'dental-history/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $uploadResult = uploadFile($file, $uploadDir, ['image/jpeg', 'image/png']);
                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['message'] ?? 'Dental history image upload failed.');
                }
                $dentalUploadPath = $uploadResult['path'] ?? null;
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

            $meds = $_POST['medications'] ?? [];
            if (!is_array($meds)) {
                $meds = [];
            }
            $meds = array_values(array_unique(array_filter(array_map('strval', $meds))));
            $medsPayload = json_encode($meds, JSON_UNESCAPED_UNICODE);
            if ($medsPayload === false) {
                $medsPayload = null;
            }

            $allergiesFlag = 'no';
            $ay = !empty($_POST['allergies_yes']);
            $an = !empty($_POST['allergies_no']);
            if ($ay && !$an) {
                $allergiesFlag = 'yes';
            }
            if ($an && !$ay) {
                $allergiesFlag = 'no';
            }

            // Insert patient linked to user (schema-compatible: address/address_line1 may differ between DBs)
            $patientColumns = [
                'full_name',
                'date_of_birth',
                'gender',
                'phone',
                'email',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relation',
                'insurance_provider',
                'insurance_id',
                'insurance_type',
                'insurance_coverage',
                'medical_history',
                'allergies',
                'current_medications',
                'dental_history',
                'last_visit_date',
            ];
            $patientValues = [
                $fullName,
                $_POST['date_of_birth'] ?? null,
                $_POST['gender'] ?? null,
                $phone,
                $patientsEmail,
                $_POST['emergency_contact_name'] ?? null,
                $_POST['emergency_contact_phone'] ?? null,
                $_POST['emergency_contact_relation'] ?? null,
                $_POST['insurance_provider'] ?? null,
                $_POST['insurance_id'] ?? null,
                $_POST['insurance_type'] ?? 'None',
                (int) ($_POST['insurance_coverage'] ?? 0),
                $medicalHistoryPayload,
                $allergiesFlag,
                $medsPayload,
                $_POST['dental_history'] ?? null,
                normalizePatientOptionalDate($_POST['last_visit_date'] ?? null),
            ];
            $patientTypes = str_repeat('s', 11) . 'i' . str_repeat('s', 5);

            $addressValue = $address !== '' ? $address : null;
            if (dbColumnExists('patients', 'address')) {
                $patientColumns[] = 'address';
                $patientValues[] = $addressValue;
                $patientTypes .= 's';
            } elseif (dbColumnExists('patients', 'address_line1')) {
                $patientColumns[] = 'address_line1';
                $patientValues[] = $addressValue;
                $patientTypes .= 's';
            }
            if (dbColumnExists('patients', 'country')) {
                $patientColumns[] = 'country';
                $patientValues[] = 'LB';
                $patientTypes .= 's';
            }

            $patientColumns[] = 'user_id';
            $patientValues[] = (int) $userId;
            $patientTypes .= 'i';

            $patientColumns[] = 'created_by';
            $patientValues[] = Auth::userId();
            $patientTypes .= 'i';

            $patientColumns[] = 'sync_status';
            $patientValues[] = 'pending';
            $patientTypes .= 's';

            $placeholders = implode(', ', array_fill(0, count($patientColumns), '?'));
            $columnsSql = implode(', ', $patientColumns);

            $patientId = $db->insert(
                "INSERT INTO patients ({$columnsSql}) VALUES ({$placeholders})",
                $patientValues,
                $patientTypes
            );

            if (!$patientId) {
                throw new Exception('Database insert failed (patient)');
            }
            sync_push_row_now('patients', (int) $patientId);

            logAction('CREATE', 'patients', $patientId, null, $_POST);

            if ($dentalUploadPath) {
                $file = $_FILES['dental_history_image'];
                $fileName = basename((string) ($dentalUploadPath));
                $db->insert(
                    "INSERT INTO xrays (patient_id, file_name, file_path, file_size, mime_type, xray_type, tooth_numbers, findings, notes, uploaded_by)
                     VALUES (?, ?, ?, ?, ?, 'Other', ?, ?, ?, ?)",
                    [
                        $patientId,
                        $fileName,
                        $dentalUploadPath,
                        (int) ($file['size'] ?? 0),
                        (string) ($file['type'] ?? ''),
                        null,
                        null,
                        'Dental history (handwritten) uploaded during patient registration.',
                        Auth::userId(),
                    ],
                    "ississssi"
                );
            }

            $conn->commit();

            // Welcome WhatsApp (Node send.js on 127.0.0.1:3210); failure does not undo the patient record
            $basePublic = (defined('PUBLIC_SITE_URL') && trim((string) PUBLIC_SITE_URL) !== '')
                ? rtrim((string) PUBLIC_SITE_URL, '/')
                : (defined('SITE_URL') ? rtrim((string) SITE_URL, '/') : '');
            $loginUrl = $basePublic !== '' ? $basePublic . '/login.php' : '';
            $welcomeLines = [
                'DentAssist',
                '',
                'Welcome ' . $fullName . ', your patient account is ready!',
                'Username: ' . $username,

                '',
                'To access your patient account visit DentAssist.com',
                'Use Forgot Password to set a password of your choice.',

                
            ];
            
            $welcomeLines[] = '';
            
            $welcomeMsg = implode("\n", $welcomeLines);
            $wa = sendWhatsapp($phone, $welcomeMsg);

            // Always redirect after save — flash WhatsApp result so staff actually see it
            $_SESSION['patient_add_whatsapp_notice'] = [
                'ok' => (bool) ($wa['ok'] ?? false),
                'error' => $wa['ok'] ? null : (string) ($wa['error'] ?? 'Unknown error'),
            ];

            // Store generated credentials for display after redirect
            $_SESSION['generated_username'] = $username;
            $_SESSION['generated_password'] = $generatedPassword;

            // Redirect if "Save & Continue" was clicked
            if (isset($_POST['save_and_continue'])) {
                header("Location: view.php?id=$patientId");
                exit;
            }

            // Otherwise, reload to show success message and generated credentials
            header("Location: add.php");
            exit;

        } catch (Exception $e) {
            try {
                $conn->rollback();
            } catch (Exception $rollbackEx) {
                // ignore rollback errors
            }

            if (isset($dentalUploadPath) && $dentalUploadPath) {
                @unlink($dentalUploadPath);
            }
            $error = 'Error adding patient: ' . $e->getMessage();
        }
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Add New Patient</h1>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Patients
        </a>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if (is_array($whatsappNotice ?? null)): ?>
        <?php if (!empty($whatsappNotice['ok'])): ?>
            <div class="alert alert-success mb-3">
                Welcome message was sent to the patient's WhatsApp number.
            </div>
        <?php else: ?>
            <div class="alert alert-warning mb-3">
                <strong>WhatsApp not sent.</strong>
                <?php echo htmlspecialchars((string) ($whatsappNotice['error'] ?? 'Unknown error')); ?>
                <br><small>Ensure the WhatsApp bridge is running (<code>npm start</code> in the project folder), the QR code has been scanned, and the client shows "ready". PHP must reach <code>127.0.0.1:3210</code>.</small>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($generatedUsername && $generatedPassword): ?>
        <div class="alert alert-info">
            <strong>User account created!</strong><br>
            Username: <strong><?php echo htmlspecialchars($generatedUsername); ?></strong><br>
            Password: <strong><?php echo htmlspecialchars($generatedPassword); ?></strong><br>
            <small>Please save these credentials and provide them to the patient.</small>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12 col-xl-9 col-lg-10">
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

                <div class="tab-content">
                    <div class="tab-pane active" id="info" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date of Birth *</label>
                                <input type="date" class="form-control" name="date_of_birth" required>
                            </div>

                            <div class="col-md-3 mb-3">
                                <label class="form-label">Gender</label>
                                <select class="form-select" name="gender">
                                    <option value="">Select</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input
                                    type="tel"
                                    id="phone"
                                    class="form-control"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    placeholder="Enter phone number"
                                    required
                                >
                                <input type="hidden" name="phone_country" id="phone_country">
                                <input type="hidden" name="phone_number" id="phone_number">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required autocomplete="email">
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control" name="address" placeholder="Full address">
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
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Cardiovascular Diseases" id="mh_cardio">
                                            <label class="form-check-label" for="mh_cardio">Cardiovascular Diseases</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Hypertension" id="mh_htn">
                                            <label class="form-check-label" for="mh_htn">Hypertension</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Autoimmune diseases" id="mh_autoimmune">
                                            <label class="form-check-label" for="mh_autoimmune">Autoimmune diseases</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Immunosuppression" id="mh_immuno">
                                            <label class="form-check-label" for="mh_immuno">Immunosuppression</label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Diabetes" id="mh_diabetes">
                                            <label class="form-check-label" for="mh_diabetes">Diabetes</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Stroke history" id="mh_stroke">
                                            <label class="form-check-label" for="mh_stroke">Stroke history</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Osteoporosis" id="mh_osteo">
                                            <label class="form-check-label" for="mh_osteo">Osteoporosis</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Epilepsy" id="mh_epilepsy">
                                            <label class="form-check-label" for="mh_epilepsy">Epilepsy</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">Current Medications</label>
                               
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="medications[]" value="Anticoagulants" id="med_anticoag">
                                        <label class="form-check-label" for="med_anticoag">Anticoagulants</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="medications[]" value="Steroids" id="med_steroids">
                                        <label class="form-check-label" for="med_steroids">Steroids</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="medications[]" value="Chemotherapy" id="med_chemo">
                                        <label class="form-check-label" for="med_chemo">Chemotherapy</label>
                                    </div>
                               
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label d-block">Allergies</label>
                                <div class="d-flex gap-3 align-items-center">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allergies_yes" value="1" id="allergies_yes">
                                        <label class="form-check-label" for="allergies_yes">Yes</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="allergies_no" value="1" id="allergies_no" checked>
                                        <label class="form-check-label" for="allergies_no">No</label>
                                    </div>
                                </div>
                               
                            </div>

                            <div class="col-12">
                                <h5 class="mt-3">Emergency Contact</h5>
                                <hr>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" name="emergency_contact_name">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Emergency Contact Phone</label>
                                <input type="tel" class="form-control" name="emergency_contact_phone">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Relationship</label>
                                <input type="text" class="form-control" name="emergency_contact_relation">
                            </div>

                            <div class="col-12">
                                <h5 class="mt-3">Insurance</h5>
                                <hr>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance Provider</label>
                                <input type="text" class="form-control" name="insurance_provider">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance ID</label>
                                <input type="text" class="form-control" name="insurance_id">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Insurance Type</label>
                                <select class="form-select" name="insurance_type">
                                    <option value="None">None</option>
                                    <option value="Private">Private</option>
                                    <option value="Social Security">Social Security</option>
                                    <option value="Medicaid">Medicaid</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Coverage %</label>
                                <input type="number" class="form-control" name="insurance_coverage" min="0" max="100" value="0">
                            </div>


                            <div class="col-12 mb-3">
                                <label class="form-label">Additional Notes</label>
                                <textarea class="form-control" name="medical_additional_notes" rows="2" placeholder="Anything else the doctor should know..."></textarea>
                            </div>

                        </div>
                    </div>

                    <div class="tab-pane" id="dental" role="tabpanel">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Dental History</label>
                                <textarea class="form-control" name="dental_history" rows="3"></textarea>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Visit Date</label>
                                <input type="date" class="form-control" name="last_visit_date">
                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">Handwritten Dental History Image</label>
                                <input type="file" class="form-control" name="dental_history_image" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                <div class="form-text">Optional. Upload a photo/scan of handwritten notes for digitization.</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Checkbox for user account creation -->
                <input type="hidden" name="create_user" value="1">
                
                <hr class="mt-3">
                
                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" name="save_and_continue" class="btn btn-primary">
                        Save & Continue
                    </button>
                    <button type="submit" class="btn btn-success">
                        Save Patient
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
        </div>
    </div>
</div>
<?php include '../layouts/footer.php'; ?>

<script>
// keep allergies yes/no mutually exclusive (checkbox UI as requested)
document.getElementById('allergies_yes')?.addEventListener('change', function () {
    const noEl = document.getElementById('allergies_no');
    if (this.checked && noEl) noEl.checked = false;
});
document.getElementById('allergies_no')?.addEventListener('change', function () {
    const yesEl = document.getElementById('allergies_yes');
    if (this.checked && yesEl) yesEl.checked = false;
    if (!this.checked && yesEl && !yesEl.checked) this.checked = true;
});
</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css">
<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/intlTelInput.min.js"></script>
<script>
const phoneInput = document.querySelector("#phone");
if (phoneInput) {
  const iti = window.intlTelInput(phoneInput, {
    initialCountry: "lb",
    separateDialCode: true,
    preferredCountries: ["lb", "ae", "sa", "fr", "us"],
    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/js/utils.js"
  });

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

  // Initialize hidden fields immediately
  onPhoneChange();
}
</script>
