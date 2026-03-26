<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = 'Waiting Queue';

$db = Database::getInstance();

// Get today's daily queue
$today = date('Y-m-d');
$dailyQueue = $db->fetchAll(
    "SELECT wq.*, COALESCE(p.full_name, wq.patient_name) as patient_name 
     FROM waiting_queue wq
     LEFT JOIN patients p ON wq.patient_id = p.id
     WHERE wq.queue_type = 'daily' AND wq.status != 'checked-in' AND wq.status != 'cancelled'
     ORDER BY 
        CASE wq.priority 
            WHEN 'emergency' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END, wq.joined_at",
    []
);

// Get weekly queue (future appointments not scheduled yet)
$weeklyQueue = $db->fetchAll(
    "SELECT wq.*, COALESCE(p.full_name, wq.patient_name) as patient_name 
     FROM waiting_queue wq
     LEFT JOIN patients p ON wq.patient_id = p.id
     WHERE wq.queue_type = 'weekly' AND wq.status = 'waiting'
     ORDER BY wq.preferred_day, wq.joined_at",
    []
);

// Handle add to queue form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_queue'])) {
    $patientId = $_POST['patient_id'] ?: null;
    $patientName = $_POST['patient_name'] ?: null;
    $queueType = $_POST['queue_type'];
    $priority = $_POST['priority'];
    $reason = $_POST['reason'];
    $preferredTreatment = $_POST['preferred_treatment'];
    $preferredDay = $_POST['preferred_day'] ?? null;

    $db->insert(
        "INSERT INTO waiting_queue 
         (patient_id, patient_name, queue_type, priority, reason, preferred_treatment, preferred_day, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, 'waiting')",
        [$patientId, $patientName, $queueType, $priority, $reason, $preferredTreatment, $preferredDay],
        "issssss"
    );

    header('Location: index.php');
    exit;
}

