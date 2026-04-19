<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/patient_cloud_repository.php';

/**
 * Short labels for profile UI: no underscores, leading letter uppercased, and "password"/"passwords" get a capital P.
 */
if (!function_exists('profile_pretty_label')) {
    function profile_pretty_label(string $key, string $fallback = ''): string {
        $s = trim(__($key, $fallback !== '' ? $fallback : $key));
        $s = str_replace('_', ' ', $s);
        $s = str_ireplace('passwords', 'Passwords', $s);
        $s = str_ireplace('password', 'Password', $s);
        if ($s === '') {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8')) . mb_substr($s, 1, null, 'UTF-8');
        }
        return ucfirst($s);
    }
}

Auth::requireLogin();
if ($_SESSION['role'] !== 'patient') {
    header('Location: ../dashboard.php');
    exit;
}

// Ensure helper functions exist (in case they are not yet in functions.php)
if (!function_exists('canViewPoints')) {
    function canViewPoints() {
        global $db;
        static $cached = null;
        if ($cached === null) {
            $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_points_view'");
            $cached = ($result && $result['setting_value'] == '1');
        }
        return $cached;
    }
}
if (!function_exists('canViewReferrals')) {
    function canViewReferrals() {
        global $db;
        static $cached = null;
        if ($cached === null) {
            $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_referrals_view'");
            $cached = ($result && $result['setting_value'] == '1');
        }
        return $cached;
    }
}
if (!function_exists('canViewSubscription')) {
    function canViewSubscription() {
        global $db;
        static $cached = null;
        if ($cached === null) {
            $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_subscription_view'");
            $cached = ($result && $result['setting_value'] == '1');
        }
        return $cached;
    }
}

$db = Database::getInstance();
$userId = Auth::userId();
$patientId = getPatientIdFromUserId($userId);
if (!$patientId) {
    die("Patient record not found. Please contact support.");
}

// Get patient and user details
$patient = patient_portal_fetch_patient_cloud_first((int) $patientId);
if (!$patient) {
    die("Patient record not found. Please contact support.");
}
$user = $db->fetchOne("SELECT * FROM users WHERE id = ?", [$userId], "i");

$pageTitle = 'My Profile';

include '../layouts/header.php';

/* Feature flags: clinic_settings via canViewPoints() / canViewReferrals() (not $_POST on GET).
 * If both points and referrals are off, omit "Member since" so the subtitle strip is one row (3 fields). */
$allowPoints = canViewPoints();
$allowReferrals = canViewReferrals();
$referralCodeRaw = trim((string) ($patient['referral_code'] ?? ''));

$profileHeaderItems = [];
$uName = trim((string) ($user['username'] ?? ''));
$profileHeaderItems[] = [
    'icon' => 'fa-at',
    'label' => __('username', 'Username'),
    'value' => $uName !== '' ? $uName : '—',
    'extra' => null,
    'mono' => false,
];
$phoneDisp = trim((string) ($patient['phone'] ?? ''));
$profileHeaderItems[] = [
    'icon' => 'fa-phone',
    'label' => __('phone', 'Phone'),
    'value' => $phoneDisp !== '' ? $phoneDisp : '—',
    'extra' => null,
    'mono' => false,
];
$emailDisp = trim((string) ($patient['email'] ?? ''));
$profileHeaderItems[] = [
    'icon' => 'fa-envelope',
    'label' => __('email', 'Email'),
    'value' => $emailDisp !== '' ? $emailDisp : '—',
    'extra' => null,
    'mono' => false,
];
if ($allowPoints || $allowReferrals) {
    $profileHeaderItems[] = [
        'icon' => 'fa-calendar-check',
        'label' => __('member_since', 'Member since'),
        'value' => formatDate($patient['created_at'], 'M d, Y'),
        'extra' => null,
        'mono' => false,
    ];
}
if ($allowPoints) {
    $pts = (int) ($patient['points'] ?? 0);
    $nextPts = 250 - ($pts % 250);
    $profileHeaderItems[] = [
        'icon' => 'fa-star',
        'label' => __('my_points', 'Reward points'),
        'value' => (string) $pts,
        'extra' => 'Next in ' . $nextPts . ' pts',
        'mono' => false,
    ];
}
if ($allowReferrals) {
    $profileHeaderItems[] = [
        'icon' => 'fa-gift',
        'label' => __('referral_code', 'Referral code'),
        'value' => $referralCodeRaw !== '' ? $referralCodeRaw : '—',
        'extra' => null,
        'mono' => true,
    ];
}
$profileHeaderRow1 = array_slice($profileHeaderItems, 0, 3);
$profileHeaderRow2 = array_slice($profileHeaderItems, 3);
?>


