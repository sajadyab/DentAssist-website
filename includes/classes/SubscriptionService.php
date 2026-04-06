<?php
declare(strict_types=1);

class SubscriptionService
{
    /**
     * Confirms a clinic payment for a pending subscription and activates it.
     *
     * @return array{ok:bool,error:?string,end_date:?string,plan:?string}
     */
    public static function confirmClinicPayment(int $patientId, string $reference, int $processedBy): array
    {
        $db = Database::getInstance();

        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+1 year'));

        try {
            $plan = SubscriptionRepository::getPatientSubscriptionPlan($patientId) ?? 'basic';

            $db->beginTransaction();

            SubscriptionRepository::activateSubscription($patientId, $startDate, $endDate);
            SubscriptionRepository::completePendingPayment($patientId, $reference, $processedBy);

            $invoiceId = InvoiceRepository::findPendingSubscriptionInvoiceId($patientId);
            if ($invoiceId !== null) {
                InvoiceRepository::markPaid($invoiceId);
            } else {
                // Same pricing logic as assistant_subscriptions.php (kept here to avoid behavior changes).
                $prices = ['basic' => 29, 'premium' => 49, 'family' => 79];
                $annualAmount = (float) (($prices[$plan] ?? 29) * 12);

                $invoiceNumber = generateInvoiceNumber();
                $notes = "Subscription: {$plan} plan (Annual) - Paid at Clinic";
                InvoiceRepository::createPaidSubscriptionInvoice(
                    $patientId,
                    $invoiceNumber,
                    $annualAmount,
                    $startDate,
                    $startDate,
                    $notes,
                    $processedBy
                );
            }

            $db->commit();

            return ['ok' => true, 'error' => null, 'end_date' => $endDate, 'plan' => $plan];
        } catch (Exception $e) {
            $db->rollback();
            return ['ok' => false, 'error' => $e->getMessage(), 'end_date' => null, 'plan' => null];
        }
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public static function rejectPendingSubscription(int $patientId, string $reason): array
    {
        try {
            SubscriptionRepository::resetSubscriptionToNone($patientId);
            SubscriptionRepository::failPendingPayment($patientId, 'Rejected: ' . $reason);

            return ['ok' => true, 'error' => null];
        } catch (Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
