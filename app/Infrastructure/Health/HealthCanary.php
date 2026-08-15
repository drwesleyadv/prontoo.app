<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Health;

use Throwable;

final class HealthCanary
{
    private function __construct()
    {
    }

    public static function run(): array
    {
        $started = hrtime(true);
        $checks = [
            'database' => false,
            'storage' => false,
            'mutation_invariant' => false,
            'scope_logic' => false,
            'scope_context' => false,
        ];
        try {
            $checks['database'] = (int) \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo()
                ->query('SELECT 1')
                ->fetchColumn() === 1;
        } catch (Throwable $error) {
            $checks['database'] = false;
        }
        try {
            $storage = \Prontoo\Infrastructure\AdminPages\AdminPagesInfrastructureOperations01::platform_storage_status();
            $checks['storage'] = !empty($storage['ok']);
        } catch (Throwable $error) {
            $checks['storage'] = false;
        }
        try {
            $mutation = \Prontoo\Core\Invariant\InvariantKernel::logicSelfTest();
            $checks['mutation_invariant'] = !empty($mutation['ok']);
        } catch (Throwable $error) {
            $checks['mutation_invariant'] = false;
        }
        try {
            $scope = class_exists(\Prontoo\Core\Database\SqlScopeGuard::class)
                ? \Prontoo\Core\Database\SqlScopeGuard::logicSelfTest()
                : ['ok' => false];
            $checks['scope_logic'] = !empty($scope['ok']);
        } catch (Throwable $error) {
            $checks['scope_logic'] = false;
        }
        try {
            $context = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations02::scope_guard_context_selftest();
            $checks['scope_context'] = !empty($context['ok']);
        } catch (Throwable $error) {
            $checks['scope_context'] = false;
        }
        return [
            'schema' => 'prontoo.health-canary.v1',
            'ok' => !in_array(false, $checks, true),
            'checked_at' => gmdate('c'),
            'duration_ms' => round((hrtime(true) - $started) / 1000000, 3, \RoundingMode::HalfAwayFromZero),
            'checks' => $checks,
        ];
    }
}