<div class="container-fluid profile-page">
    <div id="message"></div>

    <?php
    $profileHeaderHasSubBadge = canViewSubscription() && ($patient['subscription_type'] ?? 'none') !== 'none';
    ?>
    <!-- Profile header: queue-style gradient; centered name; subtitle fields in 2Ã—3 grid -->
    <div class="profile-header-card">
        <div class="queue-header">
            <div class="profile-header-inner">
                <?php if ($profileHeaderHasSubBadge): ?>
                    <div class="profile-hero-badge text-white profile-header-subscription-badge">
                        <i class="fas fa-crown"></i> <?php echo htmlspecialchars(ucfirst((string) $patient['subscription_type'])); ?> <?php echo __('subscription', 'Subscription'); ?>
                    </div>
                <?php endif; ?>
                <h2 class="profile-header-name<?php echo $profileHeaderHasSubBadge ? ' profile-header-name--has-badge' : ''; ?>">
                    <?php echo htmlspecialchars((string) ($patient['full_name'] ?? '')); ?>
                </h2>
                <div class="profile-header-meta">
                    <div class="profile-header-meta-rows">
                        <div class="profile-header-inline-strip">
                            <?php foreach ($profileHeaderRow1 as $item): ?>
                                <div class="profile-header-inline-item">
                                    <i class="fas <?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                    <span class="profile-header-inline-label"><?php echo htmlspecialchars((string) $item['label']); ?></span>
                                    <span class="profile-header-inline-value<?php echo !empty($item['mono']) ? ' profile-header-inline-mono' : ''; ?>"><?php echo htmlspecialchars((string) $item['value']); ?></span>
                                    <?php if (!empty($item['extra'])): ?>
                                        <span class="profile-header-inline-extra"><?php echo htmlspecialchars((string) $item['extra']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($profileHeaderRow2) > 0): ?>
                        <div class="profile-header-inline-strip">
                            <?php foreach ($profileHeaderRow2 as $item): ?>
                                <div class="profile-header-inline-item">
                                    <i class="fas <?php echo htmlspecialchars((string) $item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                                    <span class="profile-header-inline-label"><?php echo htmlspecialchars((string) $item['label']); ?></span>
                                    <span class="profile-header-inline-value<?php echo !empty($item['mono']) ? ' profile-header-inline-mono' : ''; ?>"><?php echo htmlspecialchars((string) $item['value']); ?></span>
                                    <?php if (!empty($item['extra'])): ?>
                                        <span class="profile-header-inline-extra"><?php echo htmlspecialchars((string) $item['extra']); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- PROFILE UPDATE FORM (personal, contact, emergency, username) -->
            <form method="POST" action="<?php echo url('api/patient_profile.php'); ?>" data-api="<?php echo url('api/patient_profile.php'); ?>" data-message-target="#message" autocomplete="off">
                <div class="profile-section profile-section--unified">
                    <div class="profile-form-subsection">
                        <h5 class="section-title section-title--subsection section-title--personal">
                            <i class="fas fa-user section-icon"></i> Personal Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label-modern">Full Name *</label>
                                <input type="text" class="form-control form-control-modern" name="full_name" 
                                       value="<?php echo htmlspecialchars($patient['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-modern">Username *</label>
                                <input type="text" class="form-control form-control-modern" name="username"
                                       value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>"
                                       required autocomplete="username"
                                       pattern="[a-zA-Z0-9._-]{3,64}"
                                       title="3–64 characters: letters, numbers, dot, underscore, hyphen">
                                <small class="text-muted">Used to sign in. Must be unique.</small>
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

                    <div class="profile-form-subsection">
                        <h5 class="section-title section-title--subsection section-title--contact">
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
                            <div class="col-md-12 mb-3">
                                <label class="form-label-modern">Address</label>
                                <input type="text" class="form-control form-control-modern" name="address"
                                       value="<?php echo htmlspecialchars($patient['address'] ?? $patient['address_line1'] ?? ''); ?>"
                                       placeholder="Full address">
                            </div>
                        </div>
                    </div>

                    <div class="profile-form-subsection">
                        <h5 class="section-title section-title--subsection section-title--emergency">
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
                                       placeholder="Parent, Sibling...">
                            </div>
                        </div>
                    </div>

                    <div class="profile-section-card-actions">
                        <button type="submit" name="save_profile" value="1" class="btn btn-profile-confirm">
                            <i class="fas fa-save me-1"></i> <?php echo __('save_profile', 'Save Profile'); ?>
                        </button>
                    </div>
                </div>
            </form>

            <!-- PASSWORD CHANGE FORM (separate) -->
            <form method="POST" action="<?php echo url('api/patient_profile.php'); ?>" data-api="<?php echo url('api/patient_profile.php'); ?>" data-message-target="#message" autocomplete="off">
                <div class="profile-section profile-section--unified">
                    <div class="profile-form-subsection">
                        <h5 class="section-title section-title--subsection section-title--password">
                            <i class="fas fa-key section-icon"></i> <?php echo htmlspecialchars(profile_pretty_label('change_password', 'Change password')); ?>
                        </h5>
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label-modern" for="current_password_profile"><?php echo htmlspecialchars(profile_pretty_label('current_password', 'Current password')); ?></label>
                                <input type="password" class="form-control form-control-modern" name="current_password" id="current_password_profile"
                                       autocomplete="current-password">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-modern" for="new_password_profile"><?php echo htmlspecialchars(profile_pretty_label('new_password', 'New password')); ?></label>
                                <input type="password" class="form-control form-control-modern" name="new_password" id="new_password_profile"
                                       pattern=".{6,}" title="<?php echo htmlspecialchars(profile_pretty_label('password_minimum_length', 'Minimum 6 characters')); ?>" autocomplete="new-password">
                                <small class="text-muted"><?php echo htmlspecialchars(profile_pretty_label('password_minimum_length', 'Minimum 6 characters')); ?></small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label-modern" for="confirm_password_profile"><?php echo htmlspecialchars(profile_pretty_label('confirm_password', 'Confirm password')); ?></label>
                                <input type="password" class="form-control form-control-modern" name="confirm_password" id="confirm_password_profile"
                                       autocomplete="new-password">
                            </div>
                            <div class="col-12 mb-3">
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="showPasswords">
                                    <label class="form-check-label form-label-modern mb-0" for="showPasswords"><?php echo htmlspecialchars(profile_pretty_label('show_passwords', 'Show passwords')); ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="profile-section-card-actions">
                        <button type="submit" name="change_password" value="1" class="btn btn-profile-confirm">
                            <i class="fas fa-key me-1"></i> <?php echo __('change_password', 'Change Password'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="col-md-4">
            <!-- Subscription Info Card (visible only if subscription feature is enabled) -->
            <?php if (canViewSubscription()): ?>
            <div class="profile-section">
                <h5 class="section-title section-title--subscription">
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
                    <a href="subscription.php" class="btn btn-profile-green-outline w-100">
                        <i class="fas fa-arrow-right"></i> Manage Subscription
                    </a>
                <?php else: ?>
                    <p class="text-muted text-center mb-3">No active subscription</p>
                    <a href="subscription.php" class="btn btn-profile-green-solid w-100">
                        <i class="fas fa-gem"></i> Subscribe Now
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Referral Info Card (visible only if referrals feature is enabled) -->
            <?php if ($allowReferrals): ?>
            <div class="profile-section">
                <h5 class="section-title section-title--referral">
                    <i class="fas fa-share-alt section-icon"></i> Referral Program
                </h5>
                <p class="text-muted small mb-3">Your referral code is shown in the profile header above.</p>
                <button type="button" class="btn btn-profile-green-solid w-100 mb-2" onclick="copyReferralCode()">
                    <i class="fas fa-copy"></i> Copy referral code
                </button>
                <small class="text-muted d-block text-center">
                    <i class="fas fa-gift"></i> Share your code and earn 50 points per referral!
                </small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('showPasswords')?.addEventListener('change', function (e) {
    document.querySelectorAll('#current_password_profile, #new_password_profile, #confirm_password_profile').forEach(function (field) {
        field.type = e.target.checked ? 'text' : 'password';
    });
});

function copyReferralCode() {
    const code = <?php echo json_encode((string) ($patient['referral_code'] ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    if (!code) {
        return;
    }
    navigator.clipboard.writeText(code).then(function() {
        alert('Referral code copied to clipboard!');
    });
}
</script>

<?php include '../layouts/footer.php'; ?>
