<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
if ($_SESSION['role'] != 'patient') {
    header('Location: ../dashboard.php');
    exit;
}

$db = Database::getInstance();
$userId = Auth::userId();
$patientId = getPatientIdFromUserId($userId);

if (!$patientId) {
    die("Patient record not found.");
}

// Get patient info
$patient = $db->fetchOne("SELECT full_name FROM patients WHERE id = ?", [$patientId], "i");

$pageTitle = 'My Teeth';
include '../layouts/header.php';
?>

<style>
.teeth-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    padding: 30px;
    margin-bottom: 30px;
    color: white;
}

.tooth-chart-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.info-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    margin-right: 15px;
    margin-bottom: 10px;
}

.legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
    margin-right: 8px;
}

.legend-healthy { background: #28a745; }
.legend-cavity { background: #dc3545; }
.legend-filled { background: #ffc107; }
.legend-crown { background: #17a2b8; }
.legend-root-canal { background: #6f42c1; }
.legend-missing { background: #6c757d; }
.legend-implant { background: #20c997; }
</style>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Teeth Header -->
    <div class="teeth-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2 class="mb-2">
                    <i class="fas fa-tooth"></i> My Dental Chart
                </h2>
                <p class="mb-0">View your complete dental history and treatment records</p>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <div class="bg-white text-dark rounded p-2">
                    <small>Patient</small>
                    <h5 class="mb-0"><?php echo htmlspecialchars($patient['full_name']); ?></h5>
                </div>
            </div>
        </div>
    </div>

    <!-- Info Alert -->
    <div class="alert alert-info alert-custom mb-4">
        <i class="fas fa-info-circle"></i> 
        <strong>View Only Mode</strong> - This chart shows your dental records. For updates, please consult with your dentist during your next visit.
    </div>

    <div class="row">
        <div class="col-lg-9">
            <!-- Tooth Chart -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-simple"></i> Dental Chart
                    </h5>
                    <small class="text-muted">Click on any tooth to view details</small>
                </div>
                <div class="card-body">
                    <div id="tooth-chart-container" class="tooth-chart-container text-center">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading tooth chart...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading your dental chart...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <!-- Legend -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-palette"></i> Legend
                    </h5>
                </div>
                <div class="card-body">
                    <div class="legend-item">
                        <div class="legend-color legend-healthy"></div>
                        <span>Healthy</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-cavity"></div>
                        <span>Cavity / Decay</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-filled"></div>
                        <span>Filled / Restored</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-crown"></div>
                        <span>Crown / Cap</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-root-canal"></div>
                        <span>Root Canal</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-missing"></div>
                        <span>Missing</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-implant"></div>
                        <span>Implant</span>
                    </div>
                </div>
            </div>

            <!-- Dental Tips -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lightbulb text-warning"></i> Dental Care Tips
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-card">
                        <i class="fas fa-brush text-primary"></i>
                        <strong>Brush twice daily</strong>
                        <p class="small text-muted mb-0 mt-1">Use fluoride toothpaste and brush for at least 2 minutes</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-floss text-primary"></i>
                        <strong>Floss daily</strong>
                        <p class="small text-muted mb-0 mt-1">Flossing removes plaque between teeth where brush can't reach</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-apple-alt text-primary"></i>
                        <strong>Healthy diet</strong>
                        <p class="small text-muted mb-0 mt-1">Limit sugary foods and drinks that cause cavities</p>
                    </div>
                    <div class="info-card">
                        <i class="fas fa-calendar-check text-primary"></i>
                        <strong>Regular checkups</strong>
                        <p class="small text-muted mb-0 mt-1">Visit your dentist every 6 months for professional cleaning</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tooth Chart Modal - Read Only -->
<div class="modal fade" id="toothModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">
                    <i class="fas fa-tooth"></i> Tooth <span id="modal-tooth-number"></span> Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Status</label>
                    <p id="tooth-status-display" class="form-control-plaintext bg-light p-2 rounded"></p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Diagnosis</label>
                    <p id="tooth-diagnosis-display" class="form-control-plaintext bg-light p-2 rounded">No diagnosis recorded</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Treatment</label>
                    <p id="tooth-treatment-display" class="form-control-plaintext bg-light p-2 rounded">No treatment recorded</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Notes</label>
                    <p id="tooth-notes-display" class="form-control-plaintext bg-light p-2 rounded">No notes available</p>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Last Updated</label>
                    <p id="tooth-updated-display" class="form-control-plaintext bg-light p-2 rounded">-</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo url('assets/js/tooth-chart.js'); ?>"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooth chart in read-only mode
        if (typeof toothChart !== 'undefined') {
            toothChart.init(<?php echo $patientId; ?>, true);
        } else {
            console.error('Tooth chart library not loaded');
            // Fallback message
            document.getElementById('tooth-chart-container').innerHTML = `
                <div class="text-center py-5">
                    <i class="fas fa-chart-simple fa-4x text-muted mb-3"></i>
                    <p class="text-muted">Dental chart is currently unavailable.</p>
                    <p class="text-muted">Please consult with your dentist for your dental records.</p>
                </div>
            `;
        }
    });
</script>

<?php include '../layouts/footer.php'; ?>