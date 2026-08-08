<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Routing;

use Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations01;
use Prontoo\Runtime\Dashboards\DashboardsRuntimeOperations03;
use Prontoo\Runtime\PublicWeb\PublicWebRuntimeOperations01;

final class PageDispatcher
{
    private const NATIVE_ROUTES = [
        'home',
        'painel',
        'mobile_web_access',
    ];

    private function __construct()
    {
    }

    public static function nativeRoutes(): array
    {
        return self::NATIVE_ROUTES;
    }

    public static function hasNativeHandler(string $route): bool
    {
        return in_array($route, self::NATIVE_ROUTES, true);
    }

    public static function dispatch(string $route): bool
    {
        switch ($route) {
            case 'home':
                DashboardsRuntimeOperations01::page_home();
                return true;
            case 'painel':
                DashboardsRuntimeOperations03::page_painel();
                return true;
            case 'mobile_web_access':
                PublicWebRuntimeOperations01::page_mobile_web_access();
                return true;
            default:
                return false;
        }
    }
}
