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
    $result = $db->execute(
        "UPDATE treatment_steps SET
            step_number = ?, procedure_name = ?, description = ?,
            tooth_numbers = ?, duration_minutes = ?, cost = ?,
            status = ?, notes = ?
         WHERE id = ?",
        [
            $input['step_number'],
            $input['procedure_name'],
            $input['description'] ?? null,
            $input['tooth_numbers'] ?? null,
            $input['duration_minutes'] ?? null,
            $input['cost'] ?? null,
            $input['status'] ?? 'pending',
            $input['notes'] ?? null,
            $input['id']
        ],
        "issssdssi"
    );

    logAction('UPDATE', 'treatment_steps', $input['id'], null, $input);
    echo json_encode(['success' => true, 'message' => 'Step updated']);
} else {
    // Create new step
    $stepId = $db->insert(
        "INSERT INTO treatment_steps
            (plan_id, step_number, procedure_name, description, tooth_numbers,
             duration_minutes, cost, status, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $input['plan_id'],
            $input['step_number'],
            $input['procedure_name'],
            $input['description'] ?? null,
            $input['tooth_numbers'] ?? null,
            $input['duration_minutes'] ?? null,
            $input['cost'] ?? null,
            $input['status'] ?? 'pending',
            $input['notes'] ?? null
        ],
        "iissssdss"
    );

    logAction('CREATE', 'treatment_steps', $stepId, null, $input);
    echo json_encode(['success' => true, 'message' => 'Step created', 'id' => $stepId]);
}
?>