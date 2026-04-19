<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();
$pageTitle = __('financial_dashboard', 'Financial Dashboard');

$db = Database::getInstance();

// Handle expense form submission (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_expenses'])) {
    $monthYear = $_POST['month_year'] ?? null;
    $salaryPerAssistant = floatval($_POST['salary_per_assistant'] ?? 0);
    $assistants = intval($_POST['assistants_count'] ?? 0);
    $salariesTotal = $salaryPerAssistant * $assistants; // total salaries = salary per assistant × number of assistants
    $electricity = floatval($_POST['electricity'] ?? 0);
    $rent = floatval($_POST['rent'] ?? 0);
    $other = floatval($_POST['other_expenses'] ?? 0);
    $notes = $_POST['notes'] ?? null;

    if ($monthYear) {
        $hasMonthlySync = dbColumnExists('monthly_expenses', 'sync_status');
        $existing = $db->fetchOne(
            "SELECT id FROM monthly_expenses WHERE month_year = ?",
            [$monthYear],
            "s"
        );
        if ($existing) {
            $setParts = [
                'salaries_total = ?',
                'assistants_count = ?',
                'electricity = ?',
                'rent = ?',
                'other_expenses = ?',
                'notes = ?',
            ];
            $values = [$salariesTotal, $assistants, $electricity, $rent, $other, $notes];
            $types = 'diddds';
            if ($hasMonthlySync) {
                $setParts[] = "sync_status = 'pending'";
            }
            $values[] = $monthYear;
            $types .= 's';

            $db->execute(
                'UPDATE monthly_expenses SET ' . implode(', ', $setParts) . ' WHERE month_year = ?',
                $values,
                $types
            );
            if (!empty($existing['id'])) {
                sync_push_row_now('monthly_expenses', (int) $existing['id']);
            }
        } else {
            $columns = ['month_year', 'salaries_total', 'assistants_count', 'electricity', 'rent', 'other_expenses', 'notes'];
            $values = [$monthYear, $salariesTotal, $assistants, $electricity, $rent, $other, $notes];
            $types = 'sdiddds';
            if ($hasMonthlySync) {
                $columns[] = 'sync_status';
                $values[] = 'pending';
                $types .= 's';
            }
            $newId = (int) $db->insert(
                'INSERT INTO monthly_expenses (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                $values,
                $types
            );
            if ($newId > 0) {
                sync_push_row_now('monthly_expenses', $newId);
            }
        }

        // Redirect back to the same month
        $redirectMonth = date('Y-m', strtotime($monthYear));
        header("Location: " . url("reports/financial.php?month=$redirectMonth"));
        exit;
    }
}

// Get current month (default: current month)
$selectedMonth = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$selectedDate = date('Y-m-01', strtotime($selectedMonth . '-01'));

// Fetch monthly expenses for the selected month (or create if not exists)
$expenses = $db->fetchOne(
    "SELECT * FROM monthly_expenses WHERE month_year = ?",
    [$selectedDate],
    "s"
);
if (!$expenses) {
    $db->execute(
        "INSERT INTO monthly_expenses (month_year, salaries_total, assistants_count, electricity, rent, other_expenses) VALUES (?, 0, 0, 0, 0, 0)",
        [$selectedDate],
        "s"
    );
    $expenses = $db->fetchOne("SELECT * FROM monthly_expenses WHERE month_year = ?", [$selectedDate], "s");
}

// Calculate total revenue (from invoices paid) for the selected month
$revenue = $db->fetchOne(
    "SELECT SUM(paid_amount) as total 
     FROM invoices 
     WHERE payment_status IN ('paid', 'partial') 
       AND DATE_FORMAT(invoice_date, '%Y-%m') = ?",
    [$selectedMonth],
    "s"
);
$revenue = $revenue['total'] ?? 0;

// Calculate total cost of inventory purchases for the selected month
$inventoryCost = $db->fetchOne(
    "SELECT SUM(quantity_change * (SELECT cost_per_unit FROM inventory WHERE id = inventory_id)) as total
     FROM inventory_transactions 
     WHERE transaction_type = 'purchase' 
       AND DATE_FORMAT(performed_at, '%Y-%m') = ?",
    [$selectedMonth],
    "s"
);
$inventoryCost = $inventoryCost['total'] ?? 0;

// Manual expenses from monthly_expenses table
$manualExpenses = ($expenses['salaries_total'] ?? 0) +
                  ($expenses['electricity'] ?? 0) +
                  ($expenses['rent'] ?? 0) +
                  ($expenses['other_expenses'] ?? 0);

$totalExpenses = $inventoryCost + $manualExpenses;
$netProfit = $revenue - $totalExpenses;

// Get previous month's data for comparison
$prevMonth = date('Y-m', strtotime($selectedMonth . '-01 -1 month'));
$prevSelectedDate = date('Y-m-01', strtotime($prevMonth . '-01'));

$prevExpenses = $db->fetchOne(
    "SELECT * FROM monthly_expenses WHERE month_year = ?",
    [$prevSelectedDate],
    "s"
);
$prevManual = ($prevExpenses['salaries_total'] ?? 0) + ($prevExpenses['electricity'] ?? 0) + ($prevExpenses['rent'] ?? 0) + ($prevExpenses['other_expenses'] ?? 0);

