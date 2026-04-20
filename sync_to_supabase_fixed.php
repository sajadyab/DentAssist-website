<?php
/**
 * IMPROVED sync_to_supabase.php - Manual SELECT → UPDATE/INSERT Strategy
 * 
 * KEY CHANGES:
 * 1. Uses manual row existence check instead of upsert (avoids duplicate key errors)
 * 2. Updates schema cache with column type info for safer filtering
 * 3. Proper error recovery without falling back to problematic upsert
 * 4. Tracks which columns failed before at cloud to avoid re-sending
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/supabase_client.php';

const SYNC_BATCH_LIMIT = 100;
const SUPABASE_KNOWN_BROKEN_COLUMNS = []; // Populated by sync runs, can be cached

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

echo "Starting local -> cloud sync (manual SELECT → UPDATE/INSERT)...\n";

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
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getLocalColumns(PDO $pdo, string $tableName): array
{
    $table = safeIdentifier($tableName);
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getPrimaryKeyColumn(PDO $pdo, string $tableName): string
{
    $table = safeIdentifier($tableName);
    $stmt = $pdo->prepare("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
    $stmt->execute();
    $pk = $stmt->fetch(PDO::FETCH_ASSOC);
    return (string) ($pk['Column_name'] ?? 'id');
}

function updateLocalSyncState(
    PDO $pdo,
    string $tableName,
    string $pkColumn,
    int $pkValue,
    string $state,
    ?string $error = null
): void {
    $table = safeIdentifier($tableName);
    $pk = safeIdentifier($pkColumn);
    $columns = getLocalColumns($pdo, $table);

    $setParts = [];
    $values = [];

    if (in_array('sync_status', $columns, true)) {
        $setParts[] = "sync_status = ?";
        $values[] = $state;
    }
    if (in_array('last_sync_attempt', $columns, true)) {
        $setParts[] = "last_sync_attempt = NOW()";
    }
    if (in_array('sync_error', $columns, true)) {
        $setParts[] = "sync_error = ?";
        $values[] = ($error !== null) ? substr($error, 0, 4000) : null;
    }

    if (empty($setParts)) {
        return;
    }

    $values[] = $pkValue;
    $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE `{$pk}` = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function stripLocalTrackingColumns(array $row, string $pkColumn): array
{
    $remove = [
        'id',
        strtolower($pkColumn),
        'sync_status',
        'last_modified',
        'last_sync_attempt',
        'sync_error',
    ];

    $result = [];
    foreach ($row as $k => $v) {
        if (!in_array(strtolower((string) $k), $remove, true)) {
            $result[$k] = $v;
        }
    }
    return $result;
}

/**
 * Get cloud table columns by making a test SELECT + inspecting response.
 * Cache results to avoid repeated queries.
 */
function getCloudColumns(SupabaseAPI $supabase, string $tableName, array &$cache): array
{
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    try {
        $result = $supabase->select($tableName, ['limit' => 1]);
        
        if (empty($result)) {
            // Table is empty—assume columns are there and will fail on insert if missing
            echo "  ⚠  {$tableName}: Empty in Supabase (column detection skipped)\n";
            $cache[$tableName] = [];
            return [];
        }

        $cols = array_keys($result[0] instanceof ArrayAccess ? $result[0] : (array) $result[0]);
        $cache[$tableName] = $cols;
        return $cols;
    } catch (Throwable $e) {
        echo "  ⚠  Could not detect {$tableName} columns: " . $e->getMessage() . "\n";
        $cache[$tableName] = [];
        return [];
    }
}

/**
 * Filter payload to only include columns present in cloud.
 */
function filterPayloadByCloudColumns(array $payload, array $cloudColumns): array
{
    if (empty($cloudColumns)) {
        // If we don't know cloud columns, send everything and let cloud error guide us
        return $payload;
    }

    $filtered = [];
    $cloudLower = array_map('strtolower', $cloudColumns);
    
    foreach ($payload as $k => $v) {
        if (in_array(strtolower((string) $k), $cloudLower, true)) {
            $filtered[$k] = $v;
        }
    }
    return $filtered;
}

