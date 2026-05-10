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
$billingCycle = (string) ($_POST['billing_cycle'] ?? 'monthly');
if (!in_array($billingCycle, ['monthly', 'yearly'], true)) {
    api_error('Invalid billing cycle.', 422);
}

$patient = $db->fetchOne(
    'SELECT subscription_type, subscription_status FROM patients WHERE id = ? LIMIT 1',
    [(int) $patientId],
    'i'
);
$currentPlan = (string) ($patient['subscription_type'] ?? 'none');
$currentStatus = (string) ($patient['subscription_status'] ?? 'none');
if ($currentPlan !== 'none' && in_array($currentStatus, ['pending', 'active'], true)) {
    api_error('You already have a subscription. Plans are available for viewing only.', 409);
}

$planRow = $db->fetchOne(
    'SELECT monthly_price, annual_price FROM subscription_plans WHERE plan_key = ? AND is_active = 1 LIMIT 1',
    [$plan],
    's'
);
$monthlyPrice = (float) ($planRow['monthly_price'] ?? ['basic' => 29, 'premium' => 49, 'family' => 79][$plan]);
$annualPrice = (float) ($planRow['annual_price'] ?? ($monthlyPrice * 12));
$amount = $billingCycle === 'yearly' ? $annualPrice : $monthlyPrice;
$periodLabel = $billingCycle === 'yearly' ? 'Yearly' : 'Monthly';
$startDate = date('Y-m-d');
$endDate = date('Y-m-d', strtotime($billingCycle === 'yearly' ? '+1 year' : '+1 month'));

