<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class TasksNoticesSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.tasks_notices.01.notice_recipient_people.01' => (
                "SELECT user_id,read_at,ack_at FROM pi_notice_reads WHERE notice_id=? AND user_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ")"
            ),
            'operational.tasks_notices.01.task_event.01' => (
                "INSERT INTO pi_task_events (clinic_id,task_id,event_key,actor_user_id,from_status,to_status,note,created_at) VALUES (?,?,?,?,?,?,?,NOW())"
            ),
            'operational.tasks_notices.01.notify_task_personal_assignment.01' => (
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.tasks_notices.01.workflow_care_notice.01' => (
                "SELECT id FROM pi_notices WHERE clinic_id=? AND title=? AND body=? AND target_scope=? AND (target_role <=> ?) AND (target_user_id <=> ?) AND created_at>=DATE_SUB(NOW(), INTERVAL 6 HOUR) LIMIT 1"
            ),
            'operational.tasks_notices.01.workflow_care_notice.02' => (
                "INSERT INTO pi_notices (clinic_id,title,body,requires_ack,target_scope,target_role,target_user_id,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.tasks_notices.01.workflow_appointment_payment_pending.01' => (
                "SELECT payment_status,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.tasks_notices.01.workflow_on_task_started.01' => (
                "UPDATE pi_appointments SET status='em_preparo', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='chegou' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            'operational.tasks_notices.01.workflow_on_task_started.02' => (
                "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), status='em_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='pronto_atendimento' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
