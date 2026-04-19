<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/supabase_client.php';

const SYNC_BATCH_LIMIT = 100;

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

echo "Starting local -> cloud sync...\n";

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

function sanitizeSyncPayload(?array $payload): ?array
{
    if ($payload === null) {
        return null;
    }
    $sensitive = ['password_hash', 'token', 'access_token', 'refresh_token'];
    $out = [];
    foreach ($payload as $k => $v) {
        $key = strtolower((string) $k);
        $out[$k] = in_array($key, $sensitive, true) ? '[redacted]' : $v;
    }

    return $out;
}

function recordSyncRuntimeStatus(PDO $pdo, string $tableName, int $localId, string $direction, string $action, string $status, ?string $message = null, ?array $payload = null): void
{
    if ($tableName === '' || $localId <= 0) {
        return;
    }
    ensureSyncObservabilityTables($pdo);
    $payload = sanitizeSyncPayload($payload);
    $payloadJson = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
    if ($payloadJson !== null && strlen($payloadJson) > 20000) {
        $payloadJson = substr($payloadJson, 0, 20000);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO sync_runtime_status
            (table_name, local_id, direction, action, status, message, payload_json, attempt_count, last_started, last_finished)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, " . ($status === 'in_progress' ? 'NOW()' : 'NULL') . ", " . (in_array($status, ['synced', 'failed'], true) ? 'NOW()' : 'NULL') . ")
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            message = VALUES(message),
            payload_json = COALESCE(VALUES(payload_json), payload_json),
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
        $payloadJson,
        $status === 'in_progress' ? 1 : 0,
    ]);

    $log = $pdo->prepare(
        "INSERT INTO sync_operation_log
            (table_name, local_id, direction, action, status, message, payload_json)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $log->execute([
        $tableName,
        $localId,
        $direction,
        $action,
        $status,
        $message !== null ? substr($message, 0, 4000) : null,
        $payloadJson,
    ]);
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

function updateLocalSyncState(PDO $pdo, string $tableName, string $pkColumn, int $pkValue, string $state, ?string $error = null): void
{
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

    foreach ($remove as $col) {
        unset($row[$col]);
    }

    return $row;
}

function getCloudColumns(SupabaseAPI $supabase, string $tableName, array &$cache): ?array
{
    if (array_key_exists($tableName, $cache)) {
        return $cache[$tableName];
    }

    try {
        $rows = $supabase->select('information_schema.columns', [
            'select' => 'column_name',
            'table_schema' => 'eq.public',
            'table_name' => 'eq.' . $tableName,
            'limit' => 1000,
        ]);
        $cols = [];
        foreach ($rows as $r) {
            $name = (string) ($r['column_name'] ?? '');
            if ($name !== '') {
                $cols[] = $name;
            }
        }
        $cache[$tableName] = !empty($cols) ? array_values(array_unique($cols)) : null;

        return $cache[$tableName];
    } catch (Throwable $e) {
        $cache[$tableName] = null;

        return null;
    }
}

function filterPayloadByCloudColumns(array $payload, ?array $cloudColumns): array
{
    if ($cloudColumns === null) {
        return $payload;
    }

    $filtered = [];
    foreach ($payload as $k => $v) {
        if (in_array((string) $k, $cloudColumns, true)) {
            $filtered[$k] = $v;
        }
    }

    return $filtered;
}

function isDuplicatePrimaryKeyConflict(Throwable $e): bool
{
    $msg = strtolower($e->getMessage());
    return str_contains($msg, 'duplicate key value violates unique constraint')
        && str_contains($msg, '_pkey');
}

function extractMissingColumnFromSupabaseError(Throwable $e): ?string
{
    $msg = $e->getMessage();
    if (preg_match("/Could not find the '([A-Za-z0-9_]+)' column/i", $msg, $m)) {
        return strtolower((string) $m[1]);
    }

    return null;
}

function extractUniqueConstraintColumn(Throwable $e, array $payload = []): ?string
{
    $msg = strtolower($e->getMessage());
    if (!str_contains($msg, 'duplicate key value violates unique constraint')) {
        return null;
    }

    if (preg_match('/"([^"]+)"/', $msg, $m)) {
        $constraint = strtolower((string) $m[1]);
        if (str_ends_with($constraint, '_key')) {
            $core = substr($constraint, 0, -4); // remove trailing _key
            if (!empty($payload)) {
                $best = null;
                foreach (array_keys($payload) as $col) {
                    $col = strtolower((string) $col);
                    if ($core === $col || str_ends_with($core, '_' . $col)) {
                        if ($best === null || strlen($col) > strlen($best)) {
                            $best = $col;
                        }
                    }
                }
                if ($best !== null) {
                    return $best;
                }
            }
        }
    }

    return null;
}

function getConflictFallbackColumns(string $table): array
{
    $map = [
        'clinic_settings' => ['setting_key', 'id', 'local_id'],
        'monthly_expenses' => ['month_year', 'id', 'local_id'],
        'invoices' => ['invoice_number', 'id', 'local_id'],
        'subscription_plans' => ['plan_key', 'id', 'local_id'],
        'users' => ['email', 'username', 'id', 'local_id'],
    ];

    return $map[strtolower($table)] ?? ['id', 'local_id'];
}

function safeInsertWithColumnStripping(SupabaseAPI $supabase, string $table, array $payload, int $maxRetries = 6): array
{
    $current = $payload;
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $supabase->insert($table, $current);

            return $current;
        } catch (Throwable $e) {
            $missing = extractMissingColumnFromSupabaseError($e);
            if ($missing === null || !array_key_exists($missing, $current)) {
                throw $e;
            }
            unset($current[$missing]);
        }
    }

    return $current;
}

