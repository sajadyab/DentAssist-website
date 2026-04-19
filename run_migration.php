<?php
include 'includes/db.php';
$db = Database::getInstance();

$queries = [
    "ALTER TABLE invoices ADD COLUMN cloud_id BIGINT NULL AFTER id",
    "ALTER TABLE subscription_payments ADD COLUMN cloud_id BIGINT NULL AFTER id", 
    "ALTER TABLE patients ADD COLUMN cloud_id BIGINT NULL AFTER id",
    "ALTER TABLE invoices ADD INDEX idx_invoices_cloud_id (cloud_id)",
    "ALTER TABLE subscription_payments ADD INDEX idx_subscription_payments_cloud_id (cloud_id)",
    "ALTER TABLE patients ADD INDEX idx_patients_cloud_id (cloud_id)",
];

foreach ($queries as $query) {
    try {
        $db->execute($query, [], '');
        echo "Executed: $query\n";
    } catch (Exception $e) {
        echo "Failed: $query - " . $e->getMessage() . "\n";
    }
}

echo "Migration completed\n";
?>