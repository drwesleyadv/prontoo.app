<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class MaestroSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.maestro.02.page_maestro.01' => (
                "UPDATE pi_maestro_rules SET active=IF(active=1,0,1), next_run_at=NOW(), updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.maestro.02.page_maestro.02' => (
                "DELETE FROM pi_maestro_rules WHERE id=? AND clinic_id=?"
            ),
            'operational.maestro.02.page_maestro.03' => (
                "SELECT * FROM pi_maestro_rules WHERE clinic_id=? ORDER BY active DESC, priority DESC, id DESC LIMIT 300"
            ),
            'operational.maestro.02.page_maestro.04' => (
                "SELECT started_at,finished_at,duration_ms,actions_created,rules_run,deferred_count FROM pi_maestro_job_runs ORDER BY id DESC LIMIT 1"
            ),
            'operational.maestro.02.page_maestro.05' => (
                "SELECT AVG(duration_ms) FROM pi_maestro_job_runs WHERE finished_at>=DATE_SUB(NOW(), INTERVAL 24 HOUR)"
            ),
            'operational.maestro.02.page_maestro.06' => (
                "SELECT COUNT(*) FROM pi_maestro_executions WHERE clinic_id=? AND status='created' AND executed_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            'operational.maestro.02.page_maestro.07' => (
                "SELECT u.id,u.name FROM pi_users u INNER JOIN pi_user_roles ur ON ur.user_id=u.id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1 GROUP BY u.id,u.name ORDER BY u.name LIMIT 200"
            ),
            'operational.maestro.02.page_maestro.08' => (
                "SELECT rule_id, SUM(CASE WHEN status='created' THEN 1 ELSE 0 END) AS created_count, MAX(executed_at) AS last_at FROM pi_maestro_executions WHERE clinic_id=? AND rule_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ") GROUP BY rule_id"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