// Handle status update (notify, check-in, cancel)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    $newStatus = '';
    $updateField = '';

    switch ($action) {
        case 'notify':
            $newStatus = 'notified';
            $updateField = 'notified_at = NOW()';
            break;
        case 'checkin':
            $newStatus = 'checked-in';
            $updateField = 'checked_in_at = NOW()';
            break;
        case 'cancel':
            $newStatus = 'cancelled';
            $updateField = '';
            break;
    }

    if ($newStatus) {
        $sql = "UPDATE waiting_queue SET status = '$newStatus'";
        if ($updateField) {
            $sql .= ", $updateField";
        }
        $sql .= " WHERE id = $id";
        $db->execute($sql);
    }

    header('Location: index.php');
    exit;
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Waiting Queue</h1>

    <div class="row">
        <!-- Add to Queue Form -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Add to Queue</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Patient (select existing)</label>
                            <select class="form-select" name="patient_id" id="patientSelect" onchange="togglePatientName()">
                                <option value="">-- Walk-in / New Patient --</option>
                                <?php
                                $patients = $db->fetchAll("SELECT id, full_name FROM patients ORDER BY full_name");
                                foreach ($patients as $p):
                                ?>
                                    <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3" id="walkinNameDiv">
                            <label class="form-label">Name (for walk-in)</label>
                            <input type="text" class="form-control" name="patient_name" placeholder="Enter full name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Queue Type</label>
                            <select class="form-select" name="queue_type" id="queueType" onchange="togglePreferredDay()">
                                <option value="daily">Daily (Today)</option>
                                <option value="weekly">Weekly (Future)</option>
                            </select>
                        </div>
                        <div class="mb-3" id="preferredDayDiv" style="display: none;">
                            <label class="form-label">Preferred Day</label>
                            <select class="form-select" name="preferred_day">
                                <option value="Monday">Monday</option>
                                <option value="Tuesday">Tuesday</option>
                                <option value="Wednesday">Wednesday</option>
                                <option value="Thursday">Thursday</option>
                                <option value="Friday">Friday</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <input type="text" class="form-control" name="reason" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preferred Treatment</label>
                            <input type="text" class="form-control" name="preferred_treatment">
                        </div>
                        <button type="submit" name="add_to_queue" class="btn btn-primary w-100">
                            Add to Queue
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Queue Display -->
        <div class="col-md-8">
            <!-- Daily Queue -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Daily Queue (Today)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($dailyQueue)): ?>
                        <p class="text-muted">No patients in daily queue.</p>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Patient</th>
                                    <th>Priority</th>
                                    <th>Reason</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dailyQueue as $index => $entry): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php
                                        if ($entry['patient_id']) {
                                            echo '<a href="../patients/view.php?id=' . $entry['patient_id'] . '">' . htmlspecialchars($entry['patient_name'] ?? 'Unknown') . '</a>';
                                        } else {
                                           echo htmlspecialchars($entry['patient_name'] ?? 'Walk-in');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $priorityColors = [
                                            'emergency' => 'danger',
                                            'high' => 'warning',
                                            'medium' => 'info',
                                            'low' => 'secondary'
                                        ];
                                        $color = $priorityColors[$entry['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($entry['priority']); ?></span>
                                    </td>
                                    <td><?php echo $entry['reason']; ?></td>
                                    <td><?php echo formatTime($entry['joined_at']); ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'waiting' => 'secondary',
                                            'notified' => 'info',
                                            'checked-in' => 'success',
                                            'cancelled' => 'danger'
                                        ];
                                        $sColor = $statusColors[$entry['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $sColor; ?>"><?php echo ucfirst($entry['status']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($entry['status'] == 'waiting'): ?>
                                            <a href="?action=notify&id=<?php echo $entry['id']; ?>" class="btn btn-sm btn-info" title="Notify Patient">
                                                <i class="fas fa-bell"></i>
                                            </a>
                                            <a href="?action=checkin&id=<?php echo $entry['id']; ?>" class="btn btn-sm btn-success" title="Check In">
                                                <i class="fas fa-sign-in-alt"></i>
                                            </a>
                                            <a href="?action=cancel&id=<?php echo $entry['id']; ?>" class="btn btn-sm btn-danger" title="Cancel" onclick="return confirm('Cancel this queue entry?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                        <?php elseif ($entry['status'] == 'notified'): ?>
                                            <a href="?action=checkin&id=<?php echo $entry['id']; ?>" class="btn btn-sm btn-success">Check In</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Weekly Queue -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">Weekly Queue (Future)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($weeklyQueue)): ?>
                        <p class="text-muted">No patients in weekly queue.</p>
                    <?php else: ?>
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Preferred Day</th>
                                    <th>Priority</th>
                                    <th>Reason</th>
                                    <th>Treatment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($weeklyQueue as $entry): ?>
                                <tr>
                                    <td>
                                        <?php
                                        if ($entry['patient_id']) {
                                            echo '<a href="../patients/view.php?id=' . $entry['patient_id'] . '">' . htmlspecialchars($entry['patient_name'] ?? 'Unknown') . '</a>';
                                        } else {
                                            echo htmlspecialchars($entry['patient_name'] ?? 'Walk-in');
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo $entry['preferred_day']; ?></td>
                                    <td>
                                        <?php
                                        $priorityColors = [
                                            'emergency' => 'danger',
                                            'high' => 'warning',
                                            'medium' => 'info',
                                            'low' => 'secondary'
                                        ];
                                        $color = $priorityColors[$entry['priority']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($entry['priority']); ?></span>
                                    </td>
                                    <td><?php echo $entry['reason']; ?></td>
                                    <td><?php echo $entry['preferred_treatment']; ?></td>
                                    <td>
                                        <a href="?action=cancel&id=<?php echo $entry['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Remove from queue?')">
                                            <i class="fas fa-times"></i> Remove
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePatientName() {
    const patientSelect = document.getElementById('patientSelect');
    const walkinDiv = document.getElementById('walkinNameDiv');
    if (patientSelect.value) {
        walkinDiv.style.display = 'none';
    } else {
        walkinDiv.style.display = 'block';
    }
}

function togglePreferredDay() {
    const queueType = document.getElementById('queueType').value;
    const preferredDayDiv = document.getElementById('preferredDayDiv');
    preferredDayDiv.style.display = queueType === 'weekly' ? 'block' : 'none';
}

// Initialize on page load
togglePatientName();
togglePreferredDay();
</script>

<?php include '../layouts/footer.php'; ?>