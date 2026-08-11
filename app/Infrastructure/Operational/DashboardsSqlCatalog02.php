<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class DashboardsSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.dashboards.02.page_triagem_painel.01' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND status NOT IN ('cancelado') ORDER BY start_at ASC LIMIT 160"
            ),
            'operational.dashboards.02.page_triagem_painel.02' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND (assigned_to=? OR (assigned_to IS NULL AND (target_scope='role' AND target_role='assistente' OR target_scope='clinic')))"
            ),
            'operational.dashboards.02.page_triagem_painel.03' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at<=DATE_ADD(NOW(), INTERVAL 30 MINUTE) AND (assigned_to=? OR (assigned_to IS NULL AND (target_scope='role' AND target_role='assistente' OR target_scope='clinic')))"
            ),
            'operational.dashboards.02.manager_metric_val.01' => (
                OperationalDynamicSqlCatalog::statement($query, $bindings)
            ),
            'operational.dashboards.02.manager_metric_row.01' => (
                OperationalDynamicSqlCatalog::statement($query, $bindings)
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
