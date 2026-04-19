<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/supabase_client.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "debug-start\n";
try {
    $s = new SupabaseAPI(SUPABASE_URL, SUPABASE_KEY);
    echo "supabase-client-ok\n";
} catch (Throwable $e) {
    echo "supabase-client-fail: " . $e->getMessage() . "\n";
    exit(1);
}

$tables = ['clinic_settings','treatment_plans','treatment_steps'];
foreach ($tables as $t) {
    echo "=== {$t} columns ===\n";
    try {
        $cols = $s->select('information_schema.columns', [
            'select' => 'column_name',
            'table_schema' => 'eq.public',
            'table_name' => 'eq.' . $t,
            'limit' => 200,
        ]);
        $names = [];
        foreach ($cols as $c) { $names[] = (string)($c['column_name'] ?? ''); }
        echo implode(',', $names) . "\n";
    } catch (Throwable $e) {
        echo 'ERR columns: ' . $e->getMessage() . "\n";
    }

    echo "=== {$t} sample ===\n";
    try {
        $rows = $s->select($t, ['select' => '*', 'limit' => 5]);
        echo 'rows=' . count($rows) . "\n";
        foreach ($rows as $r) {
            $id = $r['id'] ?? '';
            $local = $r['local_id'] ?? '';
            $key = $r['setting_key'] ?? '';
            $name = $r['plan_name'] ?? ($r['procedure_name'] ?? '');
            echo 'id=' . (is_scalar($id)?(string)$id:json_encode($id)) . ' local_id=' . (is_scalar($local)?(string)$local:json_encode($local)) . ' key=' . (string)$key . ' name=' . (string)$name . "\n";
        }
    } catch (Throwable $e) {
        echo 'ERR sample: ' . $e->getMessage() . "\n";
    }
}

echo "debug-end\n";
?>