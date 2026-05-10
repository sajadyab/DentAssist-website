<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();

if (Auth::hasRole('patient')) {
    header('Location: ../index.php');
    exit;
}

$xrayId = (int) ($_GET['id'] ?? 0);
$patientId = (int) ($_GET['patient_id'] ?? 0);

$xray = repo_xray_find_by_id_for_patient($xrayId, $patientId);
if (!$xray) {
    header('Location: ../patients/index.php');
    exit;
}

$patient = Database::getInstance()->fetchOne("SELECT id, full_name FROM patients WHERE id = ?", [$patientId], "i");
if (!$patient) {
    header('Location: ../patients/index.php');
    exit;
}

$pageTitle = 'Edit X-Ray for ' . $patient['full_name'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $xrayType = trim((string) ($_POST['xray_type'] ?? ''));
    $toothNumbers = trim((string) ($_POST['tooth_numbers'] ?? ''));
    $findings = trim((string) ($_POST['findings'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($xrayType === '') {
        $error = 'X-Ray type is required.';
    } else {
        $ok = repo_xray_update_by_id_for_patient($xrayId, $patientId, [
            'xray_type' => $xrayType,
            'tooth_numbers' => $toothNumbers !== '' ? $toothNumbers : null,
            'findings' => $findings !== '' ? $findings : null,
            'notes' => $notes !== '' ? $notes : null,
        ]);

        if ($ok) {
            $success = 'X-Ray updated successfully.';
            $xray = repo_xray_find_by_id_for_patient($xrayId, $patientId) ?? $xray;
        } else {
            $error = 'Failed to update X-Ray.';
        }
    }
}

include '../layouts/header.php';
?>

<div class="container-fluid bills-page xrays-upload-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12 col-lg-8">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-edit me-2 opacity-90" aria-hidden="true"></i>Edit X-Ray
                </h2>
                <p class="mb-0 opacity-90">Update imaging details and notes for <?php echo htmlspecialchars($patient['full_name']); ?>.</p>
            </div>
            <div class="col-12 col-lg-4 mt-3 mt-lg-0 d-flex justify-content-center justify-content-lg-end">
                <a href="../patients/view.php?id=<?php echo $patientId; ?>#xrays" class="btn btn-secondary xrays-upload-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Patient
                </a>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card bills-dash-section-card xrays-upload-form-card">
        <div class="card-header bills-arrivals-header bills-arrivals-header--payment border-0">
            <div class="bills-arrivals-section-header__inner align-items-center">
                <div>
                    <h5 class="card-title mb-0"><i class="fas fa-file-medical me-2" aria-hidden="true"></i>X-Ray details</h5>
                </div>
            </div>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">X-Ray Type</label>
                        <select class="form-select" name="xray_type" required>
                            <?php foreach (['Panoramic', 'Bitewing', 'Periapical', 'CBCT', 'Intraoral', 'Other'] as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo (($xray['xray_type'] ?? '') === $type) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tooth Numbers</label>
                           <input type="text" class="form-control form-control-modern" id="tpAddTeeth" name="tooth_numbers"
                                       value="<?php echo htmlspecialchars((string) ($xray['tooth_numbers'] ?? '')); ?>"
                                       inputmode="numeric" pattern="[0-9]+(?:,[0-9]+)*" title="Enter comma-separated tooth numbers from 0 to 32, e.g. 18,19,20"
                                       oninput="this.value = this.value.replace(/[^0-9,]/g, '').replace(/,{2,}/g, ',').replace(/^,|,$/g, '').split(',').map(function(v){return v.trim();}).filter(function(v){return v !== '' && /^[0-9]+$/.test(v) && parseInt(v,10) >= 0 && parseInt(v,10) <= 32;}).map(function(v){return String(parseInt(v,10));}).join(',');"
                                       placeholder="e.g., 18,19,20">
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Findings</label>
                        <textarea class="form-control" name="findings" rows="4"><?php echo htmlspecialchars((string) ($xray['findings'] ?? '')); ?></textarea>
                    </div>
                    <div class="col-12 mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"><?php echo htmlspecialchars((string) ($xray['notes'] ?? '')); ?></textarea>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 xrays-upload-actions">
                    <a href="../patients/view.php?id=<?php echo $patientId; ?>#xrays" class="btn btn-secondary xrays-upload-action-btn">Cancel</a>
                    <button type="submit" class="btn-green xrays-upload-action-btn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>
