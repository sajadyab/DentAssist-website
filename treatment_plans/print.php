<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$planId = $_GET['id'] ?? 0;

// Fetch treatment plan with patient details
$plan = $db->fetchOne(
    "SELECT tp.*, p.full_name as patient_name, p.date_of_birth, p.phone, p.email,
            u.full_name as created_by_name
     FROM treatment_plans tp
     JOIN patients p ON tp.patient_id = p.id
     LEFT JOIN users u ON tp.created_by = u.id
     WHERE tp.id = ?",
    [$planId],
    "i"
);

if (!$plan) {
    die("Treatment plan not found.");
}

// Fetch treatment steps
$steps = $db->fetchAll(
    "SELECT * FROM treatment_steps WHERE plan_id = ? ORDER BY step_number",
    [$planId],
    "i"
);

// Calculate totals
$totalEstimatedCost = $plan['estimated_cost'] ?? 0;
$totalStepsCost = array_sum(array_column($steps, 'cost'));
$totalDiscount = $plan['discount'] ?? 0;
$netCost = $totalEstimatedCost - $totalDiscount;
$actualNetCost = $totalStepsCost - $totalDiscount;

// Define status colors for print (in case we need to style)
$statusColors = [
    'proposed' => '#ffc107',
    'approved' => '#0dcaf0',
    'in-progress' => '#0d6efd',
    'completed' => '#198754',
    'cancelled' => '#dc3545'
];
$priorityColors = [
    'low' => '#198754',
    'medium' => '#0dcaf0',
    'high' => '#ffc107',
    'emergency' => '#dc3545'
];
$stepColors = [
    'pending' => '#6c757d',
    'in-progress' => '#0d6efd',
    'completed' => '#198754',
    'skipped' => '#ffc107'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment Plan - <?php echo htmlspecialchars($plan['plan_name']); ?></title>
    <style>
        /* Print Styles */
        @media print {
            body {
                margin: 0;
                padding: 0;
                font-size: 12pt;
                line-height: 1.4;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
            a {
                text-decoration: none;
                color: #000;
            }
            .badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 4px;
                color: #fff;
                font-size: 10pt;
            }
        }
        /* Screen Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 20px;
            background: #f4f6f9;
        }
        .print-container {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 24pt;
        }
        .header p {
            margin: 5px 0 0;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 18pt;
            font-weight: bold;
            border-left: 4px solid #0d6efd;
            padding-left: 10px;
            margin-bottom: 15px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }
        .info-item {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
        }
        .info-label {
            font-weight: bold;
            width: 140px;
            flex-shrink: 0;
        }
        .info-value {
            flex: 1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            font-size: 10pt;
            color: #666;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        .btn-print {
            background-color: #0d6efd;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .btn-print:hover {
            background-color: #0b5ed7;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            color: #fff;
            font-size: 10pt;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <div class="no-print text-center">
            <button class="btn-print" onclick="window.print();">Print This Page</button>
        </div>

        <!-- Header -->
        <div class="header">
            <h1>Treatment Plan</h1>
            <p><?php echo htmlspecialchars($plan['plan_name']); ?></p>
        </div>

        <!-- Plan Details -->
        <div class="section">
            <div class="section-title">Plan Details</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Plan ID:</span>
                    <span class="info-value">#<?php echo $plan['id']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Patient:</span>
                    <span class="info-value"><?php echo htmlspecialchars($plan['patient_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="badge" style="background-color: <?php echo $statusColors[$plan['status']] ?? '#6c757d'; ?>">
                            <?php echo ucfirst($plan['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Priority:</span>
                    <span class="info-value">
                        <span class="badge" style="background-color: <?php echo $priorityColors[$plan['priority']] ?? '#6c757d'; ?>">
                            <?php echo ucfirst($plan['priority']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Teeth Affected:</span>
                    <span class="info-value"><?php echo $plan['teeth_affected'] ?? 'None'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Start Date:</span>
                    <span class="info-value"><?php echo $plan['start_date'] ? formatDate($plan['start_date']) : 'Not set'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Estimated End:</span>
                    <span class="info-value"><?php echo $plan['estimated_end_date'] ? formatDate($plan['estimated_end_date']) : 'Not set'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Created:</span>
                    <span class="info-value"><?php echo formatDate($plan['created_at'], 'M d, Y g:i A'); ?></span>
                </div>
                <?php if ($plan['description']): ?>
                <div class="info-item" style="grid-column: span 2;">
                    <span class="info-label">Description:</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($plan['description'])); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($plan['notes']): ?>
                <div class="info-item" style="grid-column: span 2;">
                    <span class="info-label">Notes:</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($plan['notes'])); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Treatment Steps -->
        <div class="section">
            <div class="section-title">Treatment Steps</div>
            <?php if (empty($steps)): ?>
                <p>No steps have been added to this plan.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Procedure</th>
                            <th>Tooth</th>
                            <th>Duration</th>
                            <th>Cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($steps as $step): ?>
                        <tr>
                            <td><?php echo $step['step_number']; ?></td>
                            <td><?php echo htmlspecialchars($step['procedure_name']); ?></td>
                            <td><?php echo $step['tooth_numbers']; ?></td>
                            <td><?php echo $step['duration_minutes']; ?> min</td>
                            <td><?php echo formatCurrency($step['cost']); ?></td>
                            <td>
                                <span class="badge" style="background-color: <?php echo $stepColors[$step['status']] ?? '#6c757d'; ?>">
                                    <?php echo ucfirst($step['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Financial Summary -->
        <div class="section">
            <div class="section-title">Financial Summary</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Estimated Total:</span>
                    <span class="info-value"><?php echo formatCurrency($totalEstimatedCost); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Steps Total:</span>
                    <span class="info-value"><?php echo formatCurrency($totalStepsCost); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Discount:</span>
                    <span class="info-value"><?php echo formatCurrency($totalDiscount); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Net Amount (Est.):</span>
                    <span class="info-value"><strong><?php echo formatCurrency($netCost); ?></strong></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Net Amount (Steps):</span>
                    <span class="info-value"><strong><?php echo formatCurrency($actualNetCost); ?></strong></span>
                </div>
                <?php if ($plan['actual_cost']): ?>
                <div class="info-item">
                    <span class="info-label">Actual Cost (Manual):</span>
                    <span class="info-value"><?php echo formatCurrency($plan['actual_cost']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Approval Section -->
        <div class="section">
            <div class="section-title">Patient Approval</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Approved:</span>
                    <span class="info-value"><?php echo $plan['patient_approved'] ? 'Yes' : 'Pending'; ?></span>
                </div>
                <?php if ($plan['patient_approved'] && $plan['approval_date']): ?>
                <div class="info-item">
                    <span class="info-label">Approval Date:</span>
                    <span class="info-value"><?php echo formatDate($plan['approval_date']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($plan['approval_signature']): ?>
                <div class="info-item" style="grid-column: span 2;">
                    <span class="info-label">Signature:</span>
                    <span class="info-value"><img src="<?php echo $plan['approval_signature']; ?>" alt="Signature" style="max-width: 200px;"></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Generated on <?php echo date('F j, Y g:i A'); ?> | <?php echo htmlspecialchars($plan['created_by_name'] ?? 'System'); ?>
        </div>
    </div>
</body>
</html>