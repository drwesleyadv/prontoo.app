<?php
declare(strict_types=1);
namespace Prontoo\Core\Architecture;

final class OperationGateway
{
    private static array $handlers = [];
    private function __construct() {}
    public static function register(array $handlers): void
    {
        self::$handlers = $handlers;
    }
    public static function override(string $name, callable $handler): void
    {
        self::$handlers[$name] = $handler;
    }
    public static function has(string $name): bool
    {
        return isset(self::$handlers[$name]) && is_callable(self::$handlers[$name]);
    }
    public static function handler(string $name): callable
    {
        $handler = self::$handlers[$name] ?? null;
        if (!is_callable($handler)) {
            throw new \RuntimeException('Operação nativa não registrada: ' . $name);
        }
        return $handler;
    }
    public static function invoke(string $name, mixed ...$arguments): mixed
    {
        return (self::handler($name))(...$arguments);
    }
}