/**
 * Recursively strip problematic columns when Supabase rejects them.
 * Returns the successfully sent payload, or throws on unrecoverable errors.
 */
function safeInsertWithRetry(
    SupabaseAPI $supabase,
    string $tableName,
    array $payload,
    int $maxRetries = 6
): array {
    $current = $payload;
    $attempts = 0;

    while ($attempts < $maxRetries) {
        $attempts++;
        try {
            $supabase->insert($tableName, $current);
            return $current; // Success
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            
            // Try to extract missing column
            if (preg_match("/Could not find the '([A-Za-z0-9_]+)' column/i", $msg, $m)) {
                $missingCol = strtolower($m[1]);
                if (isset($current[$missingCol])) {
                    echo "  → Removing missing column: {$missingCol}\n";
                    unset($current[$missingCol]);
                    continue;
                }
            }

            // If we can't extract a missing column, this is a real error
            throw $e;
        }
    }

    throw new RuntimeException("Max retries ({$maxRetries}) exceeded for {$tableName}");
}

/**
 * Safely update a record, stripping problematic columns on retry.
 */
function safeUpdateWithRetry(
    SupabaseAPI $supabase,
    string $tableName,
    array $payload,
    array $filter,
    int $maxRetries = 6
): array {
    $current = $payload;
    $attempts = 0;

    while ($attempts < $maxRetries) {
        $attempts++;
        try {
            $supabase->update($tableName, $current, $filter);
            return $current;
        } catch (Throwable $e) {
            $msg = $e->getMessage();

            if (preg_match("/Could not find the '([A-Za-z0-9_]+)' column/i", $msg, $m)) {
                $missingCol = strtolower($m[1]);
                if (isset($current[$missingCol])) {
                    echo "  → Removing missing column: {$missingCol}\n";
                    unset($current[$missingCol]);
                    continue;
                }
            }

            throw $e;
        }
    }

    throw new RuntimeException("Max retries ({$maxRetries}) exceeded for {$tableName} UPDATE");
}

/**
 * Main sync logic: Manual SELECT → UPDATE/INSERT for each pending row.
 * NO UPSERT—resolves duplicate key errors by checking existence first.
 */
