<?php
/**
 * auto_add_missing_supabase_columns.php
 * 
 * Automatically adds missing columns to Supabase tables by:
 * 1. Comparing local MySQL schema with Supabase
 * 2. Using Supabase SQL editor integration to create missing columns
 * 3. Ensuring local_id unique constraints exist
 * 
 * LIMITATIONS: 
 * - Cannot directly execute SQL against Supabase via REST API
 * - Instead, generates SQL statements you can copy/paste or use via admin API
 * - OR uses a workaround: INSERT → UPDATE to force schema inference
 * 
 * Run: php auto_add_missing_supabase_columns.php
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

echo "=== Missing Column Detector & SQL Generator ===\n\n";

// Get syncable tables
$stmt = $pdo->query(
    "SELECT DISTINCT TABLE_NAME
     FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND COLUMN_NAME = 'sync_status'
     ORDER BY TABLE_NAME"
);
$syncableTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$migrationsNeeded = [];

foreach ($syncableTables as $tableName) {
    echo "Checking {$tableName}...\n";

    // Get local columns
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION"
    );
    $stmt->execute([$tableName]);
    $localCols = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get Supabase columns by making a SELECT
    $cloudCols = [];
    try {
        $result = $supabase->select($tableName, ['limit' => 1]);
        if (!empty($result)) {
            foreach ($result[0] as $k => $v) {
                $cloudCols[$k] = null; // We don't know exact types
            }
        }
    } catch (Throwable $e) {
        echo "  ✗ Cannot access table in Supabase (does it exist?): {$tableName}\n";
        continue;
    }

    // Find missing columns (business columns only)
    $syncCols = [
        'sync_status', 'last_modified', 'last_sync_attempt', 'sync_error',
        'id', 'local_id', 'created_at', 'updated_at'
    ];

    $missing = [];
    foreach ($localCols as $col) {
        $colName = $col['COLUMN_NAME'];
        if (!in_array(strtolower($colName), array_map('strtolower', $syncCols), true)) {
            if (!isset($cloudCols[$colName])) {
                $missing[$colName] = $col;
            }
        }
    }

    if (!empty($missing)) {
        echo "  Missing columns:\n";
        foreach ($missing as $colName => $info) {
            echo "    - {$colName} ({$info['COLUMN_TYPE']})\n";
        }
        $migrationsNeeded[$tableName] = $missing;
    } else {
        echo "  ✓ All columns present\n";
    }
}

echo "\n";

// Generate SQL migrations
if (!empty($migrationsNeeded)) {
    echo "=== SQL MIGRATIONS (Copy to Supabase SQL Editor) ===\n\n";

    $allSql = [];

    foreach ($migrationsNeeded as $tableName => $missingCols) {
        foreach ($missingCols as $colName => $info) {
            // Map MySQL types to PostgreSQL
            $pgType = mapMySQLTypeToPG($info['COLUMN_TYPE']);
            $nullable = $info['IS_NULLABLE'] === 'YES' ? '' : ' NOT NULL';
            $default = $info['COLUMN_DEFAULT'] ? " DEFAULT {$info['COLUMN_DEFAULT']}" : '';

            $allSql[] = "ALTER TABLE {$tableName} ADD COLUMN IF NOT EXISTS {$colName} {$pgType}{$nullable}{$default};";
        }
    }

    foreach ($allSql as $sql) {
        echo $sql . "\n";
    }

    // Also generate unique constraint creation
    echo "\n-- Add unique constraints on local_id:\n";
    foreach (array_keys($migrationsNeeded) as $tableName) {
        echo "CREATE UNIQUE INDEX IF NOT EXISTS idx_{$tableName}_local_id_unique ON {$tableName}(local_id);\n";
    }

    // Save to file
    $migrationFile = __DIR__ . '/supabase_auto_migration.sql';
    file_put_contents($migrationFile, implode("\n", $allSql) . "\n");
    echo "\n✓ Saved to " . basename($migrationFile) . "\n";
} else {
    echo "✓ No missing columns found!\n";
}

function mapMySQLTypeToPG(string $mysqlType): string
{
    $mysql = strtoupper($mysqlType);

    return match (true) {
        str_contains($mysql, 'INT') => 'BIGINT',
        str_contains($mysql, 'VARCHAR') => 'TEXT',
        str_contains($mysql, 'TEXT') => 'TEXT',
        str_contains($mysql, 'DATETIME') => 'TIMESTAMPTZ',
        str_contains($mysql, 'TIMESTAMP') => 'TIMESTAMPTZ',
        str_contains($mysql, 'DATE') => 'DATE',
        str_contains($mysql, 'ENUM') => 'VARCHAR(255)',
        str_contains($mysql, 'BOOLEAN') => 'BOOLEAN',
        str_contains($mysql, 'DECIMAL') => 'DECIMAL',
        str_contains($mysql, 'FLOAT') => 'FLOAT',
        str_contains($mysql, 'DOUBLE') => 'DOUBLE PRECISION',
        str_contains($mysql, 'JSON') => 'JSONB',
        default => 'TEXT',
    };
}

echo "\n=== NEXT STEPS ===\n\n";
echo "1. Go to https://app.supabase.com → Your Project → SQL Editor\n";
echo "2. Create a new query and paste the SQL statements above\n";
echo "3. Execute to add missing columns\n";
echo "4. Then run: php sync_to_supabase_fixed.php\n";