function safeUpdateWithColumnStripping(SupabaseAPI $supabase, string $table, array $payload, array $filter, int $maxRetries = 6): array
{
    $current = $payload;
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $supabase->update($table, $current, $filter);

            return $current;
        } catch (Throwable $e) {
            $missing = extractMissingColumnFromSupabaseError($e);
            if ($missing === null || !array_key_exists($missing, $current)) {
                throw $e;
            }
            unset($current[$missing]);
        }
    }

    return $current;
}

function safeUpsertWithColumnStripping(SupabaseAPI $supabase, string $table, array $payload, string $onConflict, int $maxRetries = 6): array
{
    $current = $payload;
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            $supabase->upsert($table, $current, $onConflict);

            return $current;
        } catch (Throwable $e) {
            $missing = extractMissingColumnFromSupabaseError($e);
            if ($missing === null || !array_key_exists($missing, $current)) {
                throw $e;
            }
            unset($current[$missing]);
        }
    }

    return $current;
}

function syncTablePendingRows(PDO $pdo, SupabaseAPI $supabase, string $tableName, int $limit = SYNC_BATCH_LIMIT, array &$cloudSchemaCache = []): void
{
    $table = safeIdentifier($tableName);
    $pkColumn = getPrimaryKeyColumn($pdo, $table);
    $pkColumnLower = strtolower($pkColumn);
    $localColumns = getLocalColumns($pdo, $table);
    $hasLastSyncAttempt = in_array('last_sync_attempt', $localColumns, true);

    $where = "sync_status = 'pending' OR sync_status = 'failed'";
    if ($hasLastSyncAttempt) {
        $where = "sync_status = 'pending' OR (sync_status = 'failed' AND (last_sync_attempt IS NULL OR last_sync_attempt <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)))";
    }

    $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE {$where} ORDER BY `{$pkColumn}` ASC LIMIT {$limit}");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    echo "Table {$table}: " . count($rows) . " pending/failed rows\n";

    $cloudColumns = getCloudColumns($supabase, $table, $cloudSchemaCache);

    foreach ($rows as $row) {
        $row = array_change_key_case($row, CASE_LOWER);
        $localId = (int) ($row[$pkColumnLower] ?? 0);
        if ($localId <= 0) {
            continue;
        }

        $payload = stripLocalTrackingColumns($row, $pkColumn);
        $payload['local_id'] = $localId;
        $payload = filterPayloadByCloudColumns($payload, $cloudColumns);
        recordSyncRuntimeStatus($pdo, $table, $localId, 'local_to_cloud', 'upsert', 'in_progress', 'Batch sync started', $payload);

        $existsFilter = ['local_id' => $localId];
        if (is_array($cloudColumns) && !in_array('local_id', $cloudColumns, true) && in_array('id', $cloudColumns, true)) {
            $existsFilter = ['id' => $localId];
        }

        try {
            // Manual existence check (select -> update/insert).
            try {
                $existing = $supabase->select($table, [
                    'select' => 'id',
                    array_key_first($existsFilter) => 'eq.' . array_values($existsFilter)[0],
                    'limit' => 1,
                ]);
            } catch (Throwable $existsErr) {
                // Fallback for cloud tables without local_id mapping.
                $existing = $supabase->select($table, [
                    'select' => 'id',
                    'id' => 'eq.' . $localId,
                    'limit' => 1,
                ]);
                $existsFilter = ['id' => $localId];
            }

            if (!empty($existing)) {
                $payload = safeUpdateWithColumnStripping($supabase, $table, $payload, $existsFilter);
            } else {
                try {
                    $payload = safeInsertWithColumnStripping($supabase, $table, $payload);
                } catch (Throwable $insertErr) {
                    // Recovery for cloud tables where numeric PK sequence drift causes duplicate *_pkey on insert.
                    if (isDuplicatePrimaryKeyConflict($insertErr)) {
                        $payloadWithId = $payload;
                        $payloadWithId['id'] = $localId;
                        $payload = safeUpsertWithColumnStripping($supabase, $table, $payloadWithId, 'id');
                    } else {
                        $uniqueCol = extractUniqueConstraintColumn($insertErr, $payload);
                        $conflictCandidates = [];
                        if ($uniqueCol !== null) {
                            $conflictCandidates[] = $uniqueCol;
                        }
                        $conflictCandidates = array_values(array_unique(array_merge(
                            $conflictCandidates,
                            getConflictFallbackColumns($table)
                        )));

                        $resolved = false;
                        foreach ($conflictCandidates as $candidate) {
                            if (!array_key_exists($candidate, $payload)) {
                                continue;
                            }
                            try {
                                $payload = safeUpsertWithColumnStripping($supabase, $table, $payload, $candidate);
                                $resolved = true;
                                break;
                            } catch (Throwable $upsertErr) {
                                // try next candidate
                            }
                        }
                        if (!$resolved) {
                            throw $insertErr;
                        }
                    }
                }
            }

            // If local_id exists in cloud and was missing in payload due prior stripping, ensure it is set.
            if (is_array($cloudColumns) && in_array('local_id', $cloudColumns, true)) {
                try {
                    $supabase->update($table, ['local_id' => $localId], ['local_id' => $localId]);
                } catch (Throwable $ignored) {
                    // best effort only
                }
            }

            updateLocalSyncState($pdo, $table, $pkColumn, $localId, 'synced', null);
            recordSyncRuntimeStatus($pdo, $table, $localId, 'local_to_cloud', 'upsert', 'synced', 'Batch sync succeeded', $payload);
        } catch (Throwable $e) {
            updateLocalSyncState($pdo, $table, $pkColumn, $localId, 'failed', $e->getMessage());
            recordSyncRuntimeStatus($pdo, $table, $localId, 'local_to_cloud', 'upsert', 'failed', $e->getMessage(), $payload);
            echo "  failed {$table}#{$localId}: " . $e->getMessage() . "\n";
        }
    }
}

function ensureDeleteQueueTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS sync_delete_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            table_name VARCHAR(64) NOT NULL,
            local_id BIGINT NOT NULL,
            match_column VARCHAR(64) NOT NULL DEFAULT 'local_id',
            status ENUM('pending','synced','failed') NOT NULL DEFAULT 'pending',
            last_attempt DATETIME NULL,
            error_text TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_sync_delete_queue_status (status),
            KEY idx_sync_delete_queue_table (table_name, local_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );
}

function processDeleteQueue(PDO $pdo, SupabaseAPI $supabase, int $limit = SYNC_BATCH_LIMIT): void
{
    ensureDeleteQueueTable($pdo);

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sync_delete_queue
         WHERE status = 'pending'
            OR (status = 'failed' AND (last_attempt IS NULL OR last_attempt <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)))
         ORDER BY id ASC
         LIMIT " . (int) $limit
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    echo 'Delete queue: ' . count($rows) . " items\n";

    foreach ($rows as $row) {
        $queueId = (int) ($row['id'] ?? 0);
        $tableName = (string) ($row['table_name'] ?? '');
        $localId = (int) ($row['local_id'] ?? 0);
        $matchColumn = (string) ($row['match_column'] ?? 'local_id');

        if ($queueId <= 0 || $tableName === '' || $localId <= 0) {
            continue;
        }

        try {
            $table = safeIdentifier($tableName);
            $column = safeIdentifier($matchColumn);
            $preferHardDelete = in_array($table, ['treatment_steps', 'treatment_plans'], true);
            recordSyncRuntimeStatus($pdo, $table, $localId, 'local_to_cloud_delete', 'delete', 'in_progress', 'Delete sync started', null);

            $touched = false;
            try {
                $res = $supabase->delete($table, [$column => $localId]);
                $touched = is_array($res) && !empty($res);
            } catch (Throwable $hardDeleteErr) {
                // continue
            }
            if (!$touched && !$preferHardDelete) {
                try {
                    $res = $supabase->update($table, ['deleted' => 1], [$column => $localId]);
                    $touched = is_array($res) && !empty($res);
                } catch (Throwable $softDeleteErr) {
                    // continue
                }
            }
            if (!$touched && $column === 'local_id') {
                try {
                    $res = $supabase->delete($table, ['id' => $localId]);
                    $touched = is_array($res) && !empty($res);
                } catch (Throwable $hardDeleteByIdErr) {
                    // continue
                }
            }
            if (!$touched && $column === 'local_id' && !$preferHardDelete) {
                try {
                    $res = $supabase->update($table, ['deleted' => 1], ['id' => $localId]);
                    $touched = is_array($res) && !empty($res);
                } catch (Throwable $softDeleteByIdErr) {
                    // continue
                }
            }
            if (!$touched) {
                throw new RuntimeException("No matching cloud row found for delete {$table} {$column}={$localId}");
            }

            $ok = $pdo->prepare("UPDATE sync_delete_queue SET status = 'synced', last_attempt = NOW(), error_text = NULL WHERE id = ?");
            $ok->execute([$queueId]);
            recordSyncRuntimeStatus($pdo, $table, $localId, 'local_to_cloud_delete', 'delete', 'synced', 'Delete synced successfully', null);
        } catch (Throwable $e) {
            $fail = $pdo->prepare("UPDATE sync_delete_queue SET status = 'failed', last_attempt = NOW(), error_text = ? WHERE id = ?");
            $fail->execute([substr($e->getMessage(), 0, 4000), $queueId]);
            if ($tableName !== '' && $localId > 0) {
                recordSyncRuntimeStatus($pdo, $tableName, $localId, 'local_to_cloud_delete', 'delete', 'failed', $e->getMessage(), null);
            }
            echo "  delete queue failed #{$queueId}: " . $e->getMessage() . "\n";
        }
    }
}

try {
    $tables = getSyncableTables($pdo);
    $cloudSchemaCache = [];
    foreach ($tables as $tableName) {
        syncTablePendingRows($pdo, $supabase, (string) $tableName, SYNC_BATCH_LIMIT, $cloudSchemaCache);
    }
    processDeleteQueue($pdo, $supabase);
} catch (Throwable $e) {
    echo 'Fatal sync_to_supabase error: ' . $e->getMessage() . "\n";
}

echo "local -> cloud sync completed\n";
