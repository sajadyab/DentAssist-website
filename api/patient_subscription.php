<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('POST');
api_require_login();

if (($_SESSION['role'] ?? '') !== 'patient') {
    api_error('Forbidden.', 403);
}

$db = Database::getInstance();
$userId = (int) Auth::userId();
$patientId = getPatientIdFromUserId($userId);
if (!$patientId) {
    api_error('Patient record not found.', 404);
}

$action = (string) ($_POST['action'] ?? '');
$plan = (string) ($_POST['plan'] ?? '');
if (!in_array($plan, ['basic', 'premium', 'family'], true)) {
    api_error('Invalid plan.', 422);
}

if ($action === 'clinic_payment') {
    $prices = ['basic' => 29, 'premium' => 49, 'family' => 79];
    $amount = (float) $prices[$plan];
    $annualAmount = $amount * 12;
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+1 year'));

    try {
        $db->execute(
            "UPDATE patients SET subscription_type = ?, subscription_start_date = ?, subscription_end_date = ?, subscription_status = 'pending' WHERE id = ?",
            [$plan, $startDate, $endDate, (int) $patientId],
            'sssi'
        );

        $invoiceNumber = generateInvoiceNumber();
        $invoiceId = $db->insert(
            "INSERT INTO invoices (patient_id, invoice_number, subtotal, total_amount, payment_status, invoice_date, due_date, notes, created_by)
             VALUES (?, ?, ?, ?, 'pending', ?, DATE_ADD(?, INTERVAL 7 DAY), ?, ?)",
            [
                (int) $patientId,
                $invoiceNumber,
                $annualAmount,
                $annualAmount,
                $startDate,
                $startDate,
                "Subscription: {$plan} plan (Annual) - Pending Payment",
                $userId,
            ],
            'isddsssi'
        );
        if (!$invoiceId) {
            throw new RuntimeException('Failed to create invoice.');
        }

        $db->insert(
            "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_date, status, processed_by, notes)
             VALUES (?, ?, ?, 'clinic', NOW(), 'pending', ?, 'Pending payment at clinic - Please visit assistant')",
            [(int) $patientId, $plan, $annualAmount, $userId],
            'isdi'
        );

        api_ok(['redirect' => url('patient/subscription.php?success=1')], 'Subscription request created. Please visit the clinic assistant to complete payment.');
    } catch (Throwable $e) {
        api_error('Error processing subscription: ' . $e->getMessage(), 500);
    }
}

if ($action === 'online_payment') {
    $prices = ['basic' => 29, 'premium' => 49, 'family' => 79];
    $amount = (float) $prices[$plan];
    $annualAmount = $amount * 12;

    $_SESSION['pending_subscription'] = [
        'plan' => $plan,
        'amount' => $annualAmount,
        'patient_id' => (int) $patientId,
        'user_id' => $userId,
    ];

    api_ok(['redirect' => url('patient/owo_payment.php')], 'Redirecting to payment...');
}

api_error('Invalid action.', 400);

