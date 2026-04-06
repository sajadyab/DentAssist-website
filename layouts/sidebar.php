<?php
// IMPORTANT: Do NOT redeclare getClinicSetting() here!
// The function is already declared in settings/index.php or should be available globally.
// If it's not defined, we'll define it here only if it doesn't exist.

if (!function_exists('getClinicSetting')) {
    function getClinicSetting($key, $default = '') {
        $db = null;

        if (isset($GLOBALS['db']) && is_object($GLOBALS['db']) && method_exists($GLOBALS['db'], 'fetchOne')) {
            $db = $GLOBALS['db'];
        } elseif (class_exists('Database')) {
            $db = Database::getInstance();
        }

        if (!$db || !method_exists($db, 'fetchOne')) {
            return $default;
        }

        $result = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = ?", [$key]);
        return ($result && array_key_exists('setting_value', $result)) ? $result['setting_value'] : $default;
    }
}

$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
$selfPath = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
$patientNavActive = static function (string $leaf) use ($selfPath): bool {
    $leaf = ltrim($leaf, '/');
    return $leaf !== '' && substr($selfPath, -strlen($leaf)) === $leaf;
};

// Get menu visibility settings
$showPointsMenu = getClinicSetting('allow_points_view', '1');
$showReferralsMenu = getClinicSetting('allow_referrals_view', '1');
$showSubscriptionMenu = getClinicSetting('allow_subscription_view', '1');
?>
<div class="sidebar" id="app-sidebar">
    <div class="sidebar-header">
        <h3><?php echo SITE_NAME; ?></h3>
    </div>
    
    <div class="user-info">
        <?php
        // Determine profile image source
        $profileImage = '';
        if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
            $profileImage = asset_url('assets/uploads/' . $_SESSION['profile_image']);
        } else {
            // Generate initials from full name
            $fullName = $_SESSION['full_name'] ?? 'User';
            $nameParts = explode(' ', $fullName);
            $initials = strtoupper(substr($nameParts[0], 0, 1));
            if (isset($nameParts[1])) {
                $initials .= strtoupper(substr($nameParts[1], 0, 1));
            }
            $profileImage = 'https://ui-avatars.com/api/?name=' . urlencode($initials) . '&background=3498db&color=fff&size=50';
        }
        ?>
        <img src="<?php echo $profileImage; ?>" alt="Profile" class="profile-img">
        <div>
            <strong><?php echo $_SESSION['full_name'] ?? 'User'; ?></strong>
            <small class="d-block text-muted"><?php echo ucfirst($role); ?></small>
        </div>
    </div>
    
    <ul class="nav-menu">
        <?php if ($role != 'patient'): ?>
            <li class="<?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                <a href="<?php echo url('dashboard.php'); ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span><?php echo __('dashboard', 'Dashboard'); ?></span>
                </a>
            </li>
        <?php endif; ?>
        
        <?php if ($role != 'patient'): ?>
            <!-- Staff Menu -->
            <li class="<?php echo strpos($currentPage, 'patient') !== false ? 'active' : ''; ?>">
                <a href="<?php echo url('patients/index.php'); ?>">
                    <i class="fas fa-users"></i>
                    <span><?php echo __('patients', 'Patients'); ?></span>
                </a>
            </li>
            
            <li class="<?php echo strpos($currentPage, 'appointment') !== false ? 'active' : ''; ?>">
                <a href="<?php echo url('appointments/index.php'); ?>">
                    <i class="fas fa-calendar-check"></i>
                    <span><?php echo __('appointments', 'Appointments'); ?></span>
                </a>
            </li>
            
            <li class="<?php echo $currentPage == 'treatment_plans/index.php' ? 'active' : ''; ?>">
                <a href="<?php echo url('treatment_plans/index.php'); ?>">
                    <i class="fas fa-notes-medical"></i>
                    <span><?php echo __('treatment_plans', 'Treatment Plans'); ?></span>
                </a>
            </li>
            
            <li class="<?php echo strpos($currentPage, 'billing') !== false ? 'active' : ''; ?>">
                <a href="<?php echo url('billing/invoices.php'); ?>">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span><?php echo __('billing', 'Billing'); ?></span>
                </a>
            </li>
<?php if ($role != 'assistant'): ?>
            <li class="<?php echo $currentPage == 'reports/financial.php' ? 'active' : ''; ?>">
                <a href="<?php echo url('reports/financial.php'); ?>">
                    <i class="fas fa-chart-line"></i>
                    <span><?php echo __('financial_dashboard', 'Financial Dashboard'); ?></span>
                </a>
            </li>

            <li class="<?php echo strpos($currentPage, 'report') !== false && $currentPage != 'reports/financial.php' && $currentPage != 'reports/messages.php' ? 'active' : ''; ?>">
                <a href="<?php echo url('reports/index.php'); ?>">
                    <i class="fas fa-chart-bar"></i>
                    <span><?php echo __('reports', 'Reports'); ?></span>
                </a>
            </li>
