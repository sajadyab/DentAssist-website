<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$patientId = $_GET['patient_id'] ?? 0;

$patient = $db->fetchOne("SELECT id, full_name FROM patients WHERE id = ?", [$patientId], "i");

if (!$patient) {
    header('Location: ../patients/index.php');
    exit;
}

$pageTitle = 'Upload X-Ray for ' . $patient['full_name'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['xray_file'])) {
    $file = $_FILES['xray_file'];
    $uploadDir = UPLOAD_PATH . 'xrays/';

    // Create directory if not exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $result = uploadFile($file, $uploadDir, ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);

    if ($result['success']) {
        $db->insert(
            "INSERT INTO xrays (patient_id, file_name, file_path, file_size, mime_type, xray_type, findings, notes, uploaded_by) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $patientId,
                $result['filename'],
                $result['path'],
                $file['size'],
                $file['type'],
                $_POST['xray_type'],
                $_POST['findings'] ?? null,
                $_POST['notes'] ?? null,
                Auth::userId()
            ],
            "ississssi"
        );
        $success = 'X-Ray uploaded successfully';
    } else {
        $error = $result['message'];
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Upload X-Ray for <?php echo htmlspecialchars($patient['full_name']); ?></h1>
        <a href="../patients/view.php?id=<?php echo $patientId; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Patient
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">X-Ray Type</label>
                        <select class="form-select" name="xray_type" required>
                            <option value="Panoramic">Panoramic</option>
                            <option value="Bitewing">Bitewing</option>
                            <option value="Periapical">Periapical</option>
                            <option value="CBCT">CBCT</option>
                            <option value="Intraoral">Intraoral</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">File</label>
                        <input type="file" class="form-control" name="xray_file" accept="image/*,application/pdf" required>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Tooth Numbers (comma separated)</label>
                        <input type="text" class="form-control" name="tooth_numbers" placeholder="e.g., 18,19,20">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Findings</label>
                        <textarea class="form-control" name="findings" rows="3"></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Upload X-Ray</button>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>