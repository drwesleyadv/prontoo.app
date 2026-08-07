<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Modules;

final class RuntimeModuleComposition
{
    private static ?RuntimeModuleLoader $loader = null;

    private function __construct()
    {
    }

    public static function loader(): RuntimeModuleLoader
    {
        return self::$loader ??= new RuntimeModuleLoader(dirname(__DIR__, 2));
    }
}
