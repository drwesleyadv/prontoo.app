<?php
declare(strict_types=1);

const PRO_ROOT = __DIR__;

date_default_timezone_set('America/Cuiaba');
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_time_limit(PHP_SAPI === 'cli' ? 0 : 20);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Pro\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $relative = substr($class, strlen($prefix));
    $file = PRO_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) require_once $file;
});

use Pro\Core\Config;
use Pro\Core\Log;
use Pro\Core\Storage;

Config::boot(PRO_ROOT);
Storage::ensureBaseTree();
ini_set('error_log', Config::path('logs/error.log'));

set_exception_handler(static function (Throwable $error): void {
    $message = $error::class . ': ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine();
    Log::error($message);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['ok' => false, 'error' => 'internal_error'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
});

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    Log::error('Fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
});
