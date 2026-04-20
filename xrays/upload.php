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

<div class="container-fluid bills-page xrays-upload-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-x-ray me-2 opacity-90" aria-hidden="true"></i>Upload X-Ray
                </h2>
                <p class="mb-0 opacity-90">Attach imaging files and clinical notes for <?php echo htmlspecialchars($patient['full_name']); ?>.</p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end">
                <a href="../patients/view.php?id=<?php echo $patientId; ?>" class="btn btn-secondary xrays-upload-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Patient
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card bills-dash-section-card xrays-upload-summary-card mb-4">
        <div class="card-header bills-arrivals-header bills-arrivals-header--subscriptions border-0">
            <div class="bills-arrivals-section-header__inner align-items-center">
                <div>
                    <h5 class="card-title mb-0"><i class="fas fa-user me-2" aria-hidden="true"></i>Patient summary</h5>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12 mb-2 mb-md-0">
                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($patient['full_name']); ?></p>
                    <p class="mb-0 text-muted small">Allowed files: JPG, PNG, GIF, PDF</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card bills-dash-section-card xrays-upload-form-card">
        <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
            <div class="bills-arrivals-section-header__inner align-items-center">
                <div>
                    <h5 class="card-title mb-0"><i class="fas fa-file-medical me-2" aria-hidden="true"></i>X-Ray details</h5>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <ul class="nav nav-tabs xrays-upload-tabs-nav mb-4" id="xrayUploadTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="upload-details-tab" data-bs-toggle="tab" data-bs-target="#upload-details" type="button" role="tab">
                            Upload details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="upload-notes-tab" data-bs-toggle="tab" data-bs-target="#upload-notes" type="button" role="tab">
                            Clinical notes
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="upload-details" role="tabpanel">
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
                        </div>
                    </div>

                    <div class="tab-pane fade" id="upload-notes" role="tabpanel">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <label class="form-label">Findings</label>
                                <textarea class="form-control" name="findings" rows="3"></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 xrays-upload-actions">
                    <a href="../patients/view.php?id=<?php echo $patientId; ?>" class="btn btn-secondary xrays-upload-action-btn">Cancel</a>
                    <button type="submit" class="btn-green xrays-upload-action-btn">Upload X-Ray</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>