if ($action === 'clinic_payment') {
    try {
        $db->execute(
            "UPDATE patients SET subscription_type = ?, subscription_start_date = ?, subscription_end_date = ?, subscription_status = 'pending', sync_status = 'pending' WHERE id = ?",
            [$plan, $startDate, $endDate, (int) $patientId],
            'sssi'
        );
        try {
            patient_portal_cloud_upsert_by_local_id_first('patients', (int) $patientId, [
                'subscription_type' => $plan,
                'subscription_start_date' => $startDate,
                'subscription_end_date' => $endDate,
                'subscription_status' => 'pending',
            ], []);
        } catch (Throwable $e) {
            error_log('Patient subscription cloud patient update failed: ' . $e->getMessage());
        }
        try {
            sync_push_row_now('patients', (int) $patientId);
        } catch (Throwable $e) {
            error_log('Patient subscription sync push failed: ' . $e->getMessage());
        }

        $invoiceNumber = generateInvoiceNumber();
        $invoiceColumns = ['patient_id', 'invoice_number', 'subtotal', 'payment_status', 'invoice_date', 'due_date', 'notes', 'created_by'];
        $invoiceParams = [
            (int) $patientId,
            $invoiceNumber,
            $amount,
            'pending',
            $startDate,
            $startDate,
            "Subscription: {$plan} plan ({$periodLabel}) - Pending Payment",
            $userId,
        ];
        $invoiceTypes = 'isdssssi';
        if (dbColumnExists('invoices', 'total_amount')) {
            array_splice($invoiceColumns, 3, 0, 'total_amount');
            array_splice($invoiceParams, 3, 0, $amount);
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

        try {
            sync_push_row_now('invoices', $invoiceId);
        } catch (Throwable $e) {
            error_log('Subscription invoice sync push failed: ' . $e->getMessage());
        }

        // Cloud-first: insert subscription_payment to cloud first
        $cloudPaymentPayload = [
            'patient_id' => (int) $patientId,
            'subscription_type' => $plan,
            'amount' => $amount,
            'payment_method' => 'clinic',
            'payment_date' => date('Y-m-d H:i:s'),
            'status' => 'pending',
            'processed_by' => $userId,
            'notes' => "{$periodLabel} billing - Pending payment at clinic - Please visit assistant",
        ];

        $cloudPaymentId = null;
        try {
            $cloudPaymentId = patient_portal_cloud_insert_get_id('subscription_payments', $cloudPaymentPayload);
        } catch (Throwable $e) {
            error_log('Failed to create subscription payment record in cloud: ' . $e->getMessage());
        }

        // Now insert locally with cloud_id
        if (dbColumnExists('subscription_payments', 'cloud_id')) {
            $db->insert(
                "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_date, status, processed_by, notes, cloud_id)
                 VALUES (?, ?, ?, 'clinic', NOW(), 'pending', ?, ?, ?)",
                [(int) $patientId, $plan, $amount, $userId, "{$periodLabel} billing - Pending payment at clinic - Please visit assistant", $cloudPaymentId],
                'isdisi'
            );
        } else {
            $db->insert(
                "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_date, status, processed_by, notes)
                 VALUES (?, ?, ?, 'clinic', NOW(), 'pending', ?, ?)",
                [(int) $patientId, $plan, $amount, $userId, "{$periodLabel} billing - Pending payment at clinic - Please visit assistant"],
                'isdis'
            );
        }

        api_ok(['redirect' => url('patient/subscription.php?success=1')], 'Subscription request created. Please visit the clinic assistant to complete payment.');
    } catch (Throwable $e) {
        api_error('Error processing subscription: ' . $e->getMessage(), 500);
    }
}

if ($action === 'online_payment') {
    try {
        $db->execute(
            "UPDATE patients SET subscription_type = ?, subscription_start_date = ?, subscription_end_date = ?, subscription_status = 'pending', sync_status = 'pending' WHERE id = ?",
            [$plan, $startDate, $endDate, (int) $patientId],
            'sssi'
        );
        try {
            patient_portal_cloud_upsert_by_local_id_first('patients', (int) $patientId, [
                'subscription_type' => $plan,
                'subscription_start_date' => $startDate,
                'subscription_end_date' => $endDate,
                'subscription_status' => 'pending',
            ], []);
        } catch (Throwable $e) {
            error_log('Patient subscription cloud patient update failed: ' . $e->getMessage());
        }
        try {
            sync_push_row_now('patients', (int) $patientId);
        } catch (Throwable $e) {
            error_log('Patient subscription sync push failed: ' . $e->getMessage());
        }

        $invoiceNumber = generateInvoiceNumber();
        $invoiceColumns = ['patient_id', 'invoice_number', 'subtotal', 'payment_status', 'invoice_date', 'due_date', 'notes', 'created_by'];
        $invoiceParams = [
            (int) $patientId,
            $invoiceNumber,
            $amount,
            'pending',
            $startDate,
            $startDate,
            "Subscription: {$plan} plan ({$periodLabel}) - Pending Wish payment",
            $userId,
        ];
        $invoiceTypes = 'isdssssi';
        if (dbColumnExists('invoices', 'total_amount')) {
            array_splice($invoiceColumns, 3, 0, 'total_amount');
            array_splice($invoiceParams, 3, 0, $amount);
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
        try {
            sync_push_row_now('invoices', $invoiceId);
        } catch (Throwable $e) {
            error_log('Subscription invoice sync push failed: ' . $e->getMessage());
        }

        $cloudPaymentPayload = [
            'patient_id' => (int) $patientId,
            'subscription_type' => $plan,
            'amount' => $amount,
            'payment_method' => 'owo',
            'payment_date' => date('Y-m-d H:i:s'),
            'status' => 'pending',
            'processed_by' => $userId,
            'notes' => "Pending Wish payment - Please complete payment through Wish/OWO",
        ];

        $cloudPaymentId = null;
        try {
            $cloudPaymentId = patient_portal_cloud_insert_get_id('subscription_payments', $cloudPaymentPayload);
        } catch (Throwable $e) {
            error_log('Failed to create subscription payment record in cloud: ' . $e->getMessage());
        }

        if (dbColumnExists('subscription_payments', 'cloud_id')) {
            $db->insert(
                "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_date, status, processed_by, notes, cloud_id)
                 VALUES (?, ?, ?, 'owo', NOW(), 'pending', ?, ?, ?)",
                [(int) $patientId, $plan, $amount, $userId, "Pending Wish payment - Please complete payment through Wish/OWO", $cloudPaymentId],
                'isdisi'
            );
        } else {
            $db->insert(
                "INSERT INTO subscription_payments (patient_id, subscription_type, amount, payment_method, payment_date, status, processed_by, notes)
                 VALUES (?, ?, ?, 'owo', NOW(), 'pending', ?, ?)",
                [(int) $patientId, $plan, $amount, $userId, "Pending Wish payment - Please complete payment through Wish/OWO"],
                'isdis'
            );
        }

        $_SESSION['pending_subscription'] = [
            'plan' => $plan,
            'billing_cycle' => $billingCycle,
            'amount' => $amount,
            'patient_id' => (int) $patientId,
            'user_id' => $userId,
        ];

        api_ok(['redirect' => url('patient/owo_payment.php')], 'Redirecting to payment...');
    } catch (Throwable $e) {
        api_error('Error processing Wish payment: ' . $e->getMessage(), 500);
    }
}

api_error('Invalid action.', 400);
