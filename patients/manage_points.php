<?php
// manage_points.php - Points management for doctors/assistants (located in /patients/ folder)
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Only doctors and assistants allowed
Auth::requireLogin();
if (!in_array($_SESSION['role'], ['doctor', 'assistant'])) {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();

// Handle points update
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['patient_id']) && isset($_POST['points_change'])) {
    $patientId = (int) $_POST['patient_id'];
    $change = (int) $_POST['points_change'];

    if ($patientId <= 0) {
        $error = 'Invalid patient.';
    } else {
        $current = $db->fetchOne('SELECT points FROM patients WHERE id = ?', [$patientId], 'i');
        if ($current) {
            $newPoints = $current['points'] + $change;
            if ($newPoints < 0) {
                $error = 'Points cannot go below zero.';
            } else {
                $sql = 'UPDATE patients SET points = ?';
                $params = [$newPoints, $patientId];

                if (function_exists('dbColumnExists') && dbColumnExists('patients', 'sync_status')) {
                    $sql .= ", sync_status = 'pending'";
                }

                $sql .= ' WHERE id = ?';
                $db->query($sql, $params, 'ii');

                if (function_exists('sync_push_row_now')) {
                    sync_push_row_now('patients', $patientId);
                }

                $message = 'Points updated successfully. New balance: ' . $newPoints;
            }
        } else {
            $error = 'Patient not found.';
        }
    }
}
$subscriptionPatients = $db->fetchAll("
    SELECT id, full_name, phone, email, subscription_type, subscription_start_date, subscription_end_date, subscription_status
    FROM patients
    WHERE subscription_type != 'none'
    ORDER BY subscription_end_date ASC
");

// Calculate days left for each subscription patient
foreach ($subscriptionPatients as &$sp) {
    $endDate = $sp['subscription_end_date'];
    if ($endDate && $endDate != '0000-00-00') {
        $now = new DateTime();
        $end = new DateTime($endDate);
        if ($now > $end) {
            $sp['days_left'] = -$now->diff($end)->days;
            $sp['status_text'] = 'Expired';
            $sp['row_class'] = 'expired-row';
        } else {
            $diff = $now->diff($end);
            $sp['days_left'] = $diff->days;
            if ($sp['days_left'] <= 3) {
                $sp['row_class'] = 'expiring-soon-row';
            } else {
                $sp['row_class'] = '';
            }
            $sp['status_text'] = $sp['subscription_status'] === 'active' ? 'Active' : ucfirst((string) $sp['subscription_status']);
        }
    } else {
        $sp['days_left'] = null;
        $sp['status_text'] = 'Invalid date';
        $sp['row_class'] = '';
    }
}
unset($sp);

// Fetch all patients
$patients = $db->fetchAll('SELECT id, full_name, points, email, phone FROM patients ORDER BY full_name ASC');

// Fetch rewards from database
$rewards = $db->fetchAll(
    "SELECT id, name, description, points_required as points, icon 
     FROM points_rewards 
     WHERE is_active = 1 
     ORDER BY display_order ASC, points_required ASC"
);

// Fetch earning rules from database
$earningRules = $db->fetchAll(
    "SELECT id, title, description, points, icon 
     FROM points_earning_rules 
     WHERE is_active = 1 
     ORDER BY display_order ASC"
);

// Provide fallback empty arrays if tables don't exist (optional)
if ($rewards === false) {
    $rewards = [];
}
if ($earningRules === false) {
    $earningRules = [];
}

$pageTitle = 'Points & Subscriptions';
include '../layouts/header.php';
?>

<div class="container-fluid bills-page patients-manage-points-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold appointments-schedule-title">
                    <i class="fas fa-coins me-2 opacity-90" aria-hidden="true"></i>Points &amp; Subscriptions
                </h2>
                <p class="mb-0 opacity-90">Adjust loyalty points and review subscription expiry reminders.</p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end gap-2 appointments-add-top-actions">
                <a href="index.php" class="btn btn-secondary appointments-add-top-btn">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <span class="d-none d-sm-inline">Back to Patients</span><span class="d-sm-none">Back</span>
                </a>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-8">
            <div class="card bills-dash-section-card mb-3 mb-lg-0 manage-points-main-card" id="manage-points-patients-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-users me-2" aria-hidden="true"></i>All patients</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body border-bottom py-3">
                    <label class="visually-hidden" for="searchPatient">Search patients</label>
                    <input type="text" id="searchPatient" class="form-control form-control-modern" placeholder="Search by Patient Name..." autocomplete="off">
                </div>
                <div class="card-body appointments-table-wrap pt-0">
                    <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle" id="patientTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th class="text-center">Points</th>
                                <th class="text-center">Adjust points</th>
                            </tr>
                        </thead>
                        <tbody id="patientTableBody">
                            <?php foreach ($patients as $p): ?>
                                <tr data-name="<?php echo strtolower(htmlspecialchars($p['full_name'])); ?>">
                                    <td class="fw-semibold"><?php echo htmlspecialchars($p['full_name']); ?></td>
                                    <td class="small text-muted">
                                        <?php if ($p['email']): ?>
                                            <div><i class="fas fa-envelope me-1 opacity-75" aria-hidden="true"></i><?php echo htmlspecialchars((string) $p['email']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($p['phone']): ?>
                                            <div><i class="fas fa-phone-alt me-1 opacity-75" aria-hidden="true"></i><?php echo htmlspecialchars((string) $p['phone']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!$p['email'] && !$p['phone']): ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill px-3 py-2 manage-points-all-patients-pts-badge"><?php echo (int) $p['points']; ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="table-card-actions manage-points-adjust-wrap justify-content-end">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="patient_id" value="<?php echo (int) $p['id']; ?>">
                                                <input type="hidden" name="points_change" value="50">
                                                <button type="submit" class="btn btn-sm table-action-btn action-yellow manage-points-preset-text manage-points-preset-text--wide" title="Add 50 points">+50</button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="patient_id" value="<?php echo (int) $p['id']; ?>">
                                                <input type="hidden" name="points_change" value="10">
                                                <button type="submit" class="btn btn-sm table-action-btn action-blue manage-points-preset-text" title="Add 10 points">+10</button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="patient_id" value="<?php echo (int) $p['id']; ?>">
                                                <input type="hidden" name="points_change" value="-10">
                                                <button type="submit" class="btn btn-sm table-action-btn action-red manage-points-preset-text" title="Subtract 10 points">−10</button>
                                            </form>
                                            <form method="POST" class="d-inline-flex align-items-center gap-1 manage-points-custom-form">
                                                <input type="hidden" name="patient_id" value="<?php echo (int) $p['id']; ?>">
                                                <input type="number" name="points_change" class="form-control form-control-sm manage-points-custom-input" placeholder="±" step="1" required title="Positive or negative" aria-label="Custom points change">
                                                <button type="submit" class="btn btn-sm table-action-btn action-green" title="Apply custom amount" aria-label="Apply custom points">
                                                    <i class="fas fa-check" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="small text-muted mt-1">Positive or negative number</div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                    <div id="noResults" class="text-center text-muted py-5 border-top" style="display: none;">
                        <i class="fas fa-user-slash fa-2x mb-2 d-block opacity-50" aria-hidden="true"></i>
                        No patients found.
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card bills-dash-section-card mb-3 manage-points-side-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-gift me-2" aria-hidden="true"></i>Available rewards</h5>
                        </div>
                    </div>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($rewards)): ?>
                        <div class="list-group-item text-muted text-center py-4">No rewards configured.</div>
                    <?php else: ?>
                        <?php foreach ($rewards as $reward): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas <?php echo htmlspecialchars((string) $reward['icon']); ?> me-2 text-warning" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars((string) $reward['name']); ?>
                                </span>
                                <span class="badge bg-light text-dark border"><?php echo (int) $reward['points']; ?> pts</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent border-top small text-muted">
                    <i class="fas fa-info-circle me-1" aria-hidden="true"></i>Patients can redeem rewards at the front desk.
                </div>
            </div>

            <div class="card bills-dash-section-card manage-points-side-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-chart-line me-2" aria-hidden="true"></i>How to earn points</h5>
                        </div>
                    </div>
                </div>
                <div class="list-group list-group-flush">
                    <?php if (empty($earningRules)): ?>
                        <div class="list-group-item text-muted text-center py-4">No earning rules defined yet.</div>
                    <?php else: ?>
                        <?php foreach ($earningRules as $rule): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    <i class="fas <?php echo htmlspecialchars((string) $rule['icon']); ?> me-2 text-success" aria-hidden="true"></i>
                                    <?php echo htmlspecialchars((string) $rule['title']); ?>
                                </span>
                                <span class="badge bg-light text-success border">+<?php echo (int) $rule['points']; ?> pts</span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent border-top small text-muted">
                    <i class="fas fa-sliders-h me-1" aria-hidden="true"></i>Rules can be edited in Settings.
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <div class="card bills-dash-section-card manage-points-main-card" id="manage-points-subscriptions-card">
                <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
                    <div class="bills-arrivals-section-header__inner align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="fas fa-bell me-2" aria-hidden="true"></i>Subscription Reminders</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body border-bottom py-2">
                   
                </div>
                <div class="card-body appointments-table-wrap pt-0">
                    <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Contact</th>
                                <th class="text-center">Plan</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Days left</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subscriptionPatients)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No active or pending subscriptions found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($subscriptionPatients as $sp): ?>
                                    <tr class="<?php echo htmlspecialchars((string) $sp['row_class']); ?>">
                                        <td class="fw-semibold"><?php echo htmlspecialchars((string) $sp['full_name']); ?></td>
                                        <td class="small text-muted">
                                            <?php if ($sp['phone']): ?>
                                                <div><i class="fas fa-phone-alt me-1 opacity-75" aria-hidden="true"></i><?php echo htmlspecialchars((string) $sp['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($sp['email']): ?>
                                                <div><i class="fas fa-envelope me-1 opacity-75" aria-hidden="true"></i><?php echo htmlspecialchars((string) $sp['email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><span class="badge manage-points-sub-plan-badge"><?php echo ucfirst((string) $sp['subscription_type']); ?></span></td>
                                        <td><?php echo $sp['subscription_start_date'] ? htmlspecialchars(date('Y-m-d', strtotime((string) $sp['subscription_start_date']))) : '—'; ?></td>
                                        <td><?php echo $sp['subscription_end_date'] ? htmlspecialchars(date('Y-m-d', strtotime((string) $sp['subscription_end_date']))) : '—'; ?></td>
                                        <td>
                                            <?php if ($sp['days_left'] !== null): ?>
                                                <?php if ($sp['days_left'] < 0): ?>
                                                    <span class="text-danger">Expired (<?php echo abs((int) $sp['days_left']); ?> days ago)</span>
                                                <?php elseif ($sp['days_left'] == 0): ?>
                                                    <span class="text-warning">Expires today</span>
                                                <?php else: ?>
                                                    <?php echo (int) $sp['days_left']; ?> days
                                                <?php endif; ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $endRaw = $sp['subscription_end_date'] ?? '';
                                            $endValid = $endRaw && $endRaw !== '0000-00-00';
                                            $isExpired = $endValid && strtotime((string) $endRaw) < time();
                                            $daysLeft = $sp['days_left'];
                                            $expiringSoon = !$isExpired && $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 3;
                                            ?>
                                            <?php if ($isExpired): ?>
                                                <span class="badge manage-points-sub-status-expired">Expired</span>
                                            <?php elseif ($expiringSoon): ?>
                                                <span class="badge manage-points-sub-status-expiring">Expiring soon</span>
                                            <?php elseif ($sp['subscription_status'] == 'active'): ?>
                                                <span class="badge manage-points-sub-status-active">Active</span>
                                            <?php elseif ($sp['subscription_status'] == 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Pending</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst((string) $sp['subscription_status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <button type="button"
                                                    class="btn btn-sm btn-blue manage-points-sub-reminder-btn"
                                                    data-patient-id="<?php echo (int) $sp['id']; ?>"
                                                    data-patient-name="<?php echo htmlspecialchars((string) $sp['full_name'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <i class="fas fa-bell" aria-hidden="true"></i> Send reminder
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-top small text-muted">
                    <i class="fas fa-info-circle me-1" aria-hidden="true"></i>Yellow rows expire within 3 days; red rows are expired. Use the reminder action to notify patients.
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var searchEl = document.getElementById('searchPatient');
    if (!searchEl) return;
    searchEl.addEventListener('keyup', function () {
        var searchValue = this.value.toLowerCase();
        var rows = document.querySelectorAll('#patientTableBody tr');
        var visibleCount = 0;
        rows.forEach(function (row) {
            var patientName = row.getAttribute('data-name') || '';
            if (patientName.indexOf(searchValue) !== -1) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        var noResultsDiv = document.getElementById('noResults');
        if (noResultsDiv) {
            noResultsDiv.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    });
})();

document.querySelectorAll('.manage-points-sub-reminder-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id = this.getAttribute('data-patient-id');
        var name = this.getAttribute('data-patient-name') || '';
        if (!id || !confirm('Send subscription status reminder to ' + name + '?')) {
            return;
        }
        fetch('../api/send_subscription_reminder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ patient_id: parseInt(id, 10) })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                alert(data.message || (data.success ? 'Done.' : 'Request failed.'));
            })
            .catch(function () {
                alert('Network error while sending reminder.');
            });
    });
});
</script>

<?php include '../layouts/footer.php'; ?>
