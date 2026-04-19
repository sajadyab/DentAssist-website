<?php
/**
 * Diagnostic script to compare local MySQL schema with Supabase tables.
 * Checks which columns are missing in Supabase for tables being synced.
 * 
 * Run: php check_missing_columns.php
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/supabase_client.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$supabase = new SupabaseAPI((string) SUPABASE_URL, (string) SUPABASE_KEY);

// Get all syncable tables (those with sync_status column)
$stmt = $pdo->query(
    "SELECT DISTINCT TABLE_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND COLUMN_NAME = 'sync_status'
     ORDER BY TABLE_NAME"
);
$syncableTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "=== SCHEMA COMPARISON: Local MySQL vs Supabase ===\n\n";

$missingColumnsReport = [];

foreach ($syncableTables as $tableName) {
    echo "Table: {$tableName}\n";
    
    // Get local columns (excluding sync tracking columns)
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION"
    );
    $stmt->execute([$tableName]);
    $localColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Try to get Supabase columns by making a select query
    $cloudColumns = [];
    try {
        // Fetch one row to inspect structure
        $result = $supabase->select($tableName, ['limit' => 1]);
        if (!empty($result) && is_array($result[0])) {
            $cloudColumns = array_keys($result[0]);
        } else {
            // Table might be empty—try introspection via REST API error
            echo "  ⚠  Table exists in Supabase but is empty (cannot determine columns)\n";
        }
    } catch (Throwable $e) {
        echo "  ✗ Cannot access table in Supabase: " . $e->getMessage() . "\n";
        continue;
    }
    
    // Business columns (excluding sync tracking)
    $syncCols = ['sync_status', 'last_modified', 'last_sync_attempt', 'sync_error', 'id', 'local_id', 'created_at', 'updated_at'];
    $localBusiness = array_values(array_filter(
        $localColumns,
        fn($col) => !in_array(strtolower($col), array_map('strtolower', $syncCols), true)
    ));
    
    // Find missing columns
    $missing = array_diff(
        array_map('strtolower', $localBusiness),
        array_map('strtolower', $cloudColumns)
    );
    
    if (!empty($missing)) {
        echo "  Missing columns in Supabase:\n";
        foreach ($missing as $col) {
            echo "    - {$col}\n";
        }
        $missingColumnsReport[$tableName] = $missing;
    } else {
        echo "  ✓ All business columns present in Supabase\n";
    }
    
    echo "\n";
}

if (!empty($missingColumnsReport)) {
    echo "\n=== SQL MIGRATION: ADD MISSING COLUMNS ===\n\n";
    foreach ($missingColumnsReport as $tableName => $missingCols) {
        echo "-- Table: {$tableName}\n";
        foreach ($missingCols as $col) {
            echo "ALTER TABLE {$tableName} ADD COLUMN IF NOT EXISTS {$col} TEXT;\n";
        }
        echo "\n";
    }
}

echo "\n=== SYNC METADATA ===\n";
echo "Local tables with sync_status column: " . count($syncableTables) . "\n";
foreach ($syncableTables as $table) {
    echo "  - {$table}\n";
}
