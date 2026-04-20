<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$db = Database::getInstance();

if (isset($input['id']) && !empty($input['id'])) {
    // Update existing step
    $setParts = [
        'step_number = ?',
        'procedure_name = ?',
        'description = ?',
        'tooth_numbers = ?',
        'duration_minutes = ?',
        'cost = ?',
        'status = ?',
        'notes = ?',
    ];
    $values = [
        $input['step_number'],
        $input['procedure_name'],
        $input['description'] ?? null,
        $input['tooth_numbers'] ?? null,
        $input['duration_minutes'] ?? null,
        $input['cost'] ?? null,
        $input['status'] ?? 'pending',
        $input['notes'] ?? null,
    ];
    $types = 'issssdss';
    if (dbColumnExists('treatment_steps', 'sync_status')) {
        $setParts[] = "sync_status = 'pending'";
    }
    $values[] = $input['id'];
    $types .= 'i';

    $result = $db->execute(
        'UPDATE treatment_steps SET ' . implode(', ', $setParts) . ' WHERE id = ?',
        $values,
        $types
    );
    if ($result === false) {
        echo json_encode(['success' => false, 'message' => 'Failed to update step']);
        exit;
    }
    sync_push_row_now('treatment_steps', (int) $input['id']);

    logAction('UPDATE', 'treatment_steps', $input['id'], null, $input);
    echo json_encode(['success' => true, 'message' => 'Step updated']);
} else {
    // Create new step
    $columns = [
        'plan_id', 'step_number', 'procedure_name', 'description', 'tooth_numbers',
        'duration_minutes', 'cost', 'status', 'notes',
    ];
    $values = [
        $input['plan_id'],
        $input['step_number'],
        $input['procedure_name'],
        $input['description'] ?? null,
        $input['tooth_numbers'] ?? null,
        $input['duration_minutes'] ?? null,
        $input['cost'] ?? null,
        $input['status'] ?? 'pending',
        $input['notes'] ?? null,
    ];
    $types = 'iissssdss';
    if (dbColumnExists('treatment_steps', 'sync_status')) {
        $columns[] = 'sync_status';
        $values[] = 'pending';
        $types .= 's';
    }

    $stepId = $db->insert(
        'INSERT INTO treatment_steps (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
        $values,
        $types
    );
    if (!$stepId) {
        echo json_encode(['success' => false, 'message' => 'Failed to create step']);
        exit;
    }
    sync_push_row_now('treatment_steps', (int) $stepId);

    logAction('CREATE', 'treatment_steps', $stepId, null, $input);
    echo json_encode(['success' => true, 'message' => 'Step created', 'id' => $stepId]);
}
?>
