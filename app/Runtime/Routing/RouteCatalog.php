<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Routing;

final class RouteCatalog
{
    private function __construct()
    {
    }

    public static function all(): array
    {
        return RouteRegistry::names();
    }

    public static function public(): array
    {
        return RouteRegistry::publicNames();
    }

    public static function json(): array
    {
        return RouteRegistry::jsonNames();
    }

    public static function wantsJson(string $route, string $accept): bool
    {
        return (RouteRegistry::definition($route)?->json ?? false) ||
            str_contains(strtolower($accept), 'application/json');
    }

    public static function isPublicLight(string $route, string $method): bool
    {
        return RouteRegistry::definition($route)?->isPublicLight($method) ?? false;
    }
}
