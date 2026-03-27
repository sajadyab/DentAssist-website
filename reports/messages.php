<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'staff') {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();

// Handle sending messages
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'send_message') {
        $patientId = intval($_POST['patient_id'] ?? 0);
        $messageType = $_POST['message_type'] ?? '';
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $deliveryMethod = $_POST['delivery_method'] ?? 'web';
        $scheduledAt = !empty($_POST['scheduled_at']) ? $_POST['scheduled_at'] : null;

        if ($patientId && $messageType && $message) {
            $db->execute(
                "INSERT INTO messages (patient_id, message_type, subject, message, delivery_method, scheduled_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$patientId, $messageType, $subject, $message, $deliveryMethod, $scheduledAt, Auth::userId()],
                "issssss"
            );

            // If it's a web message and no scheduled time, send immediately
            if ($deliveryMethod == 'web' && !$scheduledAt) {
                // Mark as sent immediately for web notifications
                $messageId = $db->lastInsertId();
                $db->execute(
                    "UPDATE messages SET status = 'sent', sent_at = NOW() WHERE id = ?",
                    [$messageId],
                    "i"
                );
            }

            $success = 'Message sent successfully.';
        } else {
            $error = 'Please fill in all required fields.';
        }
    } elseif ($action == 'send_treatment_instructions') {
        $appointmentId = intval($_POST['appointment_id'] ?? 0);
        $treatmentType = $_POST['treatment_type'] ?? '';

        if ($appointmentId && $treatmentType) {
            // Get appointment details
            $appointment = $db->fetchOne(
                "SELECT a.*, p.first_name, p.last_name FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.id = ?",
                [$appointmentId],
                "i"
            );

            if ($appointment) {
                // Get treatment instructions
                $instructions = $db->fetchOne(
                    "SELECT * FROM treatment_instructions WHERE treatment_type = ? AND is_active = 1",
                    [$treatmentType],
                    "s"
                );

                if ($instructions) {
                    $subject = $instructions['title'];
                    $message = $instructions['instructions'];

                    // Calculate send date based on duration_days
                    $sendDate = date('Y-m-d H:i:s', strtotime($appointment['appointment_date'] . ' ' . $appointment['appointment_time']) + ($instructions['duration_days'] * 24 * 60 * 60));

                    $db->execute(
                        "INSERT INTO messages (patient_id, message_type, subject, message, delivery_method, scheduled_at, created_by) VALUES (?, 'treatment_instructions', ?, ?, 'web', ?, ?)",
                        [$appointment['patient_id'], $subject, $message, $sendDate, Auth::userId()],
                        "issssi"
                    );

                    $success = 'Treatment instructions scheduled to be sent.';
                } else {
                    $error = 'No instructions found for this treatment type.';
                }
            } else {
                $error = 'Appointment not found.';
            }
        }
    }
}

// Get recent messages
$recentMessages = $db->fetchAll(
    "SELECT m.*, p.first_name, p.last_name FROM messages m JOIN patients p ON m.patient_id = p.id ORDER BY m.created_at DESC LIMIT 20"
);

// Get pending messages
$pendingMessages = $db->fetchAll(
    "SELECT m.*, p.first_name, p.last_name FROM messages m JOIN patients p ON m.patient_id = p.id WHERE m.status = 'pending' AND (m.scheduled_at IS NULL OR m.scheduled_at <= NOW()) ORDER BY m.created_at DESC"
);

// Get patients for dropdown
$patients = $db->fetchAll("SELECT id, first_name, last_name FROM patients ORDER BY first_name, last_name");

