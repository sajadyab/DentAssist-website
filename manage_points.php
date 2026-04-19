<?php
// manage_points.php - Points management with patient search
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

Auth::requireLogin();
if (!in_array($_SESSION['role'], ['doctor', 'assistant'])) {
    header('Location: dashboard.php');
    exit;
}

$db = Database::getInstance();

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_id']) && isset($_POST['points_change'])) {
    $patientId = (int)$_POST['patient_id'];
    $change = (int)$_POST['points_change'];
    
    if ($patientId <= 0) {
        $error = 'Invalid patient.';
    } else {
        $current = $db->fetchOne("SELECT points FROM patients WHERE id = ?", [$patientId], "i");
        if ($current) {
            $newPoints = $current['points'] + $change;
            if ($newPoints < 0) {
                $error = 'Points cannot go below zero.';
            } else {
                $db->query("UPDATE patients SET points = ? WHERE id = ?", [$newPoints, $patientId], "ii");
                $message = 'Points updated successfully. New balance: ' . $newPoints;
            }
        } else {
            $error = 'Patient not found.';
        }
    }
}

$patients = $db->fetchAll("SELECT id, full_name, points, email, phone FROM patients ORDER BY full_name ASC");

$rewards = [
    ['name' => 'Free Teeth Whitening', 'points' => 500],
    ['name' => 'Free Dental Cleaning', 'points' => 250],
    ['name' => '$50 Treatment Discount', 'points' => 300],
    ['name' => 'Dental Care Kit', 'points' => 150]
];

$pageTitle = 'Manage Patient Points';
include 'layouts/header.php';
?>

