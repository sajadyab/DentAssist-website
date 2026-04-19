<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/supabase_client.php';

$localPdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

$supabase = new SupabaseAPI((string) SUPABASE_URL, (string) SUPABASE_KEY);

$table = $argv[1] ?? 'clinic_arrivals';
if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    fwrite(STDERR, "Invalid table name\n");
    exit(1);
}

$localStmt = $localPdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION");
$localStmt->execute([$table]);
$localCols = $localStmt->fetchAll(PDO::FETCH_COLUMN);

$cloudRows = $supabase->select('information_schema.columns', [
    'select' => 'column_name',
    'table_schema' => 'eq.public',
    'table_name' => 'eq.' . $table,
    'limit' => 2000,
]);
$cloudCols = [];
foreach ($cloudRows as $r) {
    $c = (string) ($r['column_name'] ?? '');
    if ($c !== '') {
        $cloudCols[] = $c;
    }
}
$cloudCols = array_values(array_unique($cloudCols));

$missingInCloud = array_values(array_diff($localCols, $cloudCols));
$extraInCloud = array_values(array_diff($cloudCols, $localCols));

echo "Table: {$table}\n";
echo "Local columns: " . count($localCols) . "\n";
echo "Cloud columns: " . count($cloudCols) . "\n\n";

echo "Missing in cloud:\n";
foreach ($missingInCloud as $c) {
    echo " - {$c}\n";
}

echo "\nExtra in cloud:\n";
foreach ($extraInCloud as $c) {
    echo " - {$c}\n";
}

if (!empty($missingInCloud)) {
    echo "\nSuggested ALTER statements (review data types manually):\n";
    foreach ($missingInCloud as $c) {
        echo "ALTER TABLE public.{$table} ADD COLUMN IF NOT EXISTS {$c} text;\n";
    }
}
