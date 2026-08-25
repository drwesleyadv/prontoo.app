<?php
declare(strict_types=1);
namespace Pro\Core;

final class Log
{
    public static function info(string $m): void { self::write('INFO', $m); }
    public static function warn(string $m): void { self::write('WARN', $m); }
    public static function error(string $m): void { self::write('ERROR', $m); }
    private static function write(string $level, string $message): void
    { Storage::appendLog('logs/app.log', '['.date('Y-m-d H:i:s')."] [$level] $message\n"); }
}
