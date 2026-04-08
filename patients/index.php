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

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Patients</h1>
        <a href="add.php" class="btn btn-primary">
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
            <div class="table-responsive">
                <table class="table table-hover">
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
                                        <div class="btn-group" role="group">
                                            <a href="view.php?id=<?php echo $patient['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $patient['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
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
