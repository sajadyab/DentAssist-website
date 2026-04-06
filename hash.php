<?php
declare(strict_types=1);

function hashPassword(string $plain): string
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

// Backwards compatible: if opened directly in browser, show example hash.
if (php_sapi_name() !== 'cli' && basename(__FILE__) === basename((string)($_SERVER['SCRIPT_FILENAME'] ?? ''))) {
    echo hashPassword('doctor');
}
?>