<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Routing;

final class PageDispatcher
{
    private function __construct()
    {
    }

    public static function nativeRoutes(): array
    {
        return RouteRegistry::names();
    }

    public static function hasNativeHandler(string $route): bool
    {
        return RouteRegistry::definition($route) !== null;
    }

    public static function dispatch(string $route): bool
    {
        $definition = RouteRegistry::definition($route);
        if (!$definition instanceof RouteDefinition) {
            return false;
        }
        $class = $definition->handlerClass;
        $method = $definition->handlerMethod;
        $class::$method();
        return true;
    }
}
