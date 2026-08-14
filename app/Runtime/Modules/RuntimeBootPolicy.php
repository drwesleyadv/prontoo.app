<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Modules;

final class RuntimeBootPolicy
{
    private const SPECIALIZATION_POLICY = 'pi-v3-php-layered-runtime-route-loaded';

    private const PUBLIC_LIGHT_ROUTES = [
        'login',
        'login_autotest',
        'mfa',
        'mobile_web_access',
        'signup',
        'logout',
    ];

    private function __construct()
    {
    }

    public static function specializationPolicy(): string
    {
        return self::SPECIALIZATION_POLICY;
    }

    public static function requestedRoute(array $query): string
    {
        $route = $query['r'] ?? 'login';
        return preg_replace('/[^a-z0-9_\-]/i', '', (string) $route) ?: 'login';
    }

    public static function publicLightRoutes(): array
    {
        return self::PUBLIC_LIGHT_ROUTES;
    }

    public static function useLightBoot(string|false $disableLightBoot): bool
    {
        return (string) $disableLightBoot !== '1';
    }
}
