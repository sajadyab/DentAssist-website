<?php
declare(strict_types=1);

$basePath = realpath(__DIR__ . '/../../');
require_once $basePath . '/includes/config.php';
require_once $basePath . '/includes/db.php';
require_once $basePath . '/includes/functions.php';
require_once $basePath . '/includes/patient_cloud_repository.php';

header('Content-Type: application/json');

// Token Authentication
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token required']);
    exit;
}

$db = Database::getInstance();
$tokenRow = $db->fetchOne("SELECT user_id FROM api_tokens WHERE token = ?", [$token], 's');

if (!$tokenRow) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$user = $db->fetchOne("SELECT id, role FROM users WHERE id = ?", [$tokenRow['user_id']], 'i');
$_SESSION['user_id'] = $user['id'];
$_SESSION['role'] = $user['role'];

// Get patient ID
$patientId = getPatientIdFromUserId($user['id']);
if (!$patientId) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Patient record not found']);
    exit;
}

// Fetch patient data
$patient = patient_portal_fetch_patient_cloud_first($patientId);

// Fetch appointments
$allAppointments = patient_portal_fetch_appointments_cloud_first($patientId);
$nextAppointment = patient_portal_pick_next_appointment($allAppointments);
$totalVisits = patient_portal_count_completed_visits($allAppointments);
$recentAppointments = array_slice($allAppointments, 0, 5);

// Check settings
$showPoints = false;
$showRefs = false;
$showSub = false;

$ptsSetting = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_points_view'");
$refSetting = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_referrals_view'");
$subSetting = $db->fetchOne("SELECT setting_value FROM clinic_settings WHERE setting_key = 'allow_subscription_view'");

if ($ptsSetting && $ptsSetting['setting_value'] == '1') $showPoints = true;
if ($refSetting && $refSetting['setting_value'] == '1') $showRefs = true;
if ($subSetting && $subSetting['setting_value'] == '1') $showSub = true;

// Referral count
$referralCount = 0;
if ($showRefs) {
    $referralCount = patient_portal_count_referred_patients_cloud_first((int) $patientId);
}

// Points
$points = (int) ($patient['points'] ?? 0);
$ptsMod = $points % 250;
$toNextReward = $points === 0 ? 250 : ($ptsMod === 0 ? 250 : 250 - $ptsMod);

// Subscription
$subscription = $patient['subscription_type'] ?? 'none';
$subscriptionEnd = $patient['subscription_end_date'] ?? null;

// Format recent appointments
$formattedRecent = [];
foreach ($recentAppointments as $apt) {
    $formattedRecent[] = [
        'id' => (int) ($apt['id'] ?? 0),
        'appointment_date' => $apt['appointment_date'] ?? '',
        'appointment_time' => $apt['appointment_time'] ?? '',
        'treatment_type' => $apt['treatment_type'] ?? '',
        'doctor_name' => $apt['doctor_name'] ?? '',
        'status' => $apt['status'] ?? '',
    ];
}

// Build response
echo json_encode([
    'success' => true,
    'data' => [
        'patient' => [
            'full_name' => $patient['full_name'] ?? '',
            'member_since' => formatDate($patient['created_at'] ?? '', 'M Y'),
            'last_visit' => patientHasLastVisitDate($patient['last_visit_date'] ?? null)
                ? formatDate(normalizePatientOptionalDate($patient['last_visit_date'] ?? null))
                : 'Never',
            'points' => $points,
            'points_to_next_reward' => $toNextReward,
            'referral_code' => $patient['referral_code'] ?? '',
            'subscription_type' => $subscription,
            'subscription_end_date' => $subscriptionEnd ? formatDate($subscriptionEnd) : null,
        ],
        'stats' => [
            'total_visits' => $totalVisits,
            'points' => $points,
            'referrals' => $referralCount,
            'subscription' => ($subscription === 'none' || $subscription === '') ? 'None' : ucfirst($subscription),
        ],
        'next_appointment' => $nextAppointment,
        'recent_appointments' => $formattedRecent,
        'settings' => [
            'show_points' => $showPoints,
            'show_referrals' => $showRefs,
            'show_subscription' => $showSub,
        ],
    ]
]);