<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class DashboardsSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.dashboards.01.page_medico_painel.01' => (
                "SELECT id,patient_link_id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND doctor_user_id=? AND start_at>=? AND start_at<? ORDER BY start_at ASC LIMIT 120"
            ),
            'operational.dashboards.01.page_medico_painel.02' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status='aberta' AND due_at IS NOT NULL AND due_at<NOW() AND (assigned_to IS NULL OR assigned_to=?)"
            ),
            'operational.dashboards.01.page_medico_painel.03' => (
                "SELECT id,patient_link_id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND doctor_user_id=? AND start_at>=NOW() ORDER BY start_at ASC LIMIT 3"
            ),
            'operational.dashboards.01.page_recepcao_painel.01' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND status NOT IN ('cancelado') ORDER BY start_at ASC LIMIT 180"
            ),
            'operational.dashboards.01.page_recepcao_painel.02' => (
                "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>? ORDER BY start_at ASC LIMIT 80"
            ),
            'operational.dashboards.01.page_recepcao_painel.03' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND (assigned_to=? OR (assigned_to IS NULL AND (COALESCE(target_scope,'clinic')='clinic' OR (COALESCE(target_scope,'clinic')='role' AND target_role=?) OR (COALESCE(target_scope,'clinic')='user' AND target_user_id=?))))"
            ),
            'operational.dashboards.01.page_recepcao_painel.04' => (
                "SELECT COUNT(*) FROM pi_tasks WHERE clinic_id=? AND status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at<NOW() AND (assigned_to=? OR (assigned_to IS NULL AND (COALESCE(target_scope,'clinic')='clinic' OR (COALESCE(target_scope,'clinic')='role' AND target_role=?) OR (COALESCE(target_scope,'clinic')='user' AND target_user_id=?))))"
            ),
            'operational.dashboards.01.page_recepcao_painel.05' => (
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                                    LeadQuerySql::active("stage")
            ),
            'operational.dashboards.01.page_recepcao_painel.06' => (
                "SELECT COUNT(*) FROM pi_patients WHERE clinic_id=? AND active=1 AND registration_needs_update=1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
