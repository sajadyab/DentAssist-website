<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'] ?? '';
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h3><?php echo SITE_NAME; ?></h3>
    </div>
    
    <div class="user-info">
        <?php
        // Determine profile image source
        $profileImage = '';
        if (isset($_SESSION['profile_image']) && !empty($_SESSION['profile_image'])) {
            $profileImage = SITE_URL . '/assets/uploads/' . $_SESSION['profile_image'];
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
            
            <li class="<?php echo strpos($currentPage, 'treatment') !== false ? 'active' : ''; ?>">
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
            <li>
                <a href="<?php echo url('settings/index.php'); ?>">
                    <i class="fas fa-cog"></i> <span><?php echo __('settings', 'Settings'); ?></span>
                </a>
            </li>
        <?php else: ?>
            <!-- Patient Menu -->
            <li>
                <a href="<?php echo url('patient/index.php'); ?>">
                    <i class="fas fa-home"></i>
                    <span><?php echo __('my_portal', 'My Portal'); ?></span>
                </a>
            </li>
             <li>
                <a href="<?php echo url('patient/profile.php'); ?>">
                    <i class="fas fa-user-edit"></i>
                    <span><?php echo __('profile', 'Profile'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo url('patient/book.php'); ?>">
                    <i class="fas fa-calendar-plus"></i>
                    <span><?php echo __('book_appointment', 'Book Appointment'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo url('patient/queue.php'); ?>">
                    <i class="fas fa-clock"></i>
                    <span><?php echo __('join_queue', 'Join Queue'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo url('patient/bills.php'); ?>">
                    <i class="fas fa-file-invoice"></i>
                    <span><?php echo __('my_bills', 'My Bills'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo url('patient/teeth.php'); ?>">
                    <i class="fas fa-tooth"></i>
                    <span><?php echo __('my_teeth', 'My Teeth'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo url('patient/points.php'); ?>">
                    <i class="fas fa-star"></i>
                    <span><?php echo __('my_points', 'My Points'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo url('patient/subscription.php'); ?>">
                    <i class="fas fa-crown"></i>
                    <span><?php echo __('subscription', 'Subscription'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo url('patient/referrals.php'); ?>">
                    <i class="fas fa-user-friends"></i>
                    <span><?php echo __('referrals', 'Referrals'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo url('settings/index.php'); ?>">
                    <i class="fas fa-cog"></i> <span><?php echo __('settings', 'Settings'); ?></span>
                </a>
            </li>
           
        <?php endif; ?>
        
        <!-- Language Switcher -->
        <li class="mt-3">
            <div class="px-3 py-2">
                <label class="form-label text-white-50 small mb-2"><?php echo __('language', 'Language'); ?></label>
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

<style>
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    width: 250px;
    background: #2c3e50;
    color: white;
    transition: all 0.3s;
    overflow-y: auto;
}

.sidebar-header {
    padding: 20px;
    background: #1a252f;
    text-align: center;
}

.sidebar-header h3 {
    margin: 0;
    font-size: 1.2rem;
    color: white;
}

.user-info {
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid #34495e;
}

.profile-img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    object-fit: cover;
}

.nav-menu {
    list-style: none;
    padding: 20px 0;
}

.nav-menu li a {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: #ecf0f1;
    text-decoration: none;
    transition: all 0.3s;
    gap: 10px;
}

.nav-menu li a i {
    width: 20px;
}

.nav-menu li:hover a {
    background: #34495e;
    padding-left: 30px;
}

.nav-menu li.active a {
    background: #3498db;
}

.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: 100vh;
    background: #f8f9fa;
}

@media (max-width: 768px) {
    .sidebar {
        width: 0;
    }
    .main-content {
        margin-left: 0;
    }
}
</style>