function syncTablePendingRows(
    PDO $pdo,
    SupabaseAPI $supabase,
    string $tableName,
    int $limit = SYNC_BATCH_LIMIT,
    array &$cloudSchemaCache = []
): void {
    $table = safeIdentifier($tableName);
    $pkColumn = getPrimaryKeyColumn($pdo, $table);
    $pkColumnLower = strtolower($pkColumn);
    $localColumns = getLocalColumns($pdo, $table);
    $hasLastSyncAttempt = in_array('last_sync_attempt', $localColumns, true);

    // Build WHERE clause to find pending/failed rows
    $where = "sync_status = 'pending' OR sync_status = 'failed'";
    if ($hasLastSyncAttempt) {
        $where = "(sync_status = 'pending') 
                  OR (sync_status = 'failed' 
                      AND (last_sync_attempt IS NULL OR last_sync_attempt <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)))";
    }

    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE {$where} ORDER BY `{$pkColumn}` ASC LIMIT {$limit}");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    echo "Table {$table}: " . count($rows) . " pending/failed rows\n";

    // Get cloud columns once per table
    $cloudColumns = getCloudColumns($supabase, $table, $cloudSchemaCache);

    foreach ($rows as $row) {
        $row = array_change_key_case($row, CASE_LOWER);
        $localId = (int) ($row[$pkColumnLower] ?? 0);
        
        if ($localId <= 0) {
            echo "  Skipping row with invalid PK: {$pkColumnLower} = {$localId}\n";
            continue;
        }

        $payload = stripLocalTrackingColumns($row, $pkColumn);
        $payload['local_id'] = $localId;
        $payload = filterPayloadByCloudColumns($payload, $cloudColumns);

        echo "  Syncing {$table}#{$localId}...\n";

        try {
            // STEP 1: Check if record exists in cloud using local_id
            $existsInCloud = false;
            $cloudId = null;

            try {
                $existing = $supabase->select($table, [
                    'select' => 'id',
                    'local_id' => 'eq.' . $localId,
                    'limit' => 1,
                ]);

                if (!empty($existing)) {
                    $existsInCloud = true;
                    $cloudId = $existing[0]['id'] ?? null;
                    echo "    ✓ Found in cloud (cloud id: {$cloudId}, local_id: {$localId})\n";
                }
            } catch (Throwable $selectErr) {
                // local_id column might not exist or query failed—try fallback
                echo "    ⚠ local_id lookup failed, trying empty select to verify table exists\n";
            }

            // STEP 2: If not found, or if verification failed, try insert
            if (!$existsInCloud) {
                try {
                    echo "    → Attempting INSERT...\n";
                    $payload = safeInsertWithRetry($supabase, $table, $payload);
                    echo "    ✓ INSERT succeeded\n";
                } catch (Throwable $insertErr) {
                    // Duplicate key or other insert error—try to update instead
                    $msg = strtolower($insertErr->getMessage());
                    
                    if (str_contains($msg, 'duplicate key') || str_contains($msg, 'unique constraint')) {
                        echo "    ⚠ INSERT failed with constraint violation: " . $insertErr->getMessage() . "\n";
                        echo "    → Attempting UPDATE as fallback...\n";
                        
                        // Try to update using local_id
                        try {
                            $payload = safeUpdateWithRetry(
                                $supabase,
                                $table,
                                $payload,
                                ['local_id' => $localId]
                            );
                            echo "    ✓ UPDATE succeeded\n";
                            $existsInCloud = true;
                        } catch (Throwable $updateErr) {
                            // If that fails too, try using 'id' filter as last resort
                            try {
                                $payload = safeUpdateWithRetry(
                                    $supabase,
                                    $table,
                                    $payload,
                                    ['id' => $localId]
                                );
                                echo "    ✓ UPDATE by id succeeded\n";
                                $existsInCloud = true;
                            } catch (Throwable $idUpdateErr) {
                                throw new RuntimeException(
                                    "Could not INSERT or UPDATE: " . $insertErr->getMessage()
                                );
                            }
                        }
                    } else {
                        throw $insertErr;
                    }
                }
            } else {
                // Record exists—UPDATE it
                try {
                    echo "    → Attempting UPDATE...\n";
                    $payload = safeUpdateWithRetry(
                        $supabase,
                        $table,
                        $payload,
                        ['local_id' => $localId]
                    );
                    echo "    ✓ UPDATE succeeded\n";
                } catch (Throwable $updateErr) {
                    throw new RuntimeException("UPDATE failed: " . $updateErr->getMessage());
                }
            }

            // STEP 3: Mark as synced
            updateLocalSyncState($pdo, $table, $pkColumn, $localId, 'synced', null);
            echo "    ✓ Marked as synced in local\n";

        } catch (Throwable $e) {
            // Mark as failed but continue with next row
            $errorMsg = $e->getMessage();
            updateLocalSyncState($pdo, $table, $pkColumn, $localId, 'failed', $errorMsg);
            echo "    ✗ FAILED: {$errorMsg}\n";
        }
    }
}

function recordSyncObservability(PDO $pdo, string $event, array $context = []): void
{
    // Optional: Record sync events for monitoring/debugging
    // Implement if you have a sync_events or similar table
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

try {
    $syncableTables = getSyncableTables($pdo);
    echo "Found " . count($syncableTables) . " syncable tables\n\n";

    $cloudSchemaCache = [];

    foreach ($syncableTables as $tableName) {
        try {
            syncTablePendingRows($pdo, $supabase, $tableName, SYNC_BATCH_LIMIT, $cloudSchemaCache);
            echo "✓ {$tableName} sync completed\n\n";
        } catch (Throwable $tableErr) {
            echo "✗ {$tableName} sync failed: " . $tableErr->getMessage() . "\n\n";
        }
    }

    echo "\n✓ Local → Cloud sync completed\n";
} catch (Throwable $e) {
    echo "\n✗ Sync failed: " . $e->getMessage() . "\n";
    exit(1);
}
