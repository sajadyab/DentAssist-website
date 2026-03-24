<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Require login
Auth::requireLogin();

header('Content-Type: application/json');

$db = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Get single appointment
            $appointment = $db->fetchOne(
                "SELECT a.*, 
                        p.full_name as patient_name,
                        u.full_name as doctor_name
                 FROM appointments a
                 JOIN patients p ON a.patient_id = p.id
                 JOIN users u ON a.doctor_id = u.id
                 WHERE a.id = ?",
                [$_GET['id']],
                "i"
            );
            
            if (!$appointment) {
                http_response_code(404);
                echo json_encode(['error' => 'Appointment not found']);
                break;
            }
            
            echo json_encode($appointment);
        } else {
            // Fetch appointments for calendar
            $start = $_GET['start'] ?? date('Y-m-d');
            $end = $_GET['end'] ?? date('Y-m-d', strtotime('+1 month'));
            
            // Build query based on user role
            $whereClause = "a.appointment_date BETWEEN ? AND ?";
            $params = [$start, $end];
            $types = "ss";
            
            if (Auth::hasRole('patient')) {
                // Patients can only see their own appointments
                $patientId = getPatientIdFromUserId(Auth::userId());
                $whereClause .= " AND a.patient_id = ?";
                $params[] = $patientId;
                $types .= "i";
            }
            
            $appointments = $db->fetchAll(
                "SELECT a.*, 
                        p.full_name as patient_name,
                        u.full_name as doctor_name
                 FROM appointments a
                 JOIN patients p ON a.patient_id = p.id
                 JOIN users u ON a.doctor_id = u.id
                 WHERE $whereClause
                 ORDER BY a.appointment_date, a.appointment_time",
                $params,
                $types
            );
            
            $events = [];
            foreach ($appointments as $apt) {
                $color = '#28a745'; // default green
                switch ($apt['status']) {
                    case 'cancelled':
                        $color = '#dc3545';
                        break;
                    case 'completed':
                        $color = '#6c757d';
                        break;
                    case 'in-treatment':
                        $color = '#ffc107';
                        break;
                }
                
                $events[] = [
                    'id' => $apt['id'],
                    'title' => $apt['patient_name'] . ' - ' . $apt['treatment_type'],
                    'start' => $apt['appointment_date'] . 'T' . $apt['appointment_time'],
                    'end' => $apt['appointment_date'] . 'T' . $apt['end_time'],
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'extendedProps' => [
                        'doctor' => $apt['doctor_name'],
                        'status' => $apt['status'],
                        'patient_id' => $apt['patient_id']
                    ]
                ];
            }
            
            echo json_encode($events);
        }
        break;
        
    case 'POST':
        // Create/Update appointment
        $input = file_get_contents('php://input');
        error_log('Raw input: ' . $input);
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
            break;
        }
        
        // Log the received data for debugging
        error_log('Appointment API POST data: ' . json_encode($data));
        
        if (isset($data['id'])) {
            // Check if this is just a status update
            if (count($data) === 2 && isset($data['status'])) {
                // Status update only
                $result = $db->execute(
                    "UPDATE appointments SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?",
                    [$data['status'], $data['id']],
                    "si"
                );
                
                logAction('UPDATE', 'appointments', $data['id'], null, ['status' => $data['status']]);
                
                echo json_encode(['success' => true, 'message' => 'Status updated']);
            } else {
                // Full update
                $sql = "UPDATE appointments SET 
                        patient_id = ?, doctor_id = ?, appointment_date = ?,
                        appointment_time = ?, duration = ?, treatment_type = ?,
                        description = ?, chair_number = ?, status = ?, notes = ?
                        WHERE id = ?";
                
                $result = $db->execute(
                    $sql,
                    [
                        $data['patient_id'],
                        $data['doctor_id'],
                        $data['appointment_date'],
                        $data['appointment_time'],
                        $data['duration'] ?? 30,
                        $data['treatment_type'],
                        $data['description'] ?? null,
                        $data['chair_number'] ?? null,
                        $data['status'] ?? 'scheduled',
                        $data['notes'] ?? null,
                        $data['id']
                    ],
                    "iississsssi"
                );
                
                logAction('UPDATE', 'appointments', $data['id'], null, $data);
                
                echo json_encode(['success' => true, 'message' => 'Appointment updated']);
            }
        } else {
            // Create
            try {
                $id = $db->insert(
                    "INSERT INTO appointments 
                    (patient_id, doctor_id, appointment_date, appointment_time, 
                     duration, treatment_type, description, chair_number, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $data['patient_id'],
                        $data['doctor_id'],
                        $data['appointment_date'],
                        $data['appointment_time'],
                        $data['duration'] ?? 30,
                        $data['treatment_type'],
                        $data['description'] ?? null,
                        $data['chair_number'] ?? null,
                        $data['status'] ?? 'scheduled',
                        Auth::userId()
                    ],
                    "iississssi"
                );
                
                if ($id) {
                    logAction('CREATE', 'appointments', $id, null, $data);
                    echo json_encode(['success' => true, 'message' => 'Appointment created', 'id' => $id]);
                } else {
                    error_log('Failed to insert appointment: ' . json_encode($data));
                    echo json_encode(['success' => false, 'message' => 'Failed to create appointment']);
                }
            } catch (Exception $e) {
                error_log('Exception creating appointment: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        }
        break;
        
    case 'DELETE':
        // Cancel appointment
        $data = json_decode(file_get_contents('php://input'), true);
        
        $result = $db->execute(
            "UPDATE appointments SET status = 'cancelled', cancellation_reason = ? WHERE id = ?",
            [$data['reason'] ?? null, $data['id']],
            "si"
        );
        
        logAction('DELETE', 'appointments', $data['id']);
        
        echo json_encode(['success' => true, 'message' => 'Appointment cancelled']);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}
?>