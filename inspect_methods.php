<?php
require_once __DIR__ . '/vendor/autoload.php';

use Supabase\Supabase\Supabase;

$client = new Supabase('https://example.com', 'dummy-key');

echo "<h3>Public methods in Supabase\Supabase\Supabase:</h3>";
echo "<pre>";
$methods = get_class_methods($client);
if ($methods) {
    foreach ($methods as $method) {
        echo " - $method\n";
    }
} else {
    echo "No methods found (maybe the class is a factory).\n";
}
echo "</pre>";

// Also check if there is a Supabase\Database class in the autoloader
$classMap = require __DIR__ . '/vendor/composer/autoload_classmap.php';
echo "<h3>Supabase-related classes found in autoloader:</h3>";
echo "<pre>";
foreach ($classMap as $class => $path) {
    if (stripos($class, 'Supabase') !== false) {
        echo "$class\n";
    }
}
echo "</pre>";
?>