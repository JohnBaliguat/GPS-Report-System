<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/Scoring.php';
require_once __DIR__ . '/../lib/Auth.php';

header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

// Surface real errors as JSON (shared hosting hides them as blank 500s).
error_reporting(E_ALL);
ini_set('display_errors', '0');

set_exception_handler(function (Throwable $e): void {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
        'where'   => basename($e->getFile()) . ':' . $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
});

register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo json_encode([
            'status'  => 'error',
            'message' => $err['message'],
            'where'   => basename($err['file']) . ':' . $err['line'],
        ], JSON_UNESCAPED_UNICODE);
    }
});

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Every API under this folder requires an authenticated session.
Auth::requireApi();

function q(string $key, $default = null)
{
    return $_GET[$key] ?? $_POST[$key] ?? $default;
}

/** Shared WHERE builder for dashboard / list filters. */
function event_filters(): array
{
    $where = [];
    $args  = [];
    if (($t = q('type')) !== null && $t !== '') {
        $where[] = 'e.event_type = ?';
        $args[]  = $t;
    }
    if (($d = q('driver_id')) !== null && $d !== '') {
        $where[] = 'e.driver_id = ?';
        $args[]  = (int) $d;
    }
    if (($from = q('from')) !== null && $from !== '') {
        $where[] = 'e.event_date >= ?';
        $args[]  = $from;
    }
    if (($to = q('to')) !== null && $to !== '') {
        $where[] = 'e.event_date <= ?';
        $args[]  = $to;
    }
    $sql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
    return [$sql, $args];
}
