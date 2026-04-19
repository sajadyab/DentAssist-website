<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

$db = Database::getInstance();
$planId = (int) ($_GET['id'] ?? 0);

// Fetch the plan to verify existence and for logging
$plan = $db->fetchOne("SELECT * FROM treatment_plans WHERE id = ?", [$planId], "i");
if (!$plan) {
    $_SESSION['error'] = 'Treatment plan not found.';
    header('Location: index.php');
    exit;
}

// Optional permission check (uncomment if needed)
// if ($_SESSION['role'] != 'admin' && $plan['created_by'] != $_SESSION['user_id']) {
//     $_SESSION['error'] = 'You do not have permission to delete this plan.';
//     header('Location: index.php');
//     exit;
// }

$conn = $db->getConnection();
$conn->begin_transaction();

try {
    $stepRows = $db->fetchAll("SELECT id FROM treatment_steps WHERE plan_id = ?", [$planId], "i");
    foreach ($stepRows as $sr) {
        $sid = (int) ($sr['id'] ?? 0);
        if ($sid > 0) {
            queueCloudDeletion('treatment_steps', $sid, 'local_id');
        }
    }
    queueCloudDeletion('treatment_plans', $planId, 'local_id');

    // ------------------------------------------------------------------
    // 1. Check for foreign key constraints on treatment_steps
    // ------------------------------------------------------------------
    $constraints = $db->fetchAll("
        SELECT CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'treatment_steps'
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ");

    $hasForeignKeyToPlan = false;
    foreach ($constraints as $fk) {
        if ($fk['REFERENCED_TABLE_NAME'] === 'treatment_plans' && $fk['REFERENCED_COLUMN_NAME'] === 'id') {
            $hasForeignKeyToPlan = true;
            break;
        }
    }

    // ------------------------------------------------------------------
    // 2. Delete related treatment steps
    // ------------------------------------------------------------------
    // If the foreign key does NOT have ON DELETE CASCADE, we must delete steps manually.
    // We'll attempt deletion and catch any foreign key error.
    $stepDeleteSuccess = false;
    $stepDeleteError = '';

    try {
        // Get all step IDs for this plan before deleting
        $stepIds = $db->fetchAll("SELECT id FROM treatment_steps WHERE plan_id = ?", [$planId], "i");
        
        // Queue deletions for sync
        foreach ($stepIds as $step) {
            queueCloudDeletion('treatment_steps', (int) $step['id'], 'local_id');
        }

        $stepDelete = $db->execute("DELETE FROM treatment_steps WHERE plan_id = ?", [$planId], "i");
        if ($stepDelete !== false) {
            $stepDeleteSuccess = true;
        } else {
            // Get detailed error from MySQL
            $stepDeleteError = $conn->error;
            if (empty($stepDeleteError)) {
                $stepDeleteError = 'Unknown database error while deleting treatment steps.';
            }
        }
    } catch (Exception $e) {
        $stepDeleteError = $e->getMessage();
    }

    // If step deletion failed, check if it's a foreign key constraint issue
    if (!$stepDeleteSuccess) {
        $isForeignKeyError = stripos($stepDeleteError, 'foreign key') !== false ||
                             stripos($stepDeleteError, 'cannot delete') !== false ||
                             stripos($stepDeleteError, 'constraint fails') !== false;

        if ($isForeignKeyError) {
            // Provide a helpful message and suggest enabling CASCADE
            throw new Exception(
                "Cannot delete treatment steps because they are referenced by other records (e.g., appointments, invoices). " .
                "To fix this permanently, ask your administrator to alter the foreign key constraint on 'treatment_steps' " .
                "to `ON DELETE CASCADE`. Alternatively, manually remove the dependent records first.<br>" .
                "System error: " . htmlspecialchars($stepDeleteError)
            );
        } else {
            throw new Exception("Failed to delete treatment steps: " . htmlspecialchars($stepDeleteError));
        }
    }

    // ------------------------------------------------------------------
    // 3. Delete the treatment plan itself
    // ------------------------------------------------------------------
    $planDelete = $db->execute("DELETE FROM treatment_plans WHERE id = ?", [$planId], "i");
    if ($planDelete === false) {
        $planDeleteError = $conn->error ?: 'Unknown error deleting treatment plan.';
        throw new Exception("Failed to delete treatment plan: " . htmlspecialchars($planDeleteError));
    }

    // ------------------------------------------------------------------
    // 4. Commit transaction and log success
    // ------------------------------------------------------------------
    $conn->commit();
    sync_process_delete_queue_now(50);
    logAction('DELETE', 'treatment_plans', $planId, $plan, null);
    $_SESSION['success'] = 'Treatment plan and all associated steps deleted successfully.';

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Error deleting treatment plan: ' . $e->getMessage();
}

header('Location: index.php');
exit;
