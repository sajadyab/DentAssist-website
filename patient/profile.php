<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
if ($_SESSION['role'] !== 'patient') {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$userId = Auth::userId();
$patientId = getPatientIdFromUserId($userId);
if (!$patientId) {
    die("Patient record not found. Please contact support.");
}

// Get patient and user details
$patient = $db->fetchOne("SELECT * FROM patients WHERE id = ?", [$patientId], "i");
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId], "i");

$pageTitle = 'My Profile';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update patient record
    $result = $db->execute(
        "UPDATE patients SET 
            full_name = ?, date_of_birth = ?, gender = ?, phone = ?, email = ?,
            emergency_contact_name = ?, emergency_contact_phone = ?, emergency_contact_relation = ?,
            address = ?, country = ?
         WHERE id = ?",
        [
            $_POST['full_name'],
            $_POST['date_of_birth'] ?? null,
            $_POST['gender'] ?? null,
            $_POST['phone'],
            (trim((string) ($_POST['email'] ?? '')) !== '' ? trim((string) $_POST['email']) : null),
            $_POST['emergency_contact_name'] ?? null,
            $_POST['emergency_contact_phone'] ?? null,
            $_POST['emergency_contact_relation'] ?? null,
            $_POST['address'] ?? null,
            $_POST['country'] ?? 'LB',
            $patientId
        ],
        "sssssssssssi"
    );

    // Update user table if name/email/phone changed
    $db->execute(
        "UPDATE users SET full_name = ?, phone = ? WHERE id = ?",
        [
            $_POST['full_name'],
            $_POST['phone'],
            $userId
        ],
        "ssi"
    );

    if ($result !== false) {
        logAction('UPDATE', 'patients', $patientId, $patient, $_POST);
        $success = 'Profile updated successfully.';
        // refresh values
        $patient = $db->fetchOne("SELECT * FROM patients WHERE id = ?", [$patientId], "i");
        $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId], "i");
        $_SESSION['full_name'] = $user['full_name'];
    } else {
        $error = 'Error updating profile';
    }
}

include '../layouts/header.php';
?>

<style>
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    font-weight: bold;
    margin: 0 auto 15px;
}

.profile-section {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.profile-section:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #667eea;
    display: inline-block;
}

.section-icon {
    color: #667eea;
    margin-right: 10px;
}

.form-control-modern {
    border-radius: 10px;
    border: 1px solid #e0e0e0;
    padding: 12px 15px;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
}

.form-label-modern {
    font-weight: 500;
    margin-bottom: 8px;
    color: #2c3e50;
}

.info-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
}

.info-card i {
    font-size: 24px;
    color: #667eea;
    margin-right: 15px;
}

.info-card .info-label {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 5px;
}

.info-card .info-value {
    font-size: 16px;
    font-weight: 500;
    color: #2c3e50;
}

.btn-save {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102,126,234,0.4);
}

