<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
            $patientId
        ],
        "ssssssssssiissssssssssssssi"
    );
    
    if ($result !== false) {
        logAction('UPDATE', 'patients', $patientId, $patient, $_POST);
        $success = 'Patient updated successfully';
        
        // Refresh patient data
        $patient = $db->fetchOne("SELECT * FROM patients WHERE id = ?", [$patientId], "i");
    } else {
        $error = 'Error updating patient';
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
            <form method="POST" action="">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" 
                                data-bs-target="#personal" type="button" role="tab">
                            Personal Information
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="contact-tab" data-bs-toggle="tab" 
                                data-bs-target="#contact" type="button" role="tab">
                            Contact & Emergency
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="insurance-tab" data-bs-toggle="tab" 
                                data-bs-target="#insurance" type="button" role="tab">
                            Insurance
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" id="medical-tab" data-bs-toggle="tab" 
                                data-bs-target="#medical" type="button" role="tab">
                            Medical History
                        </button>
                    </li>
                    <li class="nav-item">
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
                                <input type="text" class="form-control" name="full_name" 
                                       value="<?php echo htmlspecialchars($patient['full_name']); ?>" required>
                            </div>
                            
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth" 
                                       value="<?php echo $patient['date_of_birth']; ?>">
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
                        </div>
                    </div>
                    
                    <!-- Contact Information -->
                    <div class="tab-pane" id="contact" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone *</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" name="address_line1" 
                                       value="<?php echo htmlspecialchars($patient['address_line1'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" name="address_line2" 
                                       value="<?php echo htmlspecialchars($patient['address_line2'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" 
                                       value="<?php echo htmlspecialchars($patient['city'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state" 
                                       value="<?php echo htmlspecialchars($patient['state'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Postal Code</label>
                                <input type="text" class="form-control" name="postal_code" 
                                       value="<?php echo htmlspecialchars($patient['postal_code'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" 
                                       value="<?php echo htmlspecialchars($patient['country'] ?? 'USA'); ?>">
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
                        </div>
                    </div>
                    
                    <!-- Insurance Information -->
                    <div class="tab-pane" id="insurance" role="tabpanel">
                        <div class="row">
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
                        </div>
                    </div>
                    
                    <!-- Medical History -->
                    <div class="tab-pane" id="medical" role="tabpanel">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Medical History</label>
                                <textarea class="form-control" name="medical_history" rows="3"><?php echo htmlspecialchars($patient['medical_history'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Allergies</label>
                                <textarea class="form-control" name="allergies" rows="3"><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Current Medications</label>
                                <textarea class="form-control" name="current_medications" rows="3"><?php echo htmlspecialchars($patient['current_medications'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Past Surgeries</label>
                                <textarea class="form-control" name="past_surgeries" rows="3"><?php echo htmlspecialchars($patient['past_surgeries'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="col-12 mb-3">
                                <label class="form-label">Chronic Conditions</label>
                                <textarea class="form-control" name="chronic_conditions" rows="3"><?php echo htmlspecialchars($patient['chronic_conditions'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dental History -->
                    <div class="tab-pane" id="dental" role="tabpanel">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Dental History</label>
                                <textarea class="form-control" name="dental_history" rows="3"><?php echo htmlspecialchars($patient['dental_history'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Previous Dentist</label>
                                <input type="text" class="form-control" name="previous_dentist" 
                                       value="<?php echo htmlspecialchars($patient['previous_dentist'] ?? ''); ?>">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Visit Date</label>
                                <input type="date" class="form-control" name="last_visit_date" 
                                       value="<?php echo $patient['last_visit_date']; ?>">
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