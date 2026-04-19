<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

Auth::requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function safetyBadRequest(string $msg): void
{
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

function safetyForbidden(string $msg): void
{
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

if (Auth::hasRole('patient')) {
    safetyForbidden('Forbidden.');
}

if ($method === 'GET') {
    $pid = (int) ($_GET['patient_id'] ?? 0);
    if ($pid <= 0) {
        safetyBadRequest('Missing patient_id.');
    }
    $p = $db->fetchOne(
        'SELECT id, medical_history, allergies, current_medications FROM patients WHERE id = ?',
        [$pid],
        'i'
    );
    if (!$p) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
        exit;
    }

    $mh = parsePatientMedicalHistoryStructured($p['medical_history'] ?? null);
    $meds = parsePatientMedicationsList($p['current_medications'] ?? null);
    $hasAllergies = normalizePatientAllergiesFlag($p['allergies'] ?? null);

    echo json_encode([
        'success' => true,
        'patient_id' => (int) $p['id'],
        'conditions' => $mh['conditions'],
        'notes' => $mh['notes'],
        'medications' => $meds,
        'allergies' => $hasAllergies ? 'yes' : 'no',
        'caution' => buildPatientCautionSummary([
            'medical_history' => $p['medical_history'] ?? null,
            'current_medications' => $p['current_medications'] ?? null,
            'allergies' => $p['allergies'] ?? null,
        ]),
    ]);
    exit;
}

if ($method === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    if (!is_array($data)) {
        safetyBadRequest('Invalid JSON.');
    }
    $pid = (int) ($data['patient_id'] ?? 0);
    if ($pid <= 0) {
        safetyBadRequest('Missing patient_id.');
    }

    $conditions = $data['conditions'] ?? [];
    if (!is_array($conditions)) {
        $conditions = [];
    }
    $conditions = array_values(array_unique(array_filter(array_map('strval', $conditions))));

    $meds = $data['medications'] ?? [];
    if (!is_array($meds)) {
        $meds = [];
    }
    $meds = array_values(array_unique(array_filter(array_map('strval', $meds))));

    $allergies = (string) ($data['allergies'] ?? 'no');
    $allergies = strtolower(trim($allergies));
    if (!in_array($allergies, ['yes', 'no'], true)) {
        $allergies = 'no';
    }

    $medPayload = json_encode($meds, JSON_UNESCAPED_UNICODE);
    if ($medPayload === false) {
        $medPayload = null;
    }

    $existing = $db->fetchOne('SELECT id, medical_history FROM patients WHERE id = ?', [$pid], 'i');
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Patient not found.']);
        exit;
    }

    $prevMh = parsePatientMedicalHistoryStructured($existing['medical_history'] ?? null);
    $notesPreserved = $prevMh['notes'] ?? '';
    $mhPayload = json_encode(['conditions' => $conditions, 'notes' => $notesPreserved], JSON_UNESCAPED_UNICODE);
    if ($mhPayload === false) {
        $mhPayload = null;
    }

    $db->execute(
        "UPDATE patients SET medical_history = ?, allergies = ?, current_medications = ?, updated_at = CURRENT_TIMESTAMP, sync_status = 'pending' WHERE id = ?",
        [$mhPayload, $allergies, $medPayload, $pid],
        'sssi'
    );
    sync_push_row_now('patients', $pid);

    $caution = buildPatientCautionSummary([
        'medical_history' => $mhPayload,
        'current_medications' => $medPayload,
        'allergies' => $allergies,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Safety info updated.',
        'patient_id' => $pid,
        'caution' => $caution,
        'caution_html' => renderCautionBadgesHtml([
            'medical_history' => $mhPayload,
            'current_medications' => $medPayload,
            'allergies' => $allergies,
        ]),
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
exit;

