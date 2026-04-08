<?php

declare(strict_types=1);

/**
 * Forgot password → token + WhatsApp (local Node send.js).
 * Kept so forgot_password.php can POST here; delegates to reset_link.php logic.
 */
require __DIR__ . '/reset_link.php';
