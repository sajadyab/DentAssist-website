<?php
declare(strict_types=1);

class InvoiceRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listForPatient(int $patientId): array
    {
        $db = Database::getInstance();

        return $db->fetchAll(
            "SELECT * FROM invoices
             WHERE patient_id = ?
             ORDER BY invoice_date DESC",
            [$patientId],
            'i'
        );
    }

    public static function findPendingSubscriptionInvoiceId(int $patientId): ?int
    {
        $db = Database::getInstance();

        $row = $db->fetchOne(
            "SELECT id FROM invoices WHERE patient_id = ? AND notes LIKE '%Subscription%' AND payment_status = 'pending'",
            [$patientId],
            'i'
        );

        $id = (int) ($row['id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    public static function markPaid(int $invoiceId): bool
    {
        $db = Database::getInstance();
        return $db->execute(
            'UPDATE invoices SET payment_status = \'paid\', paid_amount = total_amount, paid_at = NOW() WHERE id = ?',
            [$invoiceId],
            'i'
        ) !== false;
    }

    public static function createPaidSubscriptionInvoice(
        int $patientId,
        string $invoiceNumber,
        float $amount,
        string $invoiceDate,
        string $dueDate,
        string $notes,
        int $createdBy
    ): int {
        $db = Database::getInstance();

        return (int) $db->insert(
            "INSERT INTO invoices (patient_id, invoice_number, subtotal, total_amount, payment_status, invoice_date, due_date, notes, created_by, paid_at)
             VALUES (?, ?, ?, ?, 'paid', ?, ?, ?, ?, NOW())",
            [$patientId, $invoiceNumber, $amount, $amount, $invoiceDate, $dueDate, $notes, $createdBy],
            'isddsssi'
        );
    }

    public static function deleteForPatient(int $patientId): bool
    {
        $db = Database::getInstance();
        return $db->execute('DELETE FROM invoices WHERE patient_id = ?', [$patientId], 'i') !== false;
    }
}