<?php endif; ?>
            <li class="<?php echo $currentPage == 'queue/index.php' ? 'active' : ''; ?>">
                <a href="<?php echo url('queue/index.php'); ?>">
                    <i class="fas fa-clock"></i>
                    <span><?php echo __('queue', 'Waiting Queue'); ?></span>
                </a>
            </li>
            
            <li class="<?php echo strpos($currentPage, 'inventory') !== false ? 'active' : ''; ?>">
                <a href="<?php echo url('inventory/index.php'); ?>">
                    <i class="fas fa-boxes"></i>
                    <span><?php echo __('inventory', 'Inventory'); ?></span>
                </a>
            </li>
            <?php if ($role != 'assistant'): ?>
            <!-- Treatments Management for Doctors and Admins -->
            <li class="<?php echo $currentPage == 'treatments.php' ? 'active' : ''; ?>">
                <a href="<?php echo url('treatments.php'); ?>">
                    <i class="fas fa-tooth"></i>
                    <span><?php echo __('treatments', 'Treatments'); ?></span>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="<?php echo url('settings/index.php'); ?>">
                    <i class="fas fa-cog"></i> <span><?php echo __('settings', 'Settings'); ?></span>
                </a>
            </li>
        <?php else: ?>
            <!-- Patient Menu -->
            <li class="<?php echo $patientNavActive('patient/index.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('patient/index.php'); ?>">
                    <i class="fas fa-home"></i>
                    <span><?php echo __('my_portal', 'My Portal'); ?></span>
                </a>
            </li>
            <li class="<?php echo $patientNavActive('patient/profile.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('patient/profile.php'); ?>">
                    <i class="fas fa-user-edit"></i>
                    <span><?php echo __('profile', 'Profile'); ?></span>
                </a>
            </li>
            <li class="<?php echo $patientNavActive('patient/queue.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('patient/queue.php'); ?>">
                    <i class="fas fa-calendar-plus"></i>
                    <span><?php echo __('book_appointment', 'Book Appointment'); ?></span>
                </a>
            </li>
            <li class="<?php echo $patientNavActive('patient/bills.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('patient/bills.php'); ?>">
                    <i class="fas fa-file-invoice"></i>
                    <span><?php echo __('my_bills', 'My Bills'); ?></span>
                </a>
            </li>
            <li class="<?php echo $patientNavActive('patient/teeth.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('patient/teeth.php'); ?>">
                    <i class="fas fa-tooth"></i>
                    <span><?php echo __('my_teeth', 'My Teeth'); ?></span>
                </a>
            </li>
            <?php if ($showPointsMenu == '1'): ?>
            <li class="<?php echo $patientNavActive('patient/points.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('patient/points.php'); ?>">
                    <i class="fas fa-star"></i>
                    <span><?php echo __('my_points', 'My Points'); ?></span>
                </a>
            </li>
            <?php endif; ?>
             <?php if ($showSubscriptionMenu== '1'): ?>
            <li class="<?php echo $patientNavActive('patient/subscription.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('patient/subscription.php'); ?>">
                    <i class="fas fa-crown"></i>
                    <span><?php echo __('subscription', 'Subscription'); ?></span>
                </a>
            </li>
            <?php endif; ?>
            <?php if ($showReferralsMenu == '1'): ?>
            <li class="<?php echo $patientNavActive('patient/referrals.php') ? 'active' : ''; ?>">
                <a href="<?php echo url('patient/referrals.php'); ?>">
                    <i class="fas fa-user-friends"></i>
                    <span><?php echo __('referrals', 'Referrals'); ?></span>
                </a>
            </li>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Language Switcher -->
        <li class="mt-3 sidebar-lang">
            <div class="px-3 py-2">
                <label class="form-label sidebar-lang-label small mb-2"><?php echo __('language', 'Language'); ?></label>
                <select class="form-select form-select-sm" onchange="changeLanguage(this.value)">
                    <option value="en" <?php echo (getLanguage() == 'en') ? 'selected' : ''; ?>>English</option>
                    <option value="ar" <?php echo (getLanguage() == 'ar') ? 'selected' : ''; ?>>العربية</option>
                    <option value="fr" <?php echo (getLanguage() == 'fr') ? 'selected' : ''; ?>>Français</option>
                </select>
            </div>
        </li>
        
        <li>
            <a href="<?php echo url('logout.php'); ?>">
                <i class="fas fa-sign-out-alt"></i>
                <span><?php echo __('logout', 'Logout'); ?></span>
            </a>
        </li>
    </ul>
</div>

<script>
function changeLanguage(lang) {
    fetch('<?php echo url("api/change_language.php"); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ language: lang })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error changing language');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error changing language');
    });
}
</script>
