<?php
require_once __DIR__ . '/vendor/autoload.php';

$file = __DIR__ . '/vendor/supabase-php/supabase-client/src/Supabase/Supabase.php';
$content = file_get_contents($file);

// Extract namespace and class
preg_match('/namespace\s+([^;]+);/', $content, $ns);
preg_match('/class\s+(\w+)/', $content, $class);

echo "Namespace: " . ($ns[1] ?? 'NONE') . "<br>";
echo "Class: " . ($class[1] ?? 'NONE') . "<br>";
echo "Full class name should be: " . ($ns[1] ?? '') . '\\' . ($class[1] ?? '') . "<br>";

// Also check Composer's autoload mapping
$classMap = require __DIR__ . '/vendor/composer/autoload_classmap.php';
echo "<br>Composer classmap entries containing 'Supabase':<br>";
foreach ($classMap as $class => $path) {
    if (stripos($class, 'Supabase') !== false) {
        echo "$class => $path<br>";
    }
}
?>