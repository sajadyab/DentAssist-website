<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $address = trim($_POST['address'] ?? '');

    $phone_country = $_POST['phone_country'] ?? null;
    $phone_number = $_POST['phone_number'] ?? null;

    $referral_code = strtoupper(trim($_POST['referral_code'] ?? ''));

    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
    $emergency_contact_relation = trim($_POST['emergency_contact_relation'] ?? '');

    $conditions = $_POST['medical_conditions'] ?? [];
    if (!is_array($conditions)) {
        $conditions = [];
    }
    $conditions = array_values(array_unique(array_filter(array_map('strval', $conditions))));
    $medical_additional_notes = trim((string) ($_POST['medical_additional_notes'] ?? ''));
    $allergies = trim((string) ($_POST['allergies'] ?? ''));
    $current_medications = trim((string) ($_POST['current_medications'] ?? ''));

    if (!function_exists('normalizeE164Phone')) {
        function normalizeE164Phone(?string $countryCode, ?string $localNumber): array
        {
            $code = preg_replace('/[^0-9]/', '', (string) $countryCode);
            $number = preg_replace('/[^0-9]/', '', (string) $localNumber);
            if ($code === '' || $number === '') {
                return ['ok' => false, 'value' => null, 'error' => 'Phone number is required.'];
            }
            $digitRules = [
                '961' => [8],
                '971' => [9],
                '966' => [9],
                '33' => [9],
                '1' => [10],
                '44' => [10],
                '49' => [10, 11],
            ];
            if (isset($digitRules[$code]) && !in_array(strlen($number), $digitRules[$code], true)) {
                $hint = implode(' or ', array_map('strval', $digitRules[$code]));
                return ['ok' => false, 'value' => null, 'error' => "Invalid phone digits for +{$code}. Expected {$hint} digits."];
            }
            return ['ok' => true, 'value' => '+' . $code . $number, 'error' => null];
        }
    }

    $phoneParsed = normalizeE164Phone($phone_country, $phone_number);

    if (!$full_name || !$username || !$date_of_birth || !$password || !$password_confirm) {
        $error = 'Please fill in all required fields.';
    } elseif (!$phoneParsed['ok']) {
        $error = (string) $phoneParsed['error'];
    } elseif ($password !== $password_confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = Database::getInstance();
        $conn = $db->getConnection();
        // check username uniqueness
        if ($db->fetchOne("SELECT id FROM users WHERE username = ?", [$username], "s")) {
            $error = 'Username already taken.';
        } else {
            $phone = (string) $phoneParsed['value'];
            $passwordHash = Auth::hashPassword($password);

            // users.email is UNIQUE + NOT NULL; store patient email in patients.email
            $usersEmail = $username . '@patients.local';

            $referredBy = null;
            if ($referral_code !== '') {
                $refRow = $db->fetchOne("SELECT id FROM patients WHERE referral_code = ? LIMIT 1", [$referral_code], "s");
                if (!$refRow) {
                    $error = 'Referral code not found.';
                } else {
                    $referredBy = (int) $refRow['id'];
                }
            }

            if ($error === '') {
                $conn->begin_transaction();
                try {
                    $userId = $db->insert(
                        "INSERT INTO users (username, email, password_hash, full_name, role, phone, is_active)
                         VALUES (?, ?, ?, ?, 'patient', ?, 1)",
                        [$username, $usersEmail, $passwordHash, $full_name, $phone],
                        "sssss"
                    );
                    if (!$userId) {
                        throw new Exception('Error creating account. Please try again later.');
                    }

                    $medicalHistoryPayload = json_encode([
                        'conditions' => $conditions,
                        'notes' => $medical_additional_notes,
                    ], JSON_UNESCAPED_UNICODE);
                    if ($medicalHistoryPayload === false) {
                        $medicalHistoryPayload = null;
                    }

                    $patientId = $db->insert(
                        "INSERT INTO patients (
                            user_id, full_name, date_of_birth, gender, phone, email,
                            emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                            insurance_provider, insurance_id, insurance_type, insurance_coverage,
                            medical_history, allergies, current_medications,
                            address, country,
                            referred_by, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            (int) $userId,
                            $full_name,
                            $date_of_birth,
                            $gender !== '' ? $gender : null,
                            $phone,
                            $email !== '' ? $email : null,
                            $emergency_contact_name !== '' ? $emergency_contact_name : null,
                            $emergency_contact_phone !== '' ? $emergency_contact_phone : null,
                            $emergency_contact_relation !== '' ? $emergency_contact_relation : null,
                            null,
                            null,
                            'None',
                            0,
                            $medicalHistoryPayload,
                            $allergies !== '' ? $allergies : null,
                            $current_medications !== '' ? $current_medications : null,
                            $address !== '' ? $address : null,
                            'LB',
                            $referredBy,
                            (int) $userId,
                        ],
                        "isssssssssssisssssii"
                    );
                    if (!$patientId) {
                        throw new Exception('Error creating patient record.');
                    }

                    if ($referredBy !== null) {
                        $db->execute("UPDATE patients SET points = COALESCE(points,0) + 50 WHERE id = ? LIMIT 1", [$referredBy], "i");
                    }

                    $conn->commit();
                    Auth::login($username, $password);
                    header('Location: patient/index.php');
                    exit;
                } catch (Exception $e) {
                    try { $conn->rollback(); } catch (Exception $ex) {}
                    $error = $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Patient Registration';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@18/build/css/intlTelInput.css">

    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 24px 12px;
        }

        .auth-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 32px;
            width: 100%;
            max-width: 690px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 18px;
        }

        .auth-header h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 6px;
        }

        .auth-header p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            padding: 12px;
            width: 100%;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            color: white;
        }

        .required-badge {
            color: #dc3545 !important;
            font-weight: 500;
            font-size: 12px;
            margin-left: 8px;
        }

        /* Invalid required fields after failed submit */
        .field-invalid-required {
            background-color: rgba(220, 53, 69, 0.18) !important;
            border-color: #dc3545 !important;
            box-shadow: none;
        }

        .iti.field-invalid-wrap {
            width: 100%;
            background-color: rgba(220, 53, 69, 0.18);
            border: 1px solid #dc3545;
            border-radius: 0.375rem;
        }

        .iti.field-invalid-wrap .iti__selected-flag {
            background-color: rgba(220, 53, 69, 0.12) !important;
        }

        .iti { width: 100%; }
        .iti input.form-control { width: 100%; }
        .iti--separate-dial-code .iti__selected-flag { background-color: #f8f9fa; border-right: 1px solid #dee2e6; }
        .iti__country-list { z-index: 2000; }

        @media (max-width: 768px) {
            body {
                padding: 18px 10px;
                align-items: center;
            }

            .auth-card {
                padding: 22px 18px;
                max-width: 360px;
                border-radius: 12px;
            }

            .auth-header h1 {
                font-size: 20px;
            }

            .auth-header p {
                font-size: 12px;
            }

            .form-label {
                font-size: 13px;
            }

            .form-control, .form-select {
                padding: 10px 12px;
                font-size: 14px;
            }

            .btn-register {
                padding: 10px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-header">
            <h1> DentAssist Smart Dental Clinic</h1>
        
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="register-form" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Date of Birth *</label>
                    <input type="date" name="date_of_birth" class="form-control" required value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Gender</label>
                    <select class="form-select" name="gender">
                        <option value="">Select</option>
                        <option value="male" <?php echo (($_POST['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>Male</option>
                        <option value="female" <?php echo (($_POST['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>Female</option>
                        <option value="other" <?php echo (($_POST['gender'] ?? '') === 'other') ? 'selected' : ''; ?>>Other</option>
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
                        value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>"
                        required
                    >
                    <input type="hidden" name="phone_country" id="phone_country" value="<?php echo htmlspecialchars($_POST['phone_country'] ?? ''); ?>">
                    <input type="hidden" name="phone_number" id="phone_number" value="<?php echo htmlspecialchars($_POST['phone_number'] ?? ''); ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                </div>
            </div>

            <hr>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Username *</label>
                    <input type="text" name="username" class="form-control" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Referral Code <small class="text-muted">(optional)</small></label>
                    <input type="text" name="referral_code" class="form-control" value="<?php echo htmlspecialchars($_POST['referral_code'] ?? ''); ?>">
                    <div class="form-text">Enter a friend’s code so they can earn points.</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control" required>
                </div>
            </div>

            <hr>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" class="form-control" value="<?php echo htmlspecialchars($_POST['emergency_contact_phone'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Emergency Contact Relation</label>
                    <input type="text" name="emergency_contact_relation" class="form-control" value="<?php echo htmlspecialchars($_POST['emergency_contact_relation'] ?? ''); ?>">
                </div>
            </div>

            <hr>
            <div class="row">
                <div class="col-12 mb-2">
                    <label class="form-label">Medical History</label>
                    <div class="row">
                        <div class="col-sm-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Cardiovascular Diseases" id="mh_cardio"
                                    <?php echo in_array('Cardiovascular Diseases', (array)($_POST['medical_conditions'] ?? []), true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mh_cardio">Cardiovascular Diseases</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Hypertension" id="mh_htn"
                                    <?php echo in_array('Hypertension', (array)($_POST['medical_conditions'] ?? []), true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mh_htn">Hypertension</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Autoimmune diseases" id="mh_autoimmune"
                                    <?php echo in_array('Autoimmune diseases', (array)($_POST['medical_conditions'] ?? []), true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mh_autoimmune">Autoimmune diseases</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Immunosuppression" id="mh_immuno"
                                    <?php echo in_array('Immunosuppression', (array)($_POST['medical_conditions'] ?? []), true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mh_immuno">Immunosuppression</label>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Diabetes" id="mh_diabetes"
                                    <?php echo in_array('Diabetes', (array)($_POST['medical_conditions'] ?? []), true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mh_diabetes">Diabetes</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Stroke history" id="mh_stroke"
                                    <?php echo in_array('Stroke history', (array)($_POST['medical_conditions'] ?? []), true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mh_stroke">Stroke history</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Osteoporosis" id="mh_osteo"
                                    <?php echo in_array('Osteoporosis', (array)($_POST['medical_conditions'] ?? []), true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mh_osteo">Osteoporosis</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="medical_conditions[]" value="Epilepsy" id="mh_epilepsy"
                                    <?php echo in_array('Epilepsy', (array)($_POST['medical_conditions'] ?? []), true) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mh_epilepsy">Epilepsy</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Allergies</label>
                    <textarea class="form-control" name="allergies" rows="2"><?php echo htmlspecialchars($_POST['allergies'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Current Medications</label>
                    <textarea class="form-control" name="current_medications" rows="2"><?php echo htmlspecialchars($_POST['current_medications'] ?? ''); ?></textarea>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Additional Notes</label>
                    <textarea class="form-control" name="medical_additional_notes" rows="2"><?php echo htmlspecialchars($_POST['medical_additional_notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn-register">Register</button>
        </form>

            <div class="text-center mt-3">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
    </div>

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
  const phoneLimits = { lb: 8, ae: 9, sa: 9, fr: 9, us: 10, ca: 10, gb: 10, de: 11, it: 10, es: 9 };
  function enforceLimit() {
    phoneInput.value = phoneInput.value.replace(/[^0-9]/g, "");
    const country = iti.getSelectedCountryData().iso2;
    const max = phoneLimits[country] || 15;
    if (phoneInput.value.length > max) phoneInput.value = phoneInput.value.slice(0, max);
    phoneInput.maxLength = max;
  }
  function syncHidden() {
    const data = iti.getSelectedCountryData();
    const dial = String((data && data.dialCode) ? data.dialCode : "");
    document.querySelector("#phone_country").value = dial;
    document.querySelector("#phone_number").value = phoneInput.value;
  }
  function onChange() { enforceLimit(); syncHidden(); }
  phoneInput.addEventListener("input", onChange);
  phoneInput.addEventListener("countrychange", onChange);
  const form = phoneInput.closest("form");
  if (form) form.addEventListener("submit", onChange);
  onChange();
}
</script>

<script>
    (function () {
        const form = document.querySelector('form.register-form');
        if (!form) return;

        function clearValidationUi() {
            form.querySelectorAll('.required-badge').forEach(function (el) { el.remove(); });
            form.querySelectorAll('.field-invalid-required').forEach(function (el) {
                el.classList.remove('field-invalid-required');
            });
            form.querySelectorAll('.iti.field-invalid-wrap').forEach(function (el) {
                el.classList.remove('field-invalid-wrap');
            });
        }

        function markInvalidField(field) {
            if (!field || field.type === 'hidden' || field.type === 'submit') return;
            const wrapper = field.closest('.mb-3') || field.closest('.col-md-6') || field.parentElement;
            if (!wrapper) return;
            const label = wrapper.querySelector('label.form-label') || wrapper.querySelector('label:not(.form-check-label)');
            if (label && !label.querySelector('.required-badge')) {
                const badge = document.createElement('span');
                badge.className = 'required-badge';
                badge.setAttribute('aria-live', 'polite');
                badge.textContent = 'Required';
                label.appendChild(badge);
            }
            field.classList.add('field-invalid-required');
            const iti = field.closest('.iti');
            if (iti) iti.classList.add('field-invalid-wrap');
        }

        form.addEventListener('submit', function (e) {
            clearValidationUi();

            if (form.checkValidity()) {
                return;
            }

            e.preventDefault();

            const invalidFields = Array.from(form.querySelectorAll(':invalid'));
            invalidFields.forEach(markInvalidField);

            const first = invalidFields[0];
            if (first && typeof first.focus === 'function') {
                first.focus({ preventScroll: true });
                first.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        });

        form.addEventListener('input', function (e) {
            const t = e.target;
            if (!t || !t.classList || !t.classList.contains('field-invalid-required')) return;
            if (typeof t.checkValidity === 'function' && t.checkValidity()) {
                t.classList.remove('field-invalid-required');
                const iti = t.closest('.iti');
                if (iti) iti.classList.remove('field-invalid-wrap');
                const wrapper = t.closest('.mb-3') || t.closest('.col-md-6');
                const label = wrapper && (wrapper.querySelector('label.form-label') || wrapper.querySelector('label:not(.form-check-label)'));
                const badge = label && label.querySelector('.required-badge');
                if (badge) badge.remove();
            }
        }, true);

        form.addEventListener('change', function (e) {
            const t = e.target;
            if (!t || !t.classList || !t.classList.contains('field-invalid-required')) return;
            if (typeof t.checkValidity === 'function' && t.checkValidity()) {
                t.classList.remove('field-invalid-required');
                const iti = t.closest('.iti');
                if (iti) iti.classList.remove('field-invalid-wrap');
                const wrapper = t.closest('.mb-3') || t.closest('.col-md-6');
                const label = wrapper && (wrapper.querySelector('label.form-label') || wrapper.querySelector('label:not(.form-check-label)'));
                const badge = label && label.querySelector('.required-badge');
                if (badge) badge.remove();
            }
        }, true);
    })();
</script>
</body>
</html>
