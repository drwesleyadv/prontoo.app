<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class MaestroSqlCatalog05
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.maestro.05.maestro_supervised_run_rule.01' => (
                "UPDATE pi_maestro_rules SET last_run_at=NOW(),next_run_at=DATE_ADD(NOW(),INTERVAL min_interval_minutes MINUTE),run_count=run_count+1,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.maestro.05.maestro_supervised_run_rule.02' => (
                "UPDATE pi_maestro_executions SET status='created',action_entity=?,action_entity_id=?,message='Ação criada pelo Maestro',executed_at=NOW() WHERE clinic_id=? AND rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=?"
            ),
            'operational.maestro.05.maestro_supervised_run_rule.03' => (
                "UPDATE pi_maestro_rules SET last_run_at=NOW(),next_run_at=DATE_ADD(NOW(),INTERVAL min_interval_minutes MINUTE),run_count=run_count+1,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.maestro.05.maestro_supervised_run_rule.04' => (
                "UPDATE pi_maestro_rules SET next_run_at=DATE_ADD(NOW(),INTERVAL " .
                                            (int) PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES .
                                            " MINUTE),updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.maestro.05.maestro_supervised_record_job_run.01' => (
                "INSERT INTO pi_maestro_job_runs (started_at,finished_at,duration_ms,rules_seen,rules_run,actions_created,deferred_count,errors_count,success,load_score,note) VALUES (FROM_UNIXTIME(?),NOW(),?,?,?,?,?,?,?,?,?)"
            ),
            'operational.maestro.05.maestro_supervised_cron_run.01' => (
                "SELECT * FROM pi_maestro_rules WHERE active=1 AND (next_run_at IS NULL OR next_run_at<=NOW()) ORDER BY COALESCE(next_run_at,created_at) ASC,priority DESC,id ASC LIMIT 400"
            ),
            'operational.maestro.05.maestro_supervised_cron_run.02' => (
                "SELECT * FROM pi_maestro_job_stats WHERE routine_key IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ")"
            ),
            'operational.maestro.05.maestro_cron_run.01' => (
                "SELECT * FROM pi_maestro_rules WHERE active=1 AND (next_run_at IS NULL OR next_run_at<=NOW()) ORDER BY priority DESC, COALESCE(next_run_at,created_at) ASC LIMIT 80"
            ),
            'operational.maestro.05.maestro_cron_run.02' => (
                "SELECT * FROM pi_maestro_job_stats WHERE routine_key IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ")"
            ),
            'operational.maestro.05.maestro_cron_run.03' => (
                "UPDATE pi_maestro_rules SET next_run_at=DATE_ADD(NOW(), INTERVAL " .
                                                (int) PRONTOO_MAESTRO_CRON_INTERVAL_MINUTES .
                                                " MINUTE) WHERE id=? AND clinic_id=?"
            ),
            'operational.maestro.05.maestro_cron_run.04' => (
                "INSERT INTO pi_maestro_job_runs (started_at,finished_at,duration_ms,rules_seen,rules_run,actions_created,deferred_count,errors_count,success,load_score,note) VALUES (FROM_UNIXTIME(?),NOW(),?,?,?,?,?,?,?,?,?)"
            ),
            'operational.maestro.05.maestro_record_cron_failure.01' => (
                "INSERT INTO pi_maestro_job_runs (started_at,finished_at,duration_ms,rules_seen,rules_run,actions_created,deferred_count,errors_count,success,load_score,note) VALUES (FROM_UNIXTIME(?),NOW(),?,0,0,0,0,1,0,0,?)"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