.btn-cancel {
    background: #6c757d;
    border: none;
    padding: 12px 30px;
    border-radius: 25px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.btn-cancel:hover {
    background: #5a6268;
    transform: translateY(-2px);
}

.alert-custom {
    border-radius: 12px;
    border: none;
    padding: 15px 20px;
}

.profile-badge {
    background: rgba(255,255,255,0.2);
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    display: inline-block;
    margin-top: 10px;
}
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Profile Header -->
    <div class="profile-header">
        <div class="row align-items-center">
            <div class="col-md-2 text-center">
                <div class="profile-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
            </div>
            <div class="col-md-7">
                <h2 class="mb-2"><?php echo htmlspecialchars($patient['full_name']); ?></h2>
                <p class="mb-2">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?> &nbsp;&nbsp;
                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?>
                </p>
                <p class="mb-0">
                    <i class="fas fa-calendar-alt"></i> Member since <?php echo formatDate($patient['created_at'], 'M d, Y'); ?>
                </p>
                <?php if ($patient['subscription_type'] != 'none'): ?>
                    <div class="profile-badge">
                        <i class="fas fa-crown"></i> <?php echo ucfirst($patient['subscription_type']); ?> Member
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-3 text-md-end mt-3 mt-md-0">
                <div class="info-card text-center">
                    <div class="stats-number"><?php echo $patient['points'] ?? 0; ?></div>
                    <div class="stats-label">Reward Points</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <form method="POST" action="">
                <!-- Personal Information Section -->
                <div class="profile-section">
                    <h5 class="section-title">
                        <i class="fas fa-user section-icon"></i> Personal Information
                    </h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-modern">Full Name *</label>
                            <input type="text" class="form-control form-control-modern" name="full_name" 
                                   value="<?php echo htmlspecialchars($patient['full_name']); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label-modern">Date of Birth</label>
                            <input type="date" class="form-control form-control-modern" name="date_of_birth" 
                                   value="<?php echo $patient['date_of_birth']; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label-modern">Gender</label>
                            <select class="form-select form-control-modern" name="gender">
                                <option value="">Select</option>
                                <option value="male" <?php echo $patient['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $patient['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $patient['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contact Information Section -->
                <div class="profile-section">
                    <h5 class="section-title">
                        <i class="fas fa-address-card section-icon"></i> Contact Information
                    </h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label-modern">Phone Number *</label>
                            <input type="tel" class="form-control form-control-modern" name="phone" 
                                   value="<?php echo htmlspecialchars($patient['phone']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label-modern">Email Address *</label>
                            <input type="email" class="form-control form-control-modern" name="email" 
                                   value="<?php echo htmlspecialchars($patient['email']); ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact Section -->
                <div class="profile-section">
                    <h5 class="section-title">
                        <i class="fas fa-ambulance section-icon"></i> Emergency Contact
                    </h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label-modern">Contact Name</label>
                            <input type="text" class="form-control form-control-modern" name="emergency_contact_name" 
                                   value="<?php echo htmlspecialchars($patient['emergency_contact_name'] ?? ''); ?>"
                                   placeholder="Full name">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-modern">Contact Phone</label>
                            <input type="tel" class="form-control form-control-modern" name="emergency_contact_phone" 
                                   value="<?php echo htmlspecialchars($patient['emergency_contact_phone'] ?? ''); ?>"
                                   placeholder="Phone number">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label-modern">Relationship</label>
                            <input type="text" class="form-control form-control-modern" name="emergency_contact_relation" 
                                   value="<?php echo htmlspecialchars($patient['emergency_contact_relation'] ?? ''); ?>"
                                   placeholder="e.g., Spouse, Parent, Sibling">
                        </div>
                    </div>
                </div>

                <!-- Address Section -->
                <div class="profile-section">
                    <h5 class="section-title">
                        <i class="fas fa-home section-icon"></i> Address Information
                    </h5>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label-modern">Address</label>
                            <input type="text" class="form-control form-control-modern" name="address"
                                   value="<?php echo htmlspecialchars($patient['address'] ?? ''); ?>"
                                   placeholder="Full address">
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="d-flex gap-3 mb-4">
                    <button type="submit" class="btn btn-save text-white">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="index.php" class="btn btn-cancel text-white">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>

        <div class="col-md-4">
            <!-- Quick Info Card -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-info-circle section-icon"></i> Quick Info
                </h5>
                
                <div class="info-card">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-id-card"></i>
                        <div class="ms-3">
                            <div class="info-label">Patient ID</div>
                            <div class="info-value">#<?php echo str_pad($patientId, 6, '0', STR_PAD_LEFT); ?></div>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-star"></i>
                        <div class="ms-3">
                            <div class="info-label">Reward Points</div>
                            <div class="info-value"><?php echo $patient['points'] ?? 0; ?> points</div>
                            <small class="text-muted">Next reward at <?php echo 250 - (($patient['points'] ?? 0) % 250); ?> points</small>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-calendar-check"></i>
                        <div class="ms-3">
                            <div class="info-label">Account Created</div>
                            <div class="info-value"><?php echo formatDate($patient['created_at'], 'M d, Y'); ?></div>
                        </div>
                    </div>
                </div>

                <?php if (patientHasLastVisitDate($patient['last_visit_date'] ?? null)): ?>
                <div class="info-card">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clock"></i>
                        <div class="ms-3">
                            <div class="info-label">Last Visit</div>
                            <div class="info-value"><?php echo htmlspecialchars(formatDate(normalizePatientOptionalDate($patient['last_visit_date'] ?? null))); ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Subscription Info Card -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-crown section-icon"></i> Subscription
                </h5>
                <?php if ($patient['subscription_type'] != 'none'): ?>
                    <div class="text-center mb-3">
                        <div class="badge bg-success fs-6 p-2">
                            <i class="fas fa-check-circle"></i> Active Plan
                        </div>
                        <h4 class="mt-3"><?php echo ucfirst($patient['subscription_type']); ?></h4>
                        <p class="text-muted small">
                            Valid until <?php echo formatDate($patient['subscription_end_date']); ?>
                        </p>
                    </div>
                    <a href="subscription.php" class="btn btn-outline-primary w-100">
                        <i class="fas fa-arrow-right"></i> Manage Subscription
                    </a>
                <?php else: ?>
                    <p class="text-muted text-center mb-3">No active subscription</p>
                    <a href="subscription.php" class="btn btn-primary w-100">
                        <i class="fas fa-gem"></i> Subscribe Now
                    </a>
                <?php endif; ?>
            </div>

            <!-- Referral Info Card -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-share-alt section-icon"></i> Referral Program
                </h5>
                <div class="text-center">
                    <div class="bg-light rounded p-3 mb-3">
                        <p class="mb-1">Your Referral Code</p>
                        <h4 class="mb-0 text-primary"><?php echo $patient['referral_code']; ?></h4>
                    </div>
                    <button class="btn btn-outline-success w-100 mb-2" onclick="copyReferralCode()">
                        <i class="fas fa-copy"></i> Copy Code
                    </button>
                    <small class="text-muted">
                        <i class="fas fa-gift"></i> Share your code and earn 50 points per referral!
                    </small>
                </div>
            </div>

            <!-- Support Card -->
            <div class="profile-section">
                <h5 class="section-title">
                    <i class="fas fa-headset section-icon"></i> Need Help?
                </h5>
                <div class="text-center">
                    <p class="mb-2">Contact our support team</p>
                    <a href="tel:+1234567890" class="btn btn-outline-info w-100 mb-2">
                        <i class="fas fa-phone"></i> Call Us
                    </a>
                    <a href="mailto:support@dentalclinic.com" class="btn btn-outline-info w-100">
                        <i class="fas fa-envelope"></i> Email Support
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyReferralCode() {
    const code = '<?php echo $patient['referral_code']; ?>';
    navigator.clipboard.writeText(code).then(function() {
        alert('Referral code copied to clipboard!');
    });
}
</script>

<?php include '../layouts/footer.php'; ?>