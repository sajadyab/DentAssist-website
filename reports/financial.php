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

// Get current month/year for filtering
$currentMonth = date('m');
$currentYear = date('Y');
$selectedMonth = $_GET['month'] ?? $currentMonth;
$selectedYear = $_GET['year'] ?? $currentYear;

// Calculate financial data
$startDate = $selectedYear . '-' . $selectedMonth . '-01';
$endDate = date('Y-m-t', strtotime($startDate)); // Last day of month

// Total income from invoices
$incomeQuery = "SELECT SUM(total_amount) as total_income FROM invoices WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?";
$income = $db->fetchOne($incomeQuery, [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])['total_income'] ?? 0;

// Total expenses
$expenseQuery = "SELECT SUM(amount) as total_expenses FROM expenses WHERE payment_status = 'paid' AND expense_date BETWEEN ? AND ?";
$expenses = $db->fetchOne($expenseQuery, [$startDate, $endDate])['total_expenses'] ?? 0;

// Subscription payments
$subscriptionQuery = "SELECT SUM(amount) as subscription_income FROM subscription_payments WHERE status = 'completed' AND payment_date BETWEEN ? AND ?";
$subscriptionIncome = $db->fetchOne($subscriptionQuery, [$startDate, $endDate])['subscription_income'] ?? 0;

// Net profit
$netProfit = ($income + $subscriptionIncome) - $expenses;

// Get expense breakdown by type
$expenseBreakdown = $db->fetchAll(
    "SELECT expense_type, SUM(amount) as total FROM expenses WHERE payment_status = 'paid' AND expense_date BETWEEN ? AND ? GROUP BY expense_type",
    [$startDate, $endDate]
);

// Get monthly income trend (last 12 months)
$monthlyIncome = [];
for ($i = 11; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));

    $monthIncome = $db->fetchOne(
        "SELECT SUM(total_amount) as income FROM invoices WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ?",
        [$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']
    )['income'] ?? 0;

    $monthSubscriptions = $db->fetchOne(
        "SELECT SUM(amount) as subs FROM subscription_payments WHERE status = 'completed' AND payment_date BETWEEN ? AND ?",
        [$monthStart, $monthEnd]
    )['subs'] ?? 0;

    $monthlyIncome[] = [
        'month' => date('M Y', strtotime($monthStart)),
        'income' => $monthIncome + $monthSubscriptions
    ];
}

// Get recent expenses
$recentExpenses = $db->fetchAll(
    "SELECT e.*, u.username as created_by_name FROM expenses e LEFT JOIN users u ON e.created_by = u.id ORDER BY e.created_at DESC LIMIT 10"
);

// Get pending payments
$pendingInvoices = $db->fetchAll(
    "SELECT i.*, p.first_name, p.last_name FROM invoices i JOIN patients p ON i.patient_id = p.id WHERE i.payment_status = 'pending' ORDER BY i.created_at DESC LIMIT 5"
);

$pageTitle = 'Financial Dashboard';
include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Financial Dashboard</h1>
        <div class="d-flex gap-2">
            <select class="form-select" id="monthSelect" style="width: auto;">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m == $selectedMonth ? 'selected' : ''; ?>>
                        <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                    </option>
                <?php endfor; ?>
            </select>
            <select class="form-select" id="yearSelect" style="width: auto;">
                <?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $selectedYear ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <!-- Financial Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Income</h5>
                    <h3>$<?php echo number_format($income + $subscriptionIncome, 2); ?></h3>
                    <small>Treatment + Subscription Income</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Expenses</h5>
                    <h3>$<?php echo number_format($expenses, 2); ?></h3>
                    <small>All expense categories</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card <?php echo $netProfit >= 0 ? 'bg-info' : 'bg-warning'; ?> text-white">
                <div class="card-body">
                    <h5 class="card-title">Net Profit</h5>
                    <h3>$<?php echo number_format($netProfit, 2); ?></h3>
                    <small>Income - Expenses</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Pending Payments</h5>
                    <h3><?php echo count($pendingInvoices); ?></h3>
                    <small>Unpaid invoices</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Income Trend (Last 12 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="incomeChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Expense Breakdown</h5>
                </div>
                <div class="card-body">
                    <canvas id="expenseChart" width="300" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Recent Expenses</h5>
                    <a href="add_expense.php" class="btn btn-sm btn-primary">Add Expense</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentExpenses)): ?>
                        <p class="text-muted">No expenses recorded yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentExpenses as $expense): ?>
                                        <tr>
                                            <td><?php echo ucfirst($expense['expense_type']); ?></td>
                                            <td><?php echo htmlspecialchars(substr($expense['description'], 0, 30)); ?>...</td>
                                            <td>$<?php echo number_format($expense['amount'], 2); ?></td>
                                            <td><?php echo formatDate($expense['expense_date']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Pending Payments</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pendingInvoices)): ?>
                        <p class="text-muted">No pending payments.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingInvoices as $invoice): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></td>
                                            <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                            <td><?php echo formatDate($invoice['created_at']); ?></td>
                                            <td>
                                                <a href="../billing/invoice_view.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Income Trend Chart
const incomeCtx = document.getElementById('incomeChart').getContext('2d');
const incomeChart = new Chart(incomeCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthlyIncome, 'month')); ?>,
        datasets: [{
            label: 'Monthly Income',
            data: <?php echo json_encode(array_column($monthlyIncome, 'income')); ?>,
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Expense Breakdown Chart
const expenseCtx = document.getElementById('expenseChart').getContext('2d');
const expenseData = <?php echo json_encode($expenseBreakdown); ?>;
const expenseChart = new Chart(expenseCtx, {
    type: 'doughnut',
    data: {
        labels: expenseData.length > 0 ? expenseData.map(item => item.expense_type.charAt(0).toUpperCase() + item.expense_type.slice(1)) : ['No Data'],
        datasets: [{
            data: expenseData.length > 0 ? expenseData.map(item => parseFloat(item.total)) : [1],
            backgroundColor: [
                '#FF6384',
                '#36A2EB',
                '#FFCE56',
                '#4BC0C0',
                '#9966FF',
                '#FF9F40'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            tooltip: {
                callbacks: {
                    label: function(context) {
                        if (expenseData.length === 0) return 'No expense data';
                        return context.label + ': $' + context.parsed.toLocaleString();
                    }
                }
            }
        }
    }
});

// Month/Year filter
document.getElementById('monthSelect').addEventListener('change', updateFilters);
document.getElementById('yearSelect').addEventListener('change', updateFilters);

function updateFilters() {
    const month = document.getElementById('monthSelect').value;
    const year = document.getElementById('yearSelect').value;
    window.location.href = `?month=${month}&year=${year}`;
}
</script>

<?php include '../layouts/footer.php'; ?>