$prevRevenue = $db->fetchOne(
    "SELECT SUM(paid_amount) as total FROM invoices WHERE payment_status IN ('paid', 'partial') AND DATE_FORMAT(invoice_date, '%Y-%m') = ?",
    [$prevMonth],
    "s"
);
$prevRevenue = $prevRevenue['total'] ?? 0;

$prevInventoryCost = $db->fetchOne(
    "SELECT SUM(quantity_change * (SELECT cost_per_unit FROM inventory WHERE id = inventory_id)) as total
     FROM inventory_transactions 
     WHERE transaction_type = 'purchase' 
       AND DATE_FORMAT(performed_at, '%Y-%m') = ?",
    [$prevMonth],
    "s"
);
$prevInventoryCost = $prevInventoryCost['total'] ?? 0;

$prevTotalExpenses = $prevInventoryCost + $prevManual;
$prevNetProfit = $prevRevenue - $prevTotalExpenses;

$profitChange = $netProfit - $prevNetProfit;
$profitChangePercent = $prevNetProfit != 0 ? ($profitChange / abs($prevNetProfit)) * 100 : 0;

// Get data for the last 12 months for charts
$months = [];
$revenues = [];
$expensesTotal = [];
$profits = [];
for ($i = 11; $i >= 0; $i--) {
    $monthDate = date('Y-m-01', strtotime("-$i months"));
    $monthKey = date('Y-m', strtotime($monthDate));
    $months[] = date('M Y', strtotime($monthDate));

    // Revenue
    $rev = $db->fetchOne(
        "SELECT SUM(paid_amount) as total FROM invoices WHERE payment_status IN ('paid', 'partial') AND DATE_FORMAT(invoice_date, '%Y-%m') = ?",
        [$monthKey],
        "s"
    );
    $rev = $rev['total'] ?? 0;
    $revenues[] = $rev;

    // Inventory cost
    $invCost = $db->fetchOne(
        "SELECT SUM(quantity_change * (SELECT cost_per_unit FROM inventory WHERE id = inventory_id)) as total
         FROM inventory_transactions 
         WHERE transaction_type = 'purchase' 
           AND DATE_FORMAT(performed_at, '%Y-%m') = ?",
        [$monthKey],
        "s"
    );
    $invCost = $invCost['total'] ?? 0;

    // Manual expenses for that month
    $manExp = $db->fetchOne(
        "SELECT salaries_total, electricity, rent, other_expenses FROM monthly_expenses WHERE month_year = ?",
        [$monthDate],
        "s"
    );
    $manual = 0;
    if ($manExp) {
        $manual = ($manExp['salaries_total'] ?? 0) + ($manExp['electricity'] ?? 0) + ($manExp['rent'] ?? 0) + ($manExp['other_expenses'] ?? 0);
    }
    $totalExp = $invCost + $manual;
    $expensesTotal[] = $totalExp;
    $profits[] = $rev - $totalExp;
}

