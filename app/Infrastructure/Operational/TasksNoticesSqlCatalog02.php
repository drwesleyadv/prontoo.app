<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class TasksNoticesSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.tasks_notices.02.workflow_on_appointment_finished.01' => (
                "SELECT id,patient_link_id,payment_status,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.tasks_notices.02.workflow_on_appointment_finished.02' => (
                "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), consultation_finished_at=COALESCE(consultation_finished_at,NOW()), status='atendimento_concluido', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_atendimento' AND consultation_finished_at IS NULL"
            ),
            'operational.tasks_notices.02.unread_notifications_count.01' => (
                "SELECT COUNT(*) FROM pi_notices n WHERE n.clinic_id=? AND " . NoticeQuerySql::target('n') . " AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND r.user_id=? AND (r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1)"
            ),
            'operational.tasks_notices.02.unread_notifications_count.02' => (
                "SELECT COUNT(*) FROM pi_global_notices WHERE active=1 AND (starts_at IS NULL OR starts_at<=NOW()) AND (expires_at IS NULL OR expires_at>=NOW())"
            ),
            'operational.tasks_notices.02.team_user_ids_for_roles.01' => (
                "SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND active=1 AND role_code IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ") ORDER BY id ASC LIMIT 300"
            ),
            'operational.tasks_notices.02.first_team_user_for_role.01' => (
                "SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND role_code=? AND active=1 ORDER BY id ASC LIMIT 1"
            ),
            'operational.tasks_notices.02.create_workflow_task.01' => (
                "SELECT id,patient_link_id FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.tasks_notices.02.create_workflow_task.02' => (
                "SELECT t.id FROM pi_tasks t INNER JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id WHERE t.clinic_id=? AND td.source_event=? AND td.source_entity=? AND td.source_entity_id=? AND t.status IN ('aberta','em_andamento','aguardando') LIMIT 1"
            ),
            'operational.tasks_notices.02.create_workflow_task.03' => (
                "INSERT INTO pi_tasks (clinic_id,title,target_scope,target_role,target_user_id,assigned_to,status,due_at,created_by,created_at) VALUES (?,?,?,?,?,?, 'aberta', ?, ?, NOW())"
            ),
            'operational.tasks_notices.02.create_workflow_task.04' => (
                "INSERT INTO pi_task_details (task_id,clinic_id,description,patient_link_id,appointment_id,source_event,source_entity,source_entity_id) VALUES (?,?,?,?,?,?,?,?)"
            ),
            'operational.tasks_notices.02.workflow_on_task_completed.01' => (
                "UPDATE pi_appointments SET status='pronto_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('chegou','em_preparo') AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            'operational.tasks_notices.02.workflow_on_task_completed.02' => (
                "SELECT doctor_user_id FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.tasks_notices.02.workflow_on_task_completed.03' => (
                "UPDATE pi_appointments SET status='finalizado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='atendimento_concluido' AND consultation_finished_at IS NOT NULL"
            ),
            'operational.tasks_notices.02.task_nav_counts.01' => (
                "SELECT 
                     COUNT(*) AS all_count,
                     SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') THEN 1 ELSE 0 END) AS open_count,
                     SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at>=? AND due_at<? THEN 1 ELSE 0 END) AS today_count,
                     SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND due_at IS NOT NULL AND due_at<NOW() THEN 1 ELSE 0 END) AS overdue_count,
                     SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND assigned_to IS NULL AND (COALESCE(target_scope,'clinic')='clinic' OR (COALESCE(target_scope,'clinic')='role' AND target_role=?) OR (COALESCE(target_scope,'clinic')='user' AND COALESCE(target_user_id,assigned_to)=?)) THEN 1 ELSE 0 END) AS start_count,
                     SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND assigned_to=? THEN 1 ELSE 0 END) AS mine_count,
                     SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND assigned_to IS NULL AND COALESCE(target_scope,'clinic')='role' AND target_role=? THEN 1 ELSE 0 END) AS role_count,
                     SUM(CASE WHEN status IN ('aberta','em_andamento','aguardando') AND assigned_to IS NULL AND COALESCE(target_scope,'clinic')='clinic' THEN 1 ELSE 0 END) AS clinic_count,
                     SUM(CASE WHEN status='em_andamento' THEN 1 ELSE 0 END) AS progress_count,
                     SUM(CASE WHEN status NOT IN ('aberta','em_andamento','aguardando') THEN 1 ELSE 0 END) AS done_count
                     FROM pi_tasks WHERE clinic_id=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
