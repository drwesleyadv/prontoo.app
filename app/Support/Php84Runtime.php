<?php
declare(strict_types=1);

if (!defined("PRONTOO_REQUIRED_PHP_MAJOR")) {
    define("PRONTOO_REQUIRED_PHP_MAJOR", 8);
    define("PRONTOO_REQUIRED_PHP_MINOR", 4);
    define("PRONTOO_REQUIRED_PHP_FAMILY", "8.4");
}

if (!function_exists("prontoo_php84_runtime_ok")) {
    function prontoo_php84_runtime_ok(): bool
    {
        return PHP_MAJOR_VERSION === PRONTOO_REQUIRED_PHP_MAJOR &&
            PHP_MINOR_VERSION === PRONTOO_REQUIRED_PHP_MINOR;
    }
}

if (!function_exists("prontoo_php84_runtime_message")) {
    function prontoo_php84_runtime_message(): string
    {
        return "Prontoo exige exclusivamente PHP " .
            PRONTOO_REQUIRED_PHP_FAMILY .
            ". Runtime atual: " .
            PHP_VERSION .
            ".";
    }
}

if (!prontoo_php84_runtime_ok()) {
    $prontooPhpRuntimeMessage = prontoo_php84_runtime_message();
    error_log("[Prontoo PHP runtime] " . $prontooPhpRuntimeMessage);
    if (PHP_SAPI === "cli") {
        fwrite(STDERR, $prontooPhpRuntimeMessage . PHP_EOL);
    } else {
        if (!headers_sent()) {
            http_response_code(503);
            header("Content-Type: text/plain; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Retry-After: 300");
        }
        echo $prontooPhpRuntimeMessage;
    }
    unset($prontooPhpRuntimeMessage);
    exit(1);
}
