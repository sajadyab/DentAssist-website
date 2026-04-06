<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

// Lightweight autoloader for app classes in /includes/classes.
spl_autoload_register(static function (string $class): void {
    $class = ltrim($class, '\\');
    if ($class === '') {
        return;
    }

    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR;
    $file = $baseDir . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $class) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

