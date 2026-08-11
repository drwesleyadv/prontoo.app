<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class MaestroSqlCatalog04
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.maestro.04.maestro_create_action.01' => (
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.maestro.04.maestro_stats_update.01' => (
                "SELECT * FROM pi_maestro_job_stats WHERE routine_key=? FOR UPDATE"
            ),
            'operational.maestro.04.maestro_stats_update.02' => (
                "UPDATE pi_maestro_job_stats SET skip_count=skip_count+1,last_score=?,updated_at=NOW() WHERE routine_key=?"
            ),
            'operational.maestro.04.maestro_stats_update.03' => (
                "INSERT INTO pi_maestro_job_stats (routine_key,ewma_duration_ms,ewma_yield,run_count,skip_count,last_score,last_run_at,updated_at) VALUES (?,0,0,0,1,?,NULL,NOW())"
            ),
            'operational.maestro.04.maestro_stats_update.04' => (
                "UPDATE pi_maestro_job_stats SET ewma_duration_ms=?,ewma_yield=?,run_count=run_count+1,last_score=?,last_run_at=NOW(),updated_at=NOW() WHERE routine_key=?"
            ),
            'operational.maestro.04.maestro_stats_update.05' => (
                "INSERT INTO pi_maestro_job_stats (routine_key,ewma_duration_ms,ewma_yield,run_count,skip_count,last_score,last_run_at,updated_at) VALUES (?,?,?,1,0,?,NOW(),NOW())"
            ),
            'operational.maestro.04.maestro_run_rule_scoped.01' => (
                "INSERT IGNORE INTO pi_maestro_executions (clinic_id,rule_id,source_entity,source_entity_id,action_key,status,message,executed_at) VALUES (?,?,?,?,?,'running','Em processamento',NOW())"
            ),
            'operational.maestro.04.maestro_run_rule_scoped.02' => (
                "UPDATE pi_maestro_executions SET message='Retomada determinística após interrupção', executed_at=NOW() WHERE rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND clinic_id=? AND status='running' AND executed_at<=DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            ),
            'operational.maestro.04.maestro_run_rule_scoped.03' => (
                "UPDATE pi_maestro_executions SET status='created', action_entity=?, action_entity_id=?, message='Ação criada', executed_at=NOW() WHERE rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND clinic_id=?"
            ),
            'operational.maestro.04.maestro_run_rule_scoped.04' => (
                "UPDATE pi_maestro_executions SET status='error', message=?, executed_at=NOW() WHERE rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND clinic_id=?"
            ),
            'operational.maestro.04.maestro_run_rule_scoped.05' => (
                "UPDATE pi_maestro_rules SET last_run_at=NOW(), next_run_at=DATE_ADD(NOW(), INTERVAL min_interval_minutes MINUTE), run_count=run_count+1, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.maestro.04.maestro_supervised_execution_rows.01' => (
                "SELECT source_entity,source_entity_id,status,message,executed_at FROM pi_maestro_executions WHERE clinic_id=? AND rule_id=? AND action_key=? AND source_entity_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ")"
            ),
            'operational.maestro.04.maestro_supervised_claim_execution.01' => (
                "INSERT IGNORE INTO pi_maestro_executions (clinic_id,rule_id,source_entity,source_entity_id,action_key,status,message,executed_at) VALUES (?,?,?,?,?,'running','attempt:0;Em processamento',NOW())"
            ),
            'operational.maestro.04.maestro_supervised_claim_execution.02' => (
                "UPDATE pi_maestro_executions SET message=?,executed_at=NOW() WHERE clinic_id=? AND rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND status='running'"
            ),
            'operational.maestro.04.maestro_supervised_claim_execution.03' => (
                "UPDATE pi_maestro_executions SET status='running',message=?,executed_at=NOW() WHERE clinic_id=? AND rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=? AND status='error'"
            ),
            'operational.maestro.04.maestro_supervised_action_failure.01' => (
                "UPDATE pi_maestro_executions SET status='error',message=?,executed_at=NOW() WHERE clinic_id=? AND rule_id=? AND source_entity=? AND source_entity_id=? AND action_key=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