<style>
    .manage-points-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 20px;
        padding: 25px 30px;
        margin-bottom: 30px;
        color: white;
    }
    .points-table-container {
        background: white;
        border-radius: 20px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .search-wrapper {
        padding: 20px 20px 0 20px;
        background: white;
    }
    .search-input {
        border-radius: 50px;
        padding: 10px 20px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        transition: all 0.2s;
        width: 100%;
        font-size: 0.9rem;
    }
    .search-input:focus {
        outline: none;
        border-color: #667eea;
        background: white;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    .search-icon {
        position: absolute;
        right: 15px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
    }
    .points-table {
        margin-bottom: 0;
        width: 100%;
    }
    .points-table thead th {
        background: #f8f9fc;
        border-bottom: 2px solid #e9ecef;
        padding: 16px 15px;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #4a5568;
    }
    .points-table tbody tr {
        transition: all 0.2s ease;
        border-bottom: 1px solid #f0f2f5;
    }
    .points-table tbody tr:hover {
        background: #f8faff;
        transform: scale(1.01);
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    .points-table tbody td {
        padding: 16px 15px;
        vertical-align: middle;
    }
    .patient-name {
        font-weight: 600;
        color: #2d3748;
        font-size: 1rem;
    }
    .contact-info {
        font-size: 0.8rem;
        color: #718096;
    }
    .contact-info i {
        width: 20px;
        color: #a0aec0;
    }
    .points-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 40px;
        padding: 8px 18px;
        font-size: 1.1rem;
        font-weight: bold;
        display: inline-block;
        min-width: 80px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(102,126,234,0.3);
    }
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
    }
    .btn-points {
        border-radius: 40px;
        padding: 6px 14px;
        font-size: 0.75rem;
        font-weight: 600;
        transition: all 0.2s;
    }
    .btn-points:hover {
        transform: translateY(-1px);
    }
    .custom-points-wrapper {
        display: inline-flex;
        align-items: center;
        background: #f1f5f9;
        border-radius: 40px;
        padding: 3px 3px 3px 15px;
        margin-left: 5px;
    }
    .custom-points-input {
        width: 70px;
        border: none;
        background: transparent;
        text-align: center;
        font-size: 0.8rem;
        font-weight: 500;
        padding: 6px 0;
        outline: none;
    }
    .custom-points-input:focus {
        outline: none;
    }
    .custom-points-input::placeholder {
        color: #94a3b8;
        font-weight: normal;
    }
    .custom-points-label {
        font-size: 0.7rem;
        color: #64748b;
        margin-right: 5px;
    }
    .reward-card {
        background: #ffffff;
        border-left: 4px solid #ffc107;
        padding: 12px 15px;
        margin-bottom: 12px;
        border-radius: 12px;
        transition: 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .reward-card:hover {
        transform: translateX(5px);
        background: #fffbeb;
    }
    .card-custom {
        border-radius: 20px;
        border: none;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 25px;
    }
    .card-header-custom {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 15px 20px;
        font-weight: 600;
        font-size: 1rem;
    }
    .earning-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px dashed #e9ecef;
    }
    .earning-item:last-child {
        border-bottom: none;
    }
    .badge-earn {
        background: #e6f7e6;
        color: #2e7d32;
        border-radius: 40px;
        padding: 4px 12px;
        font-weight: 600;
    }
    .no-results {
        text-align: center;
        padding: 40px;
        color: #718096;
    }
</style>

<div class="container-fluid">
    <!-- Header -->
    <div class="manage-points-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2><i class="fas fa-coins me-2"></i> Patient Points Management</h2>
                <p class="mb-0 opacity-75">Add or deduct loyalty points for patients. Points can be redeemed for rewards.</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <a href="dashboard.php" class="btn btn-light rounded-pill px-4"><i class="fas fa-arrow-left me-1"></i> Back to Dashboard</a>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-pill"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-pill"><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row">
        <!-- Main table column -->
        <div class="col-lg-8">
            <div class="points-table-container">
                <div class="search-wrapper">
                    <div class="position-relative">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" id="searchPatient" class="search-input" placeholder="Search by patient name...">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="points-table" id="patientTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th class="text-center">Points</th>
                                <th class="text-center">Adjust Points</th>
                            </tr>
                        </thead>
                        <tbody id="patientTableBody">
                            <?php foreach ($patients as $p): ?>
                            <tr data-name="<?php echo strtolower(htmlspecialchars($p['full_name'])); ?>">
                                <td class="patient-name"><?php echo htmlspecialchars($p['full_name']); ?></td>
                                <td class="contact-info">
                                    <?php if ($p['email']): ?>
                                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($p['email']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($p['phone']): ?>
                                        <div><i class="fas fa-phone-alt"></i> <?php echo htmlspecialchars($p['phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!$p['email'] && !$p['phone']): ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="points-badge"><?php echo (int)$p['points']; ?></span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="patient_id" value="<?php echo $p['id']; ?>">
                                            <input type="hidden" name="points_change" value="50">
                                            <button type="submit" class="btn btn-success btn-points" title="Add 50 points"><i class="fas fa-plus-circle me-1"></i> +50</button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="patient_id" value="<?php echo $p['id']; ?>">
                                            <input type="hidden" name="points_change" value="10">
                                            <button type="submit" class="btn btn-info btn-points text-white" title="Add 10 points"><i class="fas fa-plus me-1"></i> +10</button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="patient_id" value="<?php echo $p['id']; ?>">
                                            <input type="hidden" name="points_change" value="-10">
                                            <button type="submit" class="btn btn-warning btn-points" title="Subtract 10 points"><i class="fas fa-minus me-1"></i> -10</button>
                                        </form>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="patient_id" value="<?php echo $p['id']; ?>">
                                            <div class="custom-points-wrapper">
                                                <span class="custom-points-label">Custom:</span>
                                                <input type="number" name="points_change" class="custom-points-input" placeholder="±" step="1" required>
                                                <button type="submit" class="btn btn-sm btn-secondary rounded-pill ms-1 px-3" title="Apply custom points"><i class="fas fa-check"></i></button>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="small text-muted mt-1 text-center" style="font-size: 0.7rem;">
                                        <i class="fas fa-info-circle"></i> Enter positive or negative number
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="noResults" class="no-results" style="display: none;">
                        <i class="fas fa-user-slash fa-2x mb-2"></i><br>
                        No patients found matching your search.
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card-custom">
                <div class="card-header-custom">
                    <i class="fas fa-gift text-warning me-2"></i> Available Rewards
                </div>
                <div class="card-body p-3">
                    <?php foreach ($rewards as $reward): ?>
                    <div class="reward-card d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-star text-warning me-2"></i> <?php echo $reward['name']; ?></span>
                        <span class="badge bg-primary rounded-pill px-3 py-2"><?php echo $reward['points']; ?> pts</span>
                    </div>
                    <?php endforeach; ?>
                    <div class="alert alert-light mt-3 mb-0 small rounded-pill">
                        <i class="fas fa-info-circle text-primary me-1"></i> Patients can redeem at front desk.
                    </div>
                </div>
            </div>

            <div class="card-custom mt-4">
                <div class="card-header-custom">
                    <i class="fas fa-chart-line text-success me-2"></i> Earning Guide
                </div>
                <div class="card-body p-3">
                    <div class="earning-item">
                        <span><i class="fas fa-calendar-check text-success me-2"></i> Completed appointment</span>
                        <span class="badge-earn">+50 pts</span>
                    </div>
                    <div class="earning-item">
                        <span><i class="fas fa-user-friends text-success me-2"></i> Refer a friend</span>
                        <span class="badge-earn">+50 pts</span>
                    </div>
                    <div class="earning-item">
                        <span><i class="fas fa-gem text-success me-2"></i> Premium subscription</span>
                        <span class="badge-earn">+200 pts</span>
                    </div>
                    <div class="earning-item">
                        <span><i class="fas fa-birthday-cake text-success me-2"></i> First appointment</span>
                        <span class="badge-earn">+100 pts</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('searchPatient').addEventListener('keyup', function() {
    let searchValue = this.value.toLowerCase();
    let rows = document.querySelectorAll('#patientTableBody tr');
    let visibleCount = 0;
    
    rows.forEach(row => {
        let patientName = row.getAttribute('data-name');
        if (patientName.includes(searchValue)) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    let noResultsDiv = document.getElementById('noResults');
    if (visibleCount === 0) {
        noResultsDiv.style.display = 'block';
    } else {
        noResultsDiv.style.display = 'none';
    }
});
</script>

<?php include 'layouts/footer.php'; ?>