$pageTitle = 'Message Center';
include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Message Center</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#sendMessageModal">
            Send New Message
        </button>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Send Treatment Instructions -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Send Treatment Instructions</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="send_treatment_instructions">
                        <div class="mb-3">
                            <label for="appointment_id" class="form-label">Select Recent Appointment</label>
                            <select class="form-select" id="appointment_id" name="appointment_id" required>
                                <option value="">Choose appointment</option>
                                <?php
                                $recentAppointments = $db->fetchAll(
                                    "SELECT a.id, a.appointment_date, a.appointment_time, a.treatment_type, p.first_name, p.last_name FROM appointments a JOIN patients p ON a.patient_id = p.id WHERE a.appointment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND a.status = 'completed' ORDER BY a.appointment_date DESC, a.appointment_time DESC"
                                );
                                foreach ($recentAppointments as $apt):
                                ?>
                                    <option value="<?php echo $apt['id']; ?>" data-treatment="<?php echo $apt['treatment_type']; ?>">
                                        <?php echo htmlspecialchars($apt['first_name'] . ' ' . $apt['last_name']); ?> -
                                        <?php echo formatDate($apt['appointment_date']); ?> <?php echo $apt['appointment_time']; ?> -
                                        <?php echo ucfirst(str_replace('_', ' ', $apt['treatment_type'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="treatment_type" class="form-label">Treatment Type</label>
                            <input type="text" class="form-control" id="treatment_type" name="treatment_type" readonly>
                        </div>
                        <button type="submit" class="btn btn-success">Send Treatment Instructions</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Pending Messages -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5>Pending Messages (<?php echo count($pendingMessages); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingMessages)): ?>
                        <p class="text-muted">No pending messages.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($pendingMessages as $msg): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?></h6>
                                        <small><?php echo ucfirst($msg['message_type']); ?></small>
                                    </div>
                                    <p class="mb-1"><?php echo htmlspecialchars(substr($msg['subject'], 0, 50)); ?>...</p>
                                    <small class="text-muted">
                                        <?php echo $msg['delivery_method']; ?>
                                        <?php if ($msg['scheduled_at']): ?>
                                            - Scheduled: <?php echo formatDateTime($msg['scheduled_at']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Messages -->
    <div class="card">
        <div class="card-header">
            <h5>Recent Messages</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Sent At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentMessages as $msg): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($msg['first_name'] . ' ' . $msg['last_name']); ?></td>
                                <td><?php echo ucfirst(str_replace('_', ' ', $msg['message_type'])); ?></td>
                                <td><?php echo htmlspecialchars(substr($msg['subject'], 0, 30)); ?>...</td>
                                <td><?php echo ucfirst($msg['delivery_method']); ?></td>
                                <td>
                                    <span class="badge bg-<?php
                                        echo $msg['status'] == 'sent' ? 'success' :
                                             ($msg['status'] == 'delivered' ? 'info' :
                                             ($msg['status'] == 'failed' ? 'danger' : 'warning'));
                                    ?>">
                                        <?php echo ucfirst($msg['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $msg['sent_at'] ? formatDateTime($msg['sent_at']) : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Send Message Modal -->
<div class="modal fade" id="sendMessageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send New Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="patient_id" class="form-label">Patient *</label>
                                <select class="form-select" id="patient_id" name="patient_id" required>
                                    <option value="">Select patient</option>
                                    <?php foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>">
                                            <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="message_type" class="form-label">Message Type *</label>
                                <select class="form-select" id="message_type" name="message_type" required>
                                    <option value="general">General</option>
                                    <option value="appointment_reminder">Appointment Reminder</option>
                                    <option value="payment_reminder">Payment Reminder</option>
                                    <option value="treatment_instructions">Treatment Instructions</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" class="form-control" id="subject" name="subject">
                    </div>

                    <div class="mb-3">
                        <label for="message" class="form-label">Message *</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="delivery_method" class="form-label">Delivery Method</label>
                                <select class="form-select" id="delivery_method" name="delivery_method">
                                    <option value="web">Web Notification</option>
                                    <option value="email">Email</option>
                                    <option value="sms">SMS</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="scheduled_at" class="form-label">Schedule For (Optional)</label>
                                <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Message</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update treatment type when appointment is selected
document.getElementById('appointment_id').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const treatmentType = selectedOption.getAttribute('data-treatment');
    document.getElementById('treatment_type').value = treatmentType || '';
});
</script>

<?php include '../layouts/footer.php'; ?>