<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

function api_wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    if (stripos($accept, 'application/json') !== false) {
        return true;
    }
    $xhr = (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    if (strcasecmp($xhr, 'XMLHttpRequest') === 0) {
        return true;
    }

    return true; // API endpoints always default to JSON
}

function api_respond(array $payload, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload);
    exit;
}

function api_ok(array $payload = [], string $message = ''): void
{
    if ($message !== '') {
        $payload['message'] = $message;
    }
    $payload['success'] = true;
    api_respond($payload, 200);
}

function api_error(string $message, int $statusCode = 400, array $payload = []): void
{
    $payload['success'] = false;
    $payload['message'] = $message;
    api_respond($payload, $statusCode);
}

function api_require_method(string $method): void
{
    $actual = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
    if (strcasecmp($actual, $method) !== 0) {
        api_error('Method not allowed.', 405);
    }
}

function api_require_login(): void
{
    if (!Auth::isLoggedIn()) {
        api_error('Unauthorized.', 401);
    }
}

function api_require_admin(): void
{
    api_require_login();
    if (!Auth::isAdmin()) {
        api_error('Forbidden.', 403);
    }
}

