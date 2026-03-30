<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Generate a random password for new user accounts
function generateRandomPassword($length = 8) {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, $length);
}

Auth::requireLogin();
$pageTitle = 'Add Patient';

$db = Database::getInstance();
$error = '';
$success = '';

// Check for generated credentials in session (for display after redirect)
$generatedUsername = $_SESSION['generated_username'] ?? null;
$generatedPassword = $_SESSION['generated_password'] ?? null;
unset($_SESSION['generated_username'], $_SESSION['generated_password']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate required fields
    $required = ['full_name', 'phone', 'email'];
    $missing = [];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $missing[] = $field;
        }
    }
    if (!empty($missing)) {
        $error = 'Please fill in all required fields: ' . implode(', ', $missing);
    }

    if (empty($error)) {
        // Start transaction
        $db->beginTransaction();

        try {
            // Insert patient
            $patientId = $db->insert(
                "INSERT INTO patients (
                    full_name, date_of_birth, gender, phone, email,
                    emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
                    insurance_provider, insurance_id, insurance_type, insurance_coverage,
                    medical_history, allergies, current_medications, past_surgeries,
                    chronic_conditions, dental_history, previous_dentist, last_visit_date,
                    address_line1, address_line2, city, state, postal_code, country,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $_POST['full_name'],
                    $_POST['date_of_birth'] ?? null,
                    $_POST['gender'] ?? null,
                    $_POST['phone'],
                    $_POST['email'],
                    $_POST['emergency_contact_name'] ?? null,
                    $_POST['emergency_contact_phone'] ?? null,
                    $_POST['emergency_contact_relation'] ?? null,
                    $_POST['insurance_provider'] ?? null,
                    $_POST['insurance_id'] ?? null,
                    $_POST['insurance_type'] ?? 'None',
                    $_POST['insurance_coverage'] ?? 0,
                    $_POST['medical_history'] ?? null,
                    $_POST['allergies'] ?? null,
                    $_POST['current_medications'] ?? null,
                    $_POST['past_surgeries'] ?? null,
                    $_POST['chronic_conditions'] ?? null,
                    $_POST['dental_history'] ?? null,
                    $_POST['previous_dentist'] ?? null,
                    $_POST['last_visit_date'] ?? null,
                    $_POST['address_line1'] ?? null,
                    $_POST['address_line2'] ?? null,
                    $_POST['city'] ?? null,
                    $_POST['state'] ?? null,
                    $_POST['postal_code'] ?? null,
                    $_POST['country'] ?? 'USA',
                    Auth::userId()
                ],
                "ssssssssssiissssssssssssssi"
            );

            if (!$patientId) {
                throw new Exception('Database insert failed (patient)');
            }

            logAction('CREATE', 'patients', $patientId, null, $_POST);

            // Create user account if requested
            $userCreated = false;
            $generatedUsername = '';
            $generatedPassword = '';

            if (isset($_POST['create_user']) && $_POST['create_user'] == '1') {
                // Generate base username from email (part before @)
                $emailParts = explode('@', $_POST['email']);
                $baseUsername = strtolower(preg_replace('/[^a-z0-9]/i', '', $emailParts[0]));
                $username = $baseUsername;
                $counter = 1;
                while ($db->fetchOne("SELECT id FROM users WHERE username = ?", [$username], "s")) {
                    $username = $baseUsername . $counter;
                    $counter++;
                }

                $generatedPassword = generateRandomPassword();
                $passwordHash = Auth::hashPassword($generatedPassword);

                $userId = $db->insert(
                    "INSERT INTO users (username, email, password_hash, full_name, role, phone, is_active)
                     VALUES (?, ?, ?, ?, 'patient', ?, 1)",
                    [
                        $username,
                        $_POST['email'],
                        $passwordHash,
                        $_POST['full_name'],
                        $_POST['phone'] ?? null
                    ],
                    "ssssss"
                );

                if ($userId) {
                    // Link patient to this user
                    $db->execute("UPDATE patients SET user_id = ? WHERE id = ?", [$userId, $patientId], "ii");
                    $userCreated = true;
                    $generatedUsername = $username;
                } else {
                    // User creation failed, but patient is already saved.
                    // Log error but continue without rolling back patient.
                    error_log("Failed to create user account for patient ID $patientId");
                }
            }

            $db->commit();

            $success = 'Patient added successfully.';

            // Store generated credentials if user was created
            if ($userCreated) {
                $_SESSION['generated_username'] = $generatedUsername;
                $_SESSION['generated_password'] = $generatedPassword;
            }

            // Redirect if "Save & Continue" was clicked
            if (isset($_POST['save_and_continue'])) {
                header("Location: view.php?id=$patientId");
                exit;
            }

            // Otherwise, reload to show success message and generated credentials
            header("Location: add.php");
            exit;

        } catch (Exception $e) {
            $db->rollback();
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
    
    <?php if ($generatedUsername && $generatedPassword): ?>
        <div class="alert alert-info">
            <strong>User account created!</strong><br>
            Username: <strong><?php echo htmlspecialchars($generatedUsername); ?></strong><br>
            Password: <strong><?php echo htmlspecialchars($generatedPassword); ?></strong><br>
            <small>Please save these credentials and provide them to the patient.</small>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form method="POST" action="">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" 
                                data-bs-target="#personal" type="button" role="tab">
                            Personal Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="contact-tab" data-bs-toggle="tab" 
                                data-bs-target="#contact" type="button" role="tab">
                            Contact & Emergency
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="insurance-tab" data-bs-toggle="tab" 
                                data-bs-target="#insurance" type="button" role="tab">
                            Insurance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="medical-tab" data-bs-toggle="tab" 
                                data-bs-target="#medical" type="button" role="tab">
                            Medical History
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
                    <!-- Personal Information -->
                    <div class="tab-pane active" id="personal" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth">
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
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="tab-pane" id="contact" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="tel" class="form-control" name="phone" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" name="address_line1">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" name="address_line2">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" value="USA">
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
                        </div>
                    </div>
                    
                    <!-- Insurance Information -->
                    <div class="tab-pane" id="insurance" role="tabpanel">
                        <div class="row">
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
                        </div>
                    </div>
                    
                    <!-- Medical History -->
                    <div class="tab-pane" id="medical" role="tabpanel">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Medical History</label>
                                <textarea class="form-control" name="medical_history" rows="3"></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Allergies</label>
                                <textarea class="form-control" name="allergies" rows="3"></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Current Medications</label>
                                <textarea class="form-control" name="current_medications" rows="3"></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Past Surgeries</label>
                                <textarea class="form-control" name="past_surgeries" rows="3"></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Chronic Conditions</label>
                                <textarea class="form-control" name="chronic_conditions" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dental History -->
                    <div class="tab-pane" id="dental" role="tabpanel">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Dental History</label>
                                <textarea class="form-control" name="dental_history" rows="3"></textarea>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Previous Dentist</label>
                                <input type="text" class="form-control" name="previous_dentist">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Visit Date</label>
                                <input type="date" class="form-control" name="last_visit_date">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Checkbox for user account creation -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="create_user" value="1" id="createUser">
                            <label class="form-check-label" for="createUser">
                                Create user account for patient (they can log in to patient portal)
                            </label>
                        </div>
                    </div>
                </div>
                
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
<script>document.querySelector('form').addEventListener('submit', function(e) {
    alert('Form submitted!');
    // If you want to see the POST data, you can log it
    console.log('Form data:', new FormData(this));
});</script>
<?php include '../layouts/footer.php'; ?>