<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../api/_helpers.php';

Auth::requireLogin();
$pageTitle = 'Patients';

// Search and pagination
$search = $_GET['search'] ?? '';
$page = (int) ($_GET['page'] ?? 1);
if ($page < 1) {
    $page = 1;
}
$limit = 10;
$offset = ($page - 1) * $limit;

$total = repo_patient_count_by_search((string) $search);
$totalPages = ceil($total / $limit);
$patients = repo_patient_search((string) $search, $limit, $offset);

include '../layouts/header.php';
?>

<div class="container-fluid bills-page patients-index-page">
    <div class="bills-queue-header">
        <div class="row align-items-center bills-queue-header-inner">
            <div class="col-12">
                <h2 class="mb-2 fw-bold">
                    <i class="fas fa-users me-2 opacity-90" aria-hidden="true"></i>Patients
                </h2>
                <p class="mb-0 opacity-90">Search, open records, and manage everyone registered at the clinic.</p>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center justify-content-md-end mb-4">
        <a href="add.php" class="btn-green staff-cta-mobile-90">
            <i class="fas fa-plus"></i> Add New Patient
        </a>
    </div>
    
    <!-- Search -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-10">
                    <input type="text" 
                           class="form-control" 
                           name="search" 
                           placeholder="Search by name, email, or phone..."
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Patients Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive patients-table-wrap">
                <table class="table table-hover table-sm patients-index-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Contact</th>
                            <th>Insurance</th>
                            <th>Last Visit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($patients)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No patients found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td>#<?php echo $patient['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($patient['full_name']); ?></strong><br>
                                        <small class="text-muted">
                                            DOB: <?php echo formatDate($patient['date_of_birth'], 'M d, Y'); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <i class="fas fa-phone"></i> <?php echo $patient['phone']; ?><br>
                                        <small><i class="fas fa-envelope"></i> <?php echo $patient['email']; ?></small>
                                    </td>
                                    <td>
                                        <?php echo $patient['insurance_provider'] ?? 'None'; ?><br>
                                        <small><?php echo $patient['insurance_type'] ?? '-'; ?></small>
                                    </td>
                                    <td>
                                        <?php echo patientHasLastVisitDate($patient['last_visit_date'] ?? null)
                                            ? htmlspecialchars(formatDate(normalizePatientOptionalDate($patient['last_visit_date'] ?? null)))
                                            : 'No visits'; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group table-card-actions" role="group">
                                            <a href="view.php?id=<?php echo $patient['id']; ?>" 
                                               class="btn btn-sm btn-info table-action-btn action-blue" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $patient['id']; ?>" 
                                               class="btn btn-sm btn-warning table-action-btn action-yellow" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger table-action-btn action-red" 
                                                    onclick="deletePatient(<?php echo $patient['id']; ?>)"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function deletePatient(id) {
    if (confirm('Are you sure you want to delete this patient?')) {
        // Implement delete via AJAX
       fetch('../api/delete_patient.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({id: id})
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error deleting patient');
            }
        });
    }
}
</script>
<?php include '../layouts/footer.php'; ?>
