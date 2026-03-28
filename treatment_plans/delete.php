<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$planId = $_GET['id'] ?? 0;

// Get plan to check existence and for logging
$plan = $db->fetchOne(
    "SELECT * FROM treatment_plans WHERE id = ?",
    [$planId],
    "i"
);

if (!$plan) {
    $_SESSION['error'] = 'Treatment plan not found.';
    header('Location: index.php');
    exit;
}

// Optional: Check permissions (e.g., only admin or creator can delete)
// if ($_SESSION['role'] != 'admin' && $plan['created_by'] != $_SESSION['user_id']) {
//     $_SESSION['error'] = 'You do not have permission to delete this plan.';
//     header('Location: index.php');
//     exit;
// }

// Delete related treatment steps first (if foreign key is not set to cascade)
$db->execute("DELETE FROM treatment_steps WHERE plan_id = ?", [$planId], "i");

// Delete the plan
$result = $db->execute("DELETE FROM treatment_plans WHERE id = ?", [$planId], "i");

if ($result !== false) {
    logAction('DELETE', 'treatment_plans', $planId, $plan, null);
    $_SESSION['success'] = 'Treatment plan deleted successfully.';
} else {
    $_SESSION['error'] = 'Error deleting treatment plan.';
}

header('Location: index.php');
exit;