<?php
declare(strict_types=1);

require_once __DIR__ . '/../supabase_client.php';

if (!function_exists('sync_cloud_enabled')) {
    function sync_cloud_enabled(): bool
    {
        $url = defined('SUPABASE_URL') ? trim((string) SUPABASE_URL) : '';
        $key = defined('SUPABASE_KEY') ? trim((string) SUPABASE_KEY) : '';

        return $url !== '' && $key !== '';
    }
}

if (!function_exists('sync_ensure_observability_tables')) {
    function sync_ensure_observability_tables(Database $db): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        $ensured = true;
        try {
            $conn = $db->getConnection();
            $conn->query(
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
            $conn->query(
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
        } catch (Throwable $e) {
            error_log('sync_ensure_observability_tables failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('sync_sanitize_payload')) {
    function sync_sanitize_payload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }
        $sensitive = ['password_hash', 'token', 'access_token', 'refresh_token'];
        $sanitized = [];
        foreach ($payload as $k => $v) {
            $key = strtolower((string) $k);
            if (in_array($key, $sensitive, true)) {
                $sanitized[$k] = '[redacted]';
                continue;
            }
            $sanitized[$k] = $v;
        }

        return $sanitized;
    }
}

if (!function_exists('sync_record_runtime_status')) {
    function sync_record_runtime_status(
        Database $db,
        string $table,
        int $localId,
        string $direction,
        string $action,
        string $status,
        ?string $message = null,
        ?array $payload = null,
        bool $started = false,
        bool $finished = false
    ): void {
        if ($localId <= 0 || $table === '') {
            return;
        }
        sync_ensure_observability_tables($db);
        $payload = sync_sanitize_payload($payload);
        $payloadJson = $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
        if ($payloadJson !== null && strlen($payloadJson) > 20000) {
            $payloadJson = substr($payloadJson, 0, 20000);
        }
        try {
            $db->execute(
                "INSERT INTO sync_runtime_status
                    (table_name, local_id, direction, action, status, message, payload_json, attempt_count, last_started, last_finished)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, " . ($started ? 'NOW()' : 'NULL') . ", " . ($finished ? 'NOW()' : 'NULL') . ")
                 ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    message = VALUES(message),
                    payload_json = COALESCE(VALUES(payload_json), payload_json),
                    attempt_count = CASE WHEN VALUES(status) = 'in_progress' THEN attempt_count + 1 ELSE attempt_count END,
                    last_started = CASE WHEN VALUES(status) = 'in_progress' THEN NOW() ELSE last_started END,
                    last_finished = CASE WHEN VALUES(status) IN ('synced','failed') THEN NOW() ELSE last_finished END",
                [
                    $table,
                    $localId,
                    $direction,
                    $action,
                    $status,
                    $message !== null ? substr($message, 0, 4000) : null,
                    $payloadJson,
                    $status === 'in_progress' ? 1 : 0,
                ],
                'sisssssi'
            );
            $db->insert(
                "INSERT INTO sync_operation_log
                    (table_name, local_id, direction, action, status, message, payload_json)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [
                    $table,
                    $localId,
                    $direction,
                    $action,
                    $status,
                    $message !== null ? substr($message, 0, 4000) : null,
                    $payloadJson,
                ],
                'sisssss'
            );
        } catch (Throwable $e) {
            error_log('sync_record_runtime_status failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('sync_safe_identifier')) {
    function sync_safe_identifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('Unsafe identifier: ' . $name);
        }

        return $name;
    }
}

if (!function_exists('sync_supabase_client')) {
    function sync_supabase_client(): ?SupabaseAPI
    {
        static $client = null;
        static $ready = false;

        if ($ready) {
            return $client;
        }
        $ready = true;

        if (!sync_cloud_enabled()) {
            return null;
        }

        try {
            $client = new SupabaseAPI((string) SUPABASE_URL, (string) SUPABASE_KEY);
        } catch (Throwable $e) {
            error_log('sync_supabase_client init failed: ' . $e->getMessage());
            $client = null;
        }

        return $client;
    }
}

if (!function_exists('sync_table_columns')) {
    function sync_table_columns(Database $db, string $table): array
    {
        static $cache = [];
        $table = sync_safe_identifier($table);
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $rows = $db->fetchAll(
            "SELECT COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?",
            [$table],
            's'
        );
        $cols = [];
        foreach ($rows as $r) {
            $c = strtolower((string) ($r['COLUMN_NAME'] ?? ''));
            if ($c !== '') {
                $cols[$c] = true;
            }
        }
        $cache[$table] = $cols;

        return $cols;
    }
}

if (!function_exists('sync_primary_key_column')) {
    function sync_primary_key_column(Database $db, string $table): string
    {
        static $cache = [];
        $table = sync_safe_identifier($table);
        if (isset($cache[$table])) {
            return $cache[$table];
        }

        $row = $db->fetchOne("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        $pk = (string) ($row['Column_name'] ?? 'id');
        $cache[$table] = $pk !== '' ? $pk : 'id';

        return $cache[$table];
    }
}

if (!function_exists('sync_update_local_state')) {
    function sync_update_local_state(Database $db, string $table, string $pkColumn, int $pkValue, string $state, ?string $error = null): void
    {
        $table = sync_safe_identifier($table);
        $pk = sync_safe_identifier($pkColumn);
        $cols = sync_table_columns($db, $table);

        $setParts = [];
        $values = [];
        $types = '';

        if (isset($cols['sync_status'])) {
            $setParts[] = 'sync_status = ?';
            $values[] = $state;
            $types .= 's';
        }
        if (isset($cols['last_sync_attempt'])) {
            $setParts[] = 'last_sync_attempt = NOW()';
        }
        if (isset($cols['sync_error'])) {
            $setParts[] = 'sync_error = ?';
            $values[] = $error !== null ? substr($error, 0, 4000) : null;
            $types .= 's';
        }

        if (empty($setParts)) {
            return;
        }

        $values[] = $pkValue;
        $types .= 'i';

        $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE `{$pk}` = ?";
        $db->execute($sql, $values, $types);
    }
}

if (!function_exists('sync_cloud_columns')) {
    function sync_cloud_columns(SupabaseAPI $supabase, string $table): ?array
    {
        static $cache = [];
        $table = sync_safe_identifier($table);
        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        try {
            $rows = $supabase->select('information_schema.columns', [
                'select' => 'column_name',
                'table_schema' => 'eq.public',
                'table_name' => 'eq.' . $table,
                'limit' => 1000,
            ]);
            $cols = [];
            foreach ($rows as $r) {
                $c = (string) ($r['column_name'] ?? '');
                if ($c !== '') {
                    $cols[] = $c;
                }
            }
            $cache[$table] = !empty($cols) ? array_values(array_unique($cols)) : null;
        } catch (Throwable $e) {
            $cache[$table] = null;
        }

        return $cache[$table];
    }
}

if (!function_exists('sync_filter_payload_by_cloud_columns')) {
    function sync_filter_payload_by_cloud_columns(array $payload, ?array $cloudColumns): array
    {
        if ($cloudColumns === null) {
            return $payload;
        }

        $out = [];
        foreach ($payload as $k => $v) {
            if (in_array((string) $k, $cloudColumns, true)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }
}

if (!function_exists('sync_sanitize_patient_payload')) {
    function sync_sanitize_patient_payload(array $payload): array
    {
        if (array_key_exists('gender', $payload)) {
            $gender = $payload['gender'];
            if ($gender === null || trim((string) $gender) === '' || !in_array((string) $gender, ['male', 'female', 'other'], true)) {
                unset($payload['gender']);
            }
        }

        return $payload;
    }
}

if (!function_exists('sync_is_duplicate_primary_key_conflict')) {
    function sync_is_duplicate_primary_key_conflict(Throwable $e): bool
    {
        $msg = strtolower($e->getMessage());
        return str_contains($msg, 'duplicate key value violates unique constraint')
            && str_contains($msg, '_pkey');
    }
}

if (!function_exists('sync_extract_missing_column_from_error')) {
    function sync_extract_missing_column_from_error(Throwable $e): ?string
    {
        $msg = $e->getMessage();
        if (preg_match("/Could not find the '([A-Za-z0-9_]+)' column/i", $msg, $m)) {
            return strtolower((string) $m[1]);
        }

        return null;
    }
}

if (!function_exists('sync_extract_unique_constraint_column')) {
    function sync_extract_unique_constraint_column(Throwable $e, array $payload = []): ?string
    {
        $msg = strtolower($e->getMessage());
        if (!str_contains($msg, 'duplicate key value violates unique constraint')) {
            return null;
        }

        if (preg_match('/"([^"]+)"/', $msg, $m)) {
            $constraint = strtolower((string) $m[1]);
            if (str_ends_with($constraint, '_key')) {
                $core = substr($constraint, 0, -4);
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
}

if (!function_exists('sync_conflict_fallback_columns')) {
    function sync_conflict_fallback_columns(string $table): array
    {
        $map = [
            'clinic_settings' => ['setting_key', 'id', 'local_id'],
            'monthly_expenses' => ['month_year', 'id', 'local_id'],
            'invoices' => ['invoice_number', 'id', 'local_id'],
            'subscription_plans' => ['plan_key', 'id', 'local_id'],
            'tooth_chart' => ['patient_id', 'tooth_number', 'id', 'local_id'],
            'users' => ['email', 'username', 'id', 'local_id'],
        ];

        return $map[strtolower($table)] ?? ['id', 'local_id'];
    }
}

if (!function_exists('sync_safe_insert_with_column_stripping')) {
    function sync_safe_insert_with_column_stripping(SupabaseAPI $supabase, string $table, array $payload, int $maxRetries = 6): array
    {
        $current = $payload;
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $supabase->insert($table, $current);

                return $current;
            } catch (Throwable $e) {
                $missing = sync_extract_missing_column_from_error($e);
                if ($missing === null || !array_key_exists($missing, $current)) {
                    throw $e;
                }
                unset($current[$missing]);
            }
        }

        return $current;
    }
}

if (!function_exists('sync_safe_update_with_column_stripping')) {
    function sync_safe_update_with_column_stripping(SupabaseAPI $supabase, string $table, array $payload, array $filter, int $maxRetries = 6): array
    {
        $current = $payload;
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $supabase->update($table, $current, $filter);

                return $current;
            } catch (Throwable $e) {
                $missing = sync_extract_missing_column_from_error($e);
                if ($missing === null || !array_key_exists($missing, $current)) {
                    throw $e;
                }
                unset($current[$missing]);
            }
        }

        return $current;
    }
}

if (!function_exists('sync_safe_upsert_with_column_stripping')) {
    function sync_safe_upsert_with_column_stripping(SupabaseAPI $supabase, string $table, array $payload, string $onConflict, int $maxRetries = 6): array
    {
        $current = $payload;
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $supabase->upsert($table, $current, $onConflict);

                return $current;
            } catch (Throwable $e) {
                $missing = sync_extract_missing_column_from_error($e);
                if ($missing === null || !array_key_exists($missing, $current)) {
                    throw $e;
                }
                unset($current[$missing]);
            }
        }

        return $current;
    }
}

if (!function_exists('sync_push_row_now')) {
    function sync_push_row_now(string $table, int $localId): bool
    {
        if ($localId <= 0) {
            return false;
        }

        $supabase = sync_supabase_client();
        if ($supabase === null) {
            return false;
        }

        $db = Database::getInstance();
        $table = sync_safe_identifier($table);
        $pkColumn = sync_primary_key_column($db, $table);
        $pkLower = strtolower($pkColumn);

        $row = $db->fetchOne("SELECT * FROM `{$table}` WHERE `{$pkColumn}` = ? LIMIT 1", [$localId], 'i');
        if (!$row) {
            return false;
        }

        $row = array_change_key_case($row, CASE_LOWER);
        $payload = $row;
        unset($payload['id'], $payload[$pkLower], $payload['sync_status'], $payload['last_modified'], $payload['last_updated'], $payload['last_sync_attempt'], $payload['sync_error']);
        $payload['local_id'] = $localId;

        $cloudCols = sync_cloud_columns($supabase, $table);
        $payload = sync_filter_payload_by_cloud_columns($payload, $cloudCols);
        $payload = sync_sanitize_patient_payload($payload);
        sync_record_runtime_status($db, $table, $localId, 'local_to_cloud', 'upsert', 'in_progress', 'Sync started', $payload, true, false);

        $matchColumn = 'local_id';
        $matchValue = $localId;
        if (is_array($cloudCols) && !in_array('local_id', $cloudCols, true) && in_array('id', $cloudCols, true)) {
            $matchColumn = 'id';
        }

        try {
            $existing = $supabase->select($table, [
                'select' => 'id',
                $matchColumn => 'eq.' . $matchValue,
                'limit' => 1,
            ]);

            if (!empty($existing)) {
                $payload = sync_safe_update_with_column_stripping($supabase, $table, $payload, [$matchColumn => $matchValue]);
            } else {
                try {
                    $payload = sync_safe_insert_with_column_stripping($supabase, $table, $payload);
                } catch (Throwable $insertErr) {
                    if (sync_is_duplicate_primary_key_conflict($insertErr)) {
                        $payloadWithId = $payload;
                        $payloadWithId['id'] = $localId;
                        $payload = sync_safe_upsert_with_column_stripping($supabase, $table, $payloadWithId, 'id');
                    } else {
                        $uniqueColumn = sync_extract_unique_constraint_column($insertErr, $payload);
                        $candidateColumns = $uniqueColumn !== null
                            ? [$uniqueColumn]
                            : sync_conflict_fallback_columns($table);
                        $resolved = false;

                        foreach ($candidateColumns as $candidate) {
                            if (!isset($payload[$candidate])) {
                                continue;
                            }
                            $candidateValue = (string) $payload[$candidate];
                            if ($candidateValue === '') {
                                continue;
                            }
                            $rows = $supabase->select($table, [
                                'select' => 'id,local_id',
                                $candidate => 'eq.' . $candidateValue,
                                'limit' => 1,
                            ]);
                            if (empty($rows)) {
                                continue;
                            }

                            $payload = sync_safe_update_with_column_stripping($supabase, $table, $payload, [$candidate => $candidateValue]);
                            if (is_array($cloudCols) && in_array('local_id', $cloudCols, true)) {
                                try {
                                    sync_safe_update_with_column_stripping(
                                        $supabase,
                                        $table,
                                        ['local_id' => $localId],
                                        [$candidate => $candidateValue]
                                    );
                                } catch (Throwable $ignored) {
                                    // best effort only
                                }
                            }
                            $resolved = true;
                            break;
                        }

                        if (!$resolved) {
                            throw $insertErr;
                        }
                    }
                }
            }

            sync_update_local_state($db, $table, $pkColumn, $localId, 'synced', null);
            sync_record_runtime_status($db, $table, $localId, 'local_to_cloud', 'upsert', 'synced', 'Synced successfully', $payload, false, true);
            error_log("sync_push_row_now synced {$table}#{$localId}");
            return true;
        } catch (Throwable $e) {
            sync_update_local_state($db, $table, $pkColumn, $localId, 'failed', $e->getMessage());
            sync_record_runtime_status($db, $table, $localId, 'local_to_cloud', 'upsert', 'failed', $e->getMessage(), $payload, false, true);
            error_log("sync_push_row_now failed {$table}#{$localId}: " . $e->getMessage());

            return false;
        }
    }
}

if (!function_exists('sync_process_delete_queue_now')) {
    function sync_process_delete_queue_now(int $limit = 20): void
    {
        $supabase = sync_supabase_client();
        if ($supabase === null) {
            return;
        }

        $db = Database::getInstance();
        $rows = $db->fetchAll(
            "SELECT id, table_name, local_id, match_column
             FROM sync_delete_queue
             WHERE status = 'pending'
                OR (status = 'failed' AND (last_attempt IS NULL OR last_attempt <= DATE_SUB(NOW(), INTERVAL 1 MINUTE)))
             ORDER BY id ASC
             LIMIT ?",
            [$limit],
            'i'
        );

        foreach ($rows as $row) {
            $queueId = (int) ($row['id'] ?? 0);
            $tableName = (string) ($row['table_name'] ?? '');
            $localId = (int) ($row['local_id'] ?? 0);
            $matchColumn = (string) ($row['match_column'] ?? 'local_id');
            if ($queueId <= 0 || $localId <= 0 || $tableName === '') {
                continue;
            }

            try {
                $table = sync_safe_identifier($tableName);
                $col = sync_safe_identifier($matchColumn);
                $touched = false;
                $preferHardDelete = in_array($table, ['treatment_steps', 'treatment_plans'], true);
                sync_record_runtime_status($db, $table, $localId, 'local_to_cloud_delete', 'delete', 'in_progress', 'Delete sync started', null, true, false);

                if (!$touched) {
                    try {
                        $res = $supabase->delete($table, [$col => $localId]);
                        $touched = is_array($res) && !empty($res);
                    } catch (Throwable $delErr) {
                        // continue
                    }
                }
                if (!$touched && !$preferHardDelete) {
                    try {
                        $res = $supabase->update($table, ['deleted' => 1], [$col => $localId]);
                        $touched = is_array($res) && !empty($res);
                    } catch (Throwable $softErr) {
                        // continue
                    }
                }
                if (!$touched && $col === 'local_id') {
                    try {
                        $res = $supabase->delete($table, ['id' => $localId]);
                        $touched = is_array($res) && !empty($res);
                    } catch (Throwable $delIdErr) {
                        // continue
                    }
                }
                if (!$touched && $col === 'local_id' && !$preferHardDelete) {
                    try {
                        $res = $supabase->update($table, ['deleted' => 1], ['id' => $localId]);
                        $touched = is_array($res) && !empty($res);
                    } catch (Throwable $softIdErr) {
                        // continue
                    }
                }
                if (!$touched) {
                    throw new RuntimeException("No matching cloud row found for delete {$table} {$col}={$localId}");
                }
                $db->execute(
                    "UPDATE sync_delete_queue
                     SET status = 'synced', last_attempt = NOW(), error_text = NULL
                     WHERE id = ?",
                    [$queueId],
                    'i'
                );
                sync_record_runtime_status($db, $table, $localId, 'local_to_cloud_delete', 'delete', 'synced', 'Delete synced successfully', null, false, true);
                error_log("sync_process_delete_queue_now synced delete {$table} {$col}={$localId}");
            } catch (Throwable $e) {
                $db->execute(
                    "UPDATE sync_delete_queue
                     SET status = 'failed', last_attempt = NOW(), error_text = ?
                     WHERE id = ?",
                    [substr($e->getMessage(), 0, 4000), $queueId],
                    'si'
                );
                if (!empty($tableName) && $localId > 0) {
                    sync_record_runtime_status($db, $tableName, $localId, 'local_to_cloud_delete', 'delete', 'failed', $e->getMessage(), null, false, true);
                }
                error_log('sync_process_delete_queue_now failed #' . $queueId . ': ' . $e->getMessage());
            }
        }
    }
}
