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
            "UPDATE patients SET subscription_type = ?, subscription_start_date = ?, subscription_end_date = ?, subscription_status = 'pending', sync_status = 'pending' WHERE id = ?",
            [$plan, $startDate, $endDate, (int) $patientId],
            'sssi'
        );

        $invoiceNumber = generateInvoiceNumber();
        $invoiceColumns = ['patient_id', 'invoice_number', 'subtotal', 'payment_status', 'invoice_date', 'due_date', 'notes', 'created_by'];
        $invoiceParams = [
            (int) $patientId,
            $invoiceNumber,
            $annualAmount,
            'pending',
            $startDate,
            $startDate,
            "Subscription: {$plan} plan (Annual) - Pending Payment",
            $userId,
        ];
        $invoiceTypes = 'isdssssi';
        if (dbColumnExists('invoices', 'total_amount')) {
            array_splice($invoiceColumns, 3, 0, 'total_amount');
            array_splice($invoiceParams, 3, 0, $annualAmount);
            $invoiceTypes = 'isddssssi';
        }

        $invoiceId = $db->insert(
            'INSERT INTO invoices (' . implode(', ', $invoiceColumns) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($invoiceColumns), '?')) . ')',
            $invoiceParams,
            $invoiceTypes
        );
        if (!$invoiceId) {
            throw new RuntimeException('Failed to create invoice.');
        }

        sync_push_row_now('invoices', $invoiceId);

        // Cloud-first: insert subscription_payment to cloud first
        $cloudPaymentPayload = [
            'patient_id' => (int) $patientId,
            'subscription_type' => $plan,
            'amount' => $annualAmount,
            'payment_method' => 'clinic',
            'payment_date' => date('Y-m-d H:i:s'),
            'status' => 'pending',
            'processed_by' => $userId,
            'notes' => 'Pending payment at clinic - Please visit assistant',
        ];

        try {
            $cloudPaymentId = patient_portal_cloud_insert_get_id('subscription_payments', $cloudPaymentPayload);
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to create payment record in cloud: ' . $e->getMessage());
        }

        // Now insert locally with cloud_id
        $db->insert(
            "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_date, status, processed_by, notes, cloud_id)
             VALUES (?, ?, ?, 'clinic', NOW(), 'pending', ?, 'Pending payment at clinic - Please visit assistant', ?)",
            [(int) $patientId, $plan, $annualAmount, $userId, $cloudPaymentId],
            'isdii'
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
