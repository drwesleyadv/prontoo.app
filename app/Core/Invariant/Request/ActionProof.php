<?php
declare(strict_types=1);

namespace Prontoo\Core\Integrity;

use Prontoo\Runtime\LayeredKernel;

final class ActionProof
{
    private function __construct() {

    }

    public static function enforce(
        string $route,
        string $method,
        array $post,
        array $context,
    ): void {

        LayeredKernel::enforceAction($route, $method, $post, $context);
    }

    public static function logicSelfTest(): array
    {

        $root = defined('PRONTOO_ROOT') ? PRONTOO_ROOT : dirname(__DIR__, 4);
        return LayeredKernel::logicSelfTest($root);
    }
}
