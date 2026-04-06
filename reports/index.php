<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
Auth::requireRole('doctor'); // Only doctors and admins can view reports

$pageTitle = 'Reports & Analytics';

$db = Database::getInstance();

$reportType = $_GET['type'] ?? 'appointments';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$data = [];

switch ($reportType) {
    case 'appointments':
        // Appointments by status
        $data['byStatus'] = $db->fetchAll(
            "SELECT status, COUNT(*) as count FROM appointments 
             WHERE appointment_date BETWEEN ? AND ?
             GROUP BY status",
            [$startDate, $endDate],
            "ss"
        );
        // Appointments by doctor
        $data['byDoctor'] = $db->fetchAll(
            "SELECT u.full_name as doctor, COUNT(*) as count 
             FROM appointments a
             JOIN users u ON a.doctor_id = u.id
             WHERE a.appointment_date BETWEEN ? AND ?
             GROUP BY a.doctor_id",
            [$startDate, $endDate],
            "ss"
        );
        // Daily trend
        $data['daily'] = $db->fetchAll(
            "SELECT appointment_date, COUNT(*) as count 
             FROM appointments 
             WHERE appointment_date BETWEEN ? AND ?
             GROUP BY appointment_date
             ORDER BY appointment_date",
            [$startDate, $endDate],
            "ss"
        );
        break;

    case 'patients':
        // New patients by month
        $data['newPatients'] = $db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
             FROM patients 
             WHERE created_at BETWEEN ? AND ?
             GROUP BY month
             ORDER BY month",
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59'],
            "ss"
        );
        // Patients by insurance type
        $data['byInsurance'] = $db->fetchAll(
            "SELECT insurance_type, COUNT(*) as count 
             FROM patients 
             GROUP BY insurance_type"
        );
        break;

    case 'revenue':
        // Revenue by month
        $data['revenue'] = $db->fetchAll(
            "SELECT DATE_FORMAT(invoice_date, '%Y-%m') as month, 
                    SUM(total_amount) as total,
                    SUM(paid_amount) as paid
             FROM invoices 
             WHERE invoice_date BETWEEN ? AND ? AND payment_status != 'cancelled'
             GROUP BY month
             ORDER BY month",
            [$startDate, $endDate],
            "ss"
        );
        // Revenue by payment method
        $data['byMethod'] = $db->fetchAll(
            "SELECT p.payment_method, SUM(p.amount) as total
             FROM payments p
             JOIN invoices i ON p.invoice_id = i.id
             WHERE p.payment_date BETWEEN ? AND ?
             GROUP BY p.payment_method",
            [$startDate . ' 00:00:00', $endDate . ' 23:59:59'],
            "ss"
        );
        break;
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4">Reports & Analytics</h1>

    <!-- Report Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" name="type">
                        <option value="appointments" <?php echo $reportType == 'appointments' ? 'selected' : ''; ?>>Appointments</option>
                        <option value="patients" <?php echo $reportType == 'patients' ? 'selected' : ''; ?>>Patients</option>
                        <option value="revenue" <?php echo $reportType == 'revenue' ? 'selected' : ''; ?>>Revenue</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Display -->
    <div class="row">
        <?php if ($reportType == 'appointments'): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Appointments by Status</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Appointments by Doctor</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="doctorChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-12 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Daily Appointment Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                // Status Chart
                const statusCtx = document.getElementById('statusChart').getContext('2d');
                new Chart(statusCtx, {
                    type: 'pie',
                    data: {
                        labels: [<?php foreach ($data['byStatus'] as $row) echo "'" . ucfirst($row['status']) . "',"; ?>],
                        datasets: [{
                            data: [<?php foreach ($data['byStatus'] as $row) echo $row['count'] . ","; ?>],
                            backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#dc3545', '#6c757d']
                        }]
                    }
                });

                // Doctor Chart
                const doctorCtx = document.getElementById('doctorChart').getContext('2d');
                new Chart(doctorCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php foreach ($data['byDoctor'] as $row) echo "'" . $row['doctor'] . "',"; ?>],
                        datasets: [{
                            label: 'Appointments',
                            data: [<?php foreach ($data['byDoctor'] as $row) echo $row['count'] . ","; ?>],
                            backgroundColor: '#3498db'
                        }]
                    }
                });

                // Daily Chart
                const dailyCtx = document.getElementById('dailyChart').getContext('2d');
                new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: [<?php foreach ($data['daily'] as $row) echo "'" . $row['appointment_date'] . "',"; ?>],
                        datasets: [{
                            label: 'Appointments',
                            data: [<?php foreach ($data['daily'] as $row) echo $row['count'] . ","; ?>],
                            borderColor: '#3498db',
                            tension: 0.1
                        }]
                    }
                });
            </script>

        <?php elseif ($reportType == 'patients'): ?>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">New Patients by Month</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="newPatientsChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Patients by Insurance</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="insuranceChart"></canvas>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                const newCtx = document.getElementById('newPatientsChart').getContext('2d');
                new Chart(newCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php foreach ($data['newPatients'] as $row) echo "'" . $row['month'] . "',"; ?>],
                        datasets: [{
                            label: 'New Patients',
                            data: [<?php foreach ($data['newPatients'] as $row) echo $row['count'] . ","; ?>],
                            backgroundColor: '#28a745'
                        }]
                    }
                });

                const insuranceCtx = document.getElementById('insuranceChart').getContext('2d');
                new Chart(insuranceCtx, {
                    type: 'pie',
                    data: {
                        labels: [<?php foreach ($data['byInsurance'] as $row) echo "'" . ($row['insurance_type'] ?: 'None') . "',"; ?>],
                        datasets: [{
                            data: [<?php foreach ($data['byInsurance'] as $row) echo $row['count'] . ","; ?>],
                            backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#2ecc71', '#95a5a6']
                        }]
                    }
                });
            </script>

        <?php elseif ($reportType == 'revenue'): ?>
            <div class="col-md-8 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Monthly Revenue</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Revenue by Payment Method</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="methodChart"></canvas>
                    </div>
                </div>
            </div>

            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                const revenueCtx = document.getElementById('revenueChart').getContext('2d');
                new Chart(revenueCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php foreach ($data['revenue'] as $row) echo "'" . $row['month'] . "',"; ?>],
                        datasets: [
                            {
                                label: 'Total Billed',
                                data: [<?php foreach ($data['revenue'] as $row) echo $row['total'] . ","; ?>],
                                backgroundColor: '#3498db'
                            },
                            {
                                label: 'Paid',
                                data: [<?php foreach ($data['revenue'] as $row) echo $row['paid'] . ","; ?>],
                                backgroundColor: '#2ecc71'
                            }
                        ]
                    }
                });

                const methodCtx = document.getElementById('methodChart').getContext('2d');
                new Chart(methodCtx, {
                    type: 'pie',
                    data: {
                        labels: [<?php foreach ($data['byMethod'] as $row) echo "'" . ucfirst($row['payment_method']) . "',"; ?>],
                        datasets: [{
                            data: [<?php foreach ($data['byMethod'] as $row) echo $row['total'] . ","; ?>],
                            backgroundColor: ['#3498db', '#e74c3c', '#f39c12', '#2ecc71', '#95a5a6']
                        }]
                    }
                });
            </script>
        <?php endif; ?>
    </div>
</div>

<?php include '../layouts/footer.php'; ?>