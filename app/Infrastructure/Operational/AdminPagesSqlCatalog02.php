<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AdminPagesSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.admin_pages.02.admin_global_ops_finance_html.01' => (
                OperationalDynamicSqlCatalog::statement($query, $bindings)
            ),
            'operational.admin_pages.02.admin_global_ops_finance_html.02' => (
                OperationalDynamicSqlCatalog::statement($query, $bindings)
            ),
            'operational.admin_pages.02.admin_global_sequence_series_20d.01' => (
                OperationalSequenceSql::dailyMutationSeries()
            ),
            'operational.admin_pages.02.admin_maestro_health_pill_html.01' => (
                "SELECT started_at,finished_at,duration_ms,note" . ((bool) $hasSuccess ? ',success,errors_count' : '') . " FROM pi_maestro_job_runs ORDER BY id DESC LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
