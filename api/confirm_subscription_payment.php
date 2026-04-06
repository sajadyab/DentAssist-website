<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');
api_require_login();

$role = (string) ($_SESSION['role'] ?? '');
if (!in_array($role, ['admin', 'assistant', 'doctor'], true)) {
    api_error('Forbidden.', 403);
}

$patientId = (int) ($_POST['patient_id'] ?? 0);
$reference = trim((string) ($_POST['reference'] ?? ''));
if ($reference === '') {
    $reference = 'CASH-' . time();
}

if ($patientId <= 0) {
    api_error('Invalid patient.', 422);
}

$res = SubscriptionService::confirmClinicPayment($patientId, $reference, (int) Auth::userId());
if (!empty($res['ok'])) {
    api_ok(['redirect' => 'assistant_subscriptions.php?success=1'], 'Subscription activated successfully.');
}

api_error('Error: ' . (string) ($res['error'] ?? 'Unknown error'), 500);

