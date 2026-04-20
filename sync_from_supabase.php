<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/supabase_client.php';

const PULL_BATCH_LIMIT = 100;

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

// Read-only pull key
$supabase = new SupabaseAPI((string) SUPABASE_URL, (string) SUPABASE_KEY);

echo "Starting cloud -> local sync...\n";

function safeIdentifier(string $name): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Unsafe identifier: ' . $name);
    }

    return $name;
}

function getSyncableTables(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT TABLE_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND COLUMN_NAME = 'sync_status'
         ORDER BY TABLE_NAME"
    );

    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Define dependency order - parent tables first
    $dependencyOrder = [
        'users',           // No dependencies
        'patients',        // Depends on users
        'appointments',    // Depends on patients, users
        'appointment_requests', // May depend on patients
        'treatment_plans', // Depends on patients
        'treatment_steps', // Depends on treatment_plans
        'invoices',        // Depends on patients
        'payments',        // Depends on invoices
        'inventory',       // No dependencies
        'inventory_transactions', // Depends on inventory
        'clinic_settings', // No dependencies
        'clinic_arrivals', // Depends on patients
        'monthly_expenses', // No dependencies
        'audit_log',       // May depend on users
    ];

    // Sort tables by dependency order, then alphabetically for remaining tables
    $orderedTables = [];
    $remainingTables = $allTables;

    foreach ($dependencyOrder as $table) {
        if (in_array($table, $allTables, true)) {
            $orderedTables[] = $table;
            $remainingTables = array_diff($remainingTables, [$table]);
        }
    }

    // Add any remaining tables not in dependency order
    $orderedTables = array_merge($orderedTables, $remainingTables);

    return $orderedTables;
}