include '../layouts/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3"><?php echo __('financial_dashboard', 'Financial Dashboard'); ?></h1>
        <div class="btn-group">
            <button class="btn btn-primary" onclick="showExpenseModal()">
                <i class="fas fa-edit"></i> <?php echo __('edit_expenses', 'Edit Expenses'); ?>
            </button>
            <a href="?month=<?php echo date('Y-m', strtotime($selectedMonth . '-01 -1 month')); ?>" class="btn btn-outline-secondary">
                <i class="fas fa-chevron-left"></i> <?php echo __('previous_month', 'Previous Month'); ?>
            </a>
            <a href="?month=<?php echo date('Y-m', strtotime($selectedMonth . '-01 +1 month')); ?>" class="btn btn-outline-secondary">
                <?php echo __('next_month', 'Next Month'); ?> <i class="fas fa-chevron-right"></i>
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title"><?php echo __('revenue', 'Revenue'); ?></h6>
                    <h2 class="mb-0"><?php echo formatCurrency($revenue); ?></h2>
                    <small><?php echo __('from_paid_invoices', 'From paid invoices'); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h6 class="card-title"><?php echo __('expenses', 'Expenses'); ?></h6>
                    <h2 class="mb-0"><?php echo formatCurrency($totalExpenses); ?></h2>
                    <small><?php echo __('inventory', 'Inventory') . ': ' . formatCurrency($inventoryCost) . ' | ' . __('manual', 'Manual') . ': ' . formatCurrency($manualExpenses); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title"><?php echo __('net_profit', 'Net Profit'); ?></h6>
                    <h2 class="mb-0"><?php echo formatCurrency($netProfit); ?></h2>
                    <small><?php echo __('vs_prev_month', 'vs last month'); ?>:
                        <?php echo ($profitChange >= 0 ? '+' : '') . formatCurrency($profitChange); ?>
                        (<?php echo number_format($profitChangePercent, 1); ?>%)
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title"><?php echo __('profit_margin', 'Profit Margin'); ?></h6>
                    <h2 class="mb-0"><?php echo $revenue > 0 ? number_format(($netProfit / $revenue) * 100, 1) : 0; ?>%</h2>
                    <small><?php echo __('of_revenue', 'of revenue'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Expenses Details -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><?php echo __('monthly_breakdown', 'Monthly Breakdown'); ?> - <?php echo date('F Y', strtotime($selectedDate)); ?></h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><?php echo __('revenue_details', 'Revenue Details'); ?></h6>
                    <table class="table table-sm">
                        <tr><th><?php echo __('paid_invoices', 'Paid Invoices'); ?></th><td class="text-end"><?php echo formatCurrency($revenue); ?></td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6><?php echo __('expense_details', 'Expense Details'); ?></h6>
                    <table class="table table-sm">
                        <tr><th><?php echo __('inventory_purchases', 'Inventory Purchases'); ?></th><td class="text-end"><?php echo formatCurrency($inventoryCost); ?></td></tr>
                        <tr><th><?php echo __('salaries', 'Salaries'); ?></th><td class="text-end"><?php echo formatCurrency($expenses['salaries_total'] ?? 0); ?></td></tr>
                        <tr><th><?php echo __('electricity', 'Electricity'); ?></th><td class="text-end"><?php echo formatCurrency($expenses['electricity'] ?? 0); ?></td></tr>
                        <tr><th><?php echo __('rent', 'Rent'); ?></th><td class="text-end"><?php echo formatCurrency($expenses['rent'] ?? 0); ?></td></tr>
                        <tr><th><?php echo __('other_expenses', 'Other Expenses'); ?></th><td class="text-end"><?php echo formatCurrency($expenses['other_expenses'] ?? 0); ?></td></tr>
                        <tr class="fw-bold"><th><?php echo __('total_expenses', 'Total Expenses'); ?></th><td class="text-end"><?php echo formatCurrency($totalExpenses); ?></td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?php echo __('revenue_vs_expenses', 'Revenue vs Expenses'); ?></h5>
                </div>
                <div class="card-body">
                    <canvas id="revenueExpenseChart" height="250"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><?php echo __('net_profit_trend', 'Net Profit Trend'); ?></h5>
                </div>
                <div class="card-body">
                    <canvas id="profitChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Editing Monthly Expenses -->
<div class="modal fade" id="expenseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('edit_monthly_expenses', 'Edit Monthly Expenses'); ?> - <?php echo date('F Y', strtotime($selectedDate)); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="" id="expenseForm">
                <input type="hidden" name="update_expenses" value="1">
                <div class="modal-body">
                    <input type="hidden" name="month_year" value="<?php echo $selectedDate; ?>">
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('assistants_count', 'Number of Assistants'); ?></label>
                        <input type="number" class="form-control" name="assistants_count" value="<?php echo $expenses['assistants_count'] ?? 0; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('salary_per_assistant', 'Salary per Assistant'); ?></label>
                        <input type="number" step="0.01" class="form-control" name="salary_per_assistant" 
                               value="<?php echo ($expenses['assistants_count'] ?? 0) > 0 ? round(($expenses['salaries_total'] ?? 0) / ($expenses['assistants_count'] ?? 1), 2) : 0; ?>">
                        <small class="text-muted"><?php echo __('total_salaries', 'Total Salaries') . ': ' . formatCurrency($expenses['salaries_total'] ?? 0); ?></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('electricity', 'Electricity'); ?></label>
                        <input type="number" step="0.01" class="form-control" name="electricity" value="<?php echo $expenses['electricity'] ?? 0; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('rent', 'Rent'); ?></label>
                        <input type="number" step="0.01" class="form-control" name="rent" value="<?php echo $expenses['rent'] ?? 0; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('other_expenses', 'Other Expenses'); ?></label>
                        <input type="number" step="0.01" class="form-control" name="other_expenses" value="<?php echo $expenses['other_expenses'] ?? 0; ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?php echo __('notes', 'Notes'); ?></label>
                        <textarea class="form-control" name="notes" rows="2"><?php echo htmlspecialchars($expenses['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel', 'Cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo __('save_changes', 'Save Changes'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const months = <?php echo json_encode($months); ?>;
    const revenues = <?php echo json_encode($revenues); ?>;
    const expensesTotal = <?php echo json_encode($expensesTotal); ?>;
    const profits = <?php echo json_encode($profits); ?>;

    new Chart(document.getElementById('revenueExpenseChart'), {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: '<?php echo __('revenue', 'Revenue'); ?>',
                    data: revenues,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: '<?php echo __('expenses', 'Expenses'); ?>',
                    data: expensesTotal,
                    backgroundColor: 'rgba(255, 99, 132, 0.5)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString() } } }
        }
    });

    new Chart(document.getElementById('profitChart'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: '<?php echo __('net_profit', 'Net Profit'); ?>',
                data: profits,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v.toLocaleString() } } }
        }
    });

    function showExpenseModal() {
        new bootstrap.Modal(document.getElementById('expenseModal')).show();
    }
</script>

<?php include '../layouts/footer.php'; ?>
