<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Autoload;

final class ProntooAutoloader
{
    private static bool $registered = false;

    private function __construct()
    {
    }

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        spl_autoload_register(static function (string $class): void {
            $prefix = 'Prontoo\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            if ($relative === false || $relative === '') {
                return;
            }
            $root = defined('PRONTOO_ROOT') ? PRONTOO_ROOT : dirname(__DIR__, 3);
            $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        });
    }
}

ProntooAutoloader::register();