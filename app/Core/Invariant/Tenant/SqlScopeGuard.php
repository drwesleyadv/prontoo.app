<?php
declare(strict_types=1);
namespace Prontoo\Core\Database;

use Prontoo\Core\Invariant\InvariantKernel;

final class SqlScopeGuard
{
    private function __construct() {}

    public static function guard(string $sql, array $params = []): void
    {
        InvariantKernel::guardMutation($sql, $params);
    }

    public static function logicSelfTest(): array
    {
        $kernel = InvariantKernel::logicSelfTest();
        $scope = (array) ($kernel["mutations"]["scope"] ?? []);
        return $scope + [
            "policy" => $kernel["policy"] ?? InvariantKernel::policyVersion(),
            "kernel" => $kernel,
        ];
    }
}