function ensureSyncObservabilityTables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sync_runtime_status (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            table_name VARCHAR(64) NOT NULL,
            local_id BIGINT NOT NULL,
            direction ENUM('local_to_cloud','local_to_cloud_delete','cloud_to_local') NOT NULL DEFAULT 'local_to_cloud',
            action VARCHAR(32) NOT NULL DEFAULT 'upsert',
            status ENUM('pending','in_progress','synced','failed') NOT NULL DEFAULT 'pending',
            message TEXT NULL,
            payload_json LONGTEXT NULL,
            attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
            last_started DATETIME NULL,
            last_finished DATETIME NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_sync_runtime_status (table_name, local_id, direction, action),
            KEY idx_sync_runtime_status_status (status),
            KEY idx_sync_runtime_status_updated (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sync_operation_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            table_name VARCHAR(64) NOT NULL,
            local_id BIGINT NOT NULL,
            direction ENUM('local_to_cloud','local_to_cloud_delete','cloud_to_local') NOT NULL DEFAULT 'local_to_cloud',
            action VARCHAR(32) NOT NULL DEFAULT 'upsert',
            status ENUM('pending','in_progress','synced','failed') NOT NULL,
            message TEXT NULL,
            payload_json LONGTEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sync_operation_log_table (table_name, local_id),
            KEY idx_sync_operation_log_status (status),
            KEY idx_sync_operation_log_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function recordSyncRuntimeStatus(PDO $pdo, string $tableName, int $localId, string $direction, string $action, string $status, ?string $message = null): void
{
    if ($tableName === '' || $localId <= 0) {
        return;
    }
    ensureSyncObservabilityTables($pdo);
    $stmt = $pdo->prepare(
        "INSERT INTO sync_runtime_status
            (table_name, local_id, direction, action, status, message, attempt_count, last_started, last_finished)
         VALUES (?, ?, ?, ?, ?, ?, ?, " . ($status === 'in_progress' ? 'NOW()' : 'NULL') . ", " . (in_array($status, ['synced', 'failed'], true) ? 'NOW()' : 'NULL') . ")
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            message = VALUES(message),
            attempt_count = CASE WHEN VALUES(status) = 'in_progress' THEN attempt_count + 1 ELSE attempt_count END,
            last_started = CASE WHEN VALUES(status) = 'in_progress' THEN NOW() ELSE last_started END,
            last_finished = CASE WHEN VALUES(status) IN ('synced','failed') THEN NOW() ELSE last_finished END"
    );
    $stmt->execute([
        $tableName,
        $localId,
        $direction,
        $action,
        $status,
        $message !== null ? substr($message, 0, 4000) : null,
        $status === 'in_progress' ? 1 : 0,
    ]);

    $log = $pdo->prepare(
        "INSERT INTO sync_operation_log
            (table_name, local_id, direction, action, status, message)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $log->execute([
        $tableName,
        $localId,
        $direction,
        $action,
        $status,
        $message !== null ? substr($message, 0, 4000) : null,
    ]);
}

function ensureSyncMetadataTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sync_metadata (
            table_name VARCHAR(64) NOT NULL,
            last_cloud_sync DATETIME NULL,
            PRIMARY KEY (table_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function getLastCloudSync(PDO $pdo, string $tableName): ?string
{
    ensureSyncMetadataTable($pdo);

    $stmt = $pdo->prepare('SELECT last_cloud_sync FROM sync_metadata WHERE table_name = ? LIMIT 1');
    $stmt->execute([$tableName]);
    $value = $stmt->fetchColumn();

    return $value !== false && $value !== null ? (string) $value : null;
}

function setLastCloudSyncNow(PDO $pdo, string $tableName): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sync_metadata (table_name, last_cloud_sync)
         VALUES (?, NOW())
         ON DUPLICATE KEY UPDATE last_cloud_sync = VALUES(last_cloud_sync)'
    );
    $stmt->execute([$tableName]);
}

function getTableColumns(PDO $pdo, string $tableName): array
{
    $table = safeIdentifier($tableName);
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
         ORDER BY ORDINAL_POSITION"
    );
    $stmt->execute([$table]);

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getPrimaryKeyColumn(PDO $pdo, string $tableName): string
{
    $table = safeIdentifier($tableName);
    $stmt = $pdo->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
    $pk = $stmt->fetch(PDO::FETCH_ASSOC);

    return (string) ($pk['Column_name'] ?? 'id');
}

function normalizeCloudTimestampForSupabase(string $mysqlDateTime): string
{
    // 2026-04-08 20:10:00 -> 2026-04-08T20:10:00Z
    return str_replace(' ', 'T', $mysqlDateTime) . 'Z';
}

function syncCloudTableToLocal(PDO $pdo, SupabaseAPI $supabase, string $tableName): void
{
    $table = safeIdentifier($tableName);
    $localColumns = getTableColumns($pdo, $table);
    $localColumnsMap = array_flip($localColumns);
    $pkColumn = getPrimaryKeyColumn($pdo, $table);

    if (!isset($localColumnsMap[$pkColumn])) {
        echo "Table {$table}: skipped (local PK column missing)\n";

        return;
    }
    if (!isset($localColumnsMap['sync_status'])) {
        echo "Table {$table}: skipped (sync_status column missing)\n";

        return;
    }

    $lastSync = getLastCloudSync($pdo, $table) ?? '1970-01-01 00:00:00';

    $query = [
        'select' => '*',
        'updated_at' => 'gt.' . normalizeCloudTimestampForSupabase($lastSync),
        'order' => 'updated_at.asc',
        'limit' => PULL_BATCH_LIMIT,
    ];

    try {
        $cloudRows = $supabase->select($table, $query);
    } catch (Throwable $e) {
        $msg = strtolower($e->getMessage());
        $missingUpdatedAt = str_contains($msg, 'updated_at') && str_contains($msg, 'does not exist');
        if (!$missingUpdatedAt) {
            echo "Table {$table}: fetch failed: {$e->getMessage()}\n";

            return;
        }

        // Fallback for cloud tables without updated_at.
        try {
            $cloudRows = $supabase->select($table, [
                'select' => '*',
                'limit' => PULL_BATCH_LIMIT,
            ]);
        } catch (Throwable $fallbackErr) {
            echo "Table {$table}: fetch failed: {$fallbackErr->getMessage()}\n";

            return;
        }
    }

    if (!is_array($cloudRows)) {
        $cloudRows = [];
    }

    echo "Table {$table}: " . count($cloudRows) . " cloud rows fetched\n";

    if (empty($cloudRows)) {
        return;
    }

    $selectLocal = $pdo->prepare("SELECT `{$pkColumn}`, `sync_status` FROM `{$table}` WHERE `{$pkColumn}` = ? LIMIT 1");

    foreach ($cloudRows as $cloudRowRaw) {
        if (!is_array($cloudRowRaw)) {
            continue;
        }

        $cloudRow = array_change_key_case($cloudRowRaw, CASE_LOWER);
        $localId = (int) ($cloudRow['local_id'] ?? 0);
        if ($localId <= 0) {
            continue;
        }
        recordSyncRuntimeStatus($pdo, $table, $localId, 'cloud_to_local', 'upsert', 'in_progress', 'Cloud->local sync started');

        $selectLocal->execute([$localId]);
        $existingLocal = $selectLocal->fetch(PDO::FETCH_ASSOC) ?: null;

        if (is_array($existingLocal) && (string) ($existingLocal['sync_status'] ?? '') === 'pending') {
            echo "  skip {$table}#{$localId} (local pending)\n";
            continue;
        }

        $data = [];
        foreach ($cloudRow as $column => $value) {
            if ($column === 'id' || $column === 'local_id') {
                continue;
            }
            if (isset($localColumnsMap[$column])) {
                $data[$column] = $value;
            }
        }

        $data['sync_status'] = 'synced';
        if (isset($localColumnsMap['sync_error'])) {
            $data['sync_error'] = null;
        }
        if (isset($localColumnsMap['last_sync_attempt'])) {
            $data['last_sync_attempt'] = date('Y-m-d H:i:s');
        }

        try {
            if (is_array($existingLocal)) {
                if (!empty($data)) {
                    $setParts = [];
                    $values = [];
                    foreach ($data as $column => $value) {
                        $setParts[] = '`' . safeIdentifier((string) $column) . '` = ?';
                        $values[] = $value;
                    }
                    $values[] = $localId;

                    $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE `{$pkColumn}` = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($values);
                }
            } else {
                $insert = $data;
                $insert[$pkColumn] = $localId;

                $columns = array_keys($insert);
                $columnSql = implode(', ', array_map(static function ($c): string {
                    return '`' . safeIdentifier((string) $c) . '`';
                }, $columns));
                $placeholderSql = implode(', ', array_fill(0, count($columns), '?'));

                $sql = "INSERT INTO `{$table}` ({$columnSql}) VALUES ({$placeholderSql})";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(array_values($insert));
            }
        } catch (Throwable $e) {
            recordSyncRuntimeStatus($pdo, $table, $localId, 'cloud_to_local', 'upsert', 'failed', $e->getMessage());
            echo "  failed {$table}#{$localId}: {$e->getMessage()}\n";
            continue;
        }
        recordSyncRuntimeStatus($pdo, $table, $localId, 'cloud_to_local', 'upsert', 'synced', 'Cloud->local sync succeeded');
    }

    // Per requirement: move sync cursor to NOW() after processing this batch.
    setLastCloudSyncNow($pdo, $table);
}

try {
    $tables = getSyncableTables($pdo);
    foreach ($tables as $tableName) {
        syncCloudTableToLocal($pdo, $supabase, (string) $tableName);
    }
} catch (Throwable $e) {
    echo 'Fatal sync_from_supabase error: ' . $e->getMessage() . "\n";
}

echo "cloud -> local sync completed\n";
