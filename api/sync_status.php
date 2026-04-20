<?php
declare(strict_types=1);

require_once __DIR__ . '/_helpers.php';

api_require_method('GET');
api_require_login();

$role = (string) ($_SESSION['role'] ?? '');
if (!in_array($role, ['doctor', 'assistant'], true) && !Auth::isAdmin()) {
    api_error('Forbidden.', 403);
}

$db = Database::getInstance();

$tables = $db->fetchAll(
    "SELECT TABLE_NAME
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND COLUMN_NAME = 'sync_status'
     ORDER BY TABLE_NAME"
);

$summary = [];
foreach ($tables as $row) {
    $table = (string) ($row['TABLE_NAME'] ?? '');
    if ($table === '') {
        continue;
    }
    $stats = $db->fetchAll(
        "SELECT sync_status, COUNT(*) AS c
         FROM `{$table}`
         GROUP BY sync_status"
    );
    $bucket = [
        'pending' => 0,
        'in_progress' => 0,
        'synced' => 0,
        'failed' => 0,
    ];
    foreach ($stats as $s) {
        $k = strtolower((string) ($s['sync_status'] ?? ''));
        $v = (int) ($s['c'] ?? 0);
        if (array_key_exists($k, $bucket)) {
            $bucket[$k] = $v;
        }
    }
    $summary[$table] = $bucket;
}

$runtimeCurrent = [];
$runtimeRecent = [];
if (dbTableExists('sync_runtime_status')) {
    $runtimeCurrent = $db->fetchAll(
        "SELECT table_name, local_id, direction, action, status, message, attempt_count, last_started, last_finished, updated_at
         FROM sync_runtime_status
         ORDER BY updated_at DESC
         LIMIT 200"
    );
}
if (dbTableExists('sync_operation_log')) {
    $runtimeRecent = $db->fetchAll(
        "SELECT id, table_name, local_id, direction, action, status, message, created_at
         FROM sync_operation_log
         ORDER BY id DESC
         LIMIT 300"
    );
}

$deleteQueue = [];
if (dbTableExists('sync_delete_queue')) {
    $deleteQueue = $db->fetchAll(
        "SELECT status, COUNT(*) AS c
         FROM sync_delete_queue
         GROUP BY status"
    );
}

api_ok([
    'summary' => $summary,
    'runtime_current' => $runtimeCurrent,
    'runtime_recent' => $runtimeRecent,
    'delete_queue' => $deleteQueue,
    'generated_at' => date('c'),
], 'Sync status snapshot');

