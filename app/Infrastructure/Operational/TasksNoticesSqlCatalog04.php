<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class TasksNoticesSqlCatalog04
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.tasks_notices.04.page_tasks.01' => (
                "SELECT t.id,t.clinic_id,t.title,t.status,t.assigned_to,t.target_scope,t.target_role,t.target_user_id,t.started_at,td.patient_link_id,td.appointment_id,td.source_event,td.source_entity,td.source_entity_id FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id WHERE t.id=? AND t.clinic_id=?"
            ),
            'operational.tasks_notices.04.page_tasks.02' => (
                "UPDATE pi_tasks SET started_by=?, started_at=NOW(), status='em_andamento', updated_at=NOW() WHERE id=? AND clinic_id=? AND assigned_to=? AND status IN ('aberta','aguardando') AND started_at IS NULL"
            ),
            'operational.tasks_notices.04.page_tasks.03' => (
                "UPDATE pi_tasks SET assigned_to=?, started_by=?, started_at=NOW(), status='em_andamento', updated_at=NOW() WHERE id=? AND clinic_id=? AND assigned_to IS NULL AND status IN ('aberta','aguardando') AND started_at IS NULL"
            ),
            'operational.tasks_notices.04.page_tasks.04' => (
                "SELECT id,title,target_scope,assigned_to FROM pi_tasks WHERE id=? AND clinic_id=?"
            ),
            'operational.tasks_notices.04.page_tasks.05' => (
                "UPDATE pi_tasks SET assigned_to=NULL, started_by=NULL, started_at=NULL, status='aberta', updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.tasks_notices.04.page_tasks.06' => (
                "SELECT t.id,t.clinic_id,t.title,t.assigned_to,td.patient_link_id,td.appointment_id,td.source_event,td.source_entity,td.source_entity_id,t.status FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id WHERE t.id=? AND t.clinic_id=?"
            ),
            'operational.tasks_notices.04.page_tasks.07' => (
                "UPDATE pi_tasks SET status='concluida', completed_by=?, completed_at=NOW(), updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.tasks_notices.04.page_tasks.08' => (
                "SELECT tc.id,tc.task_id,tc.user_id FROM pi_task_comments tc INNER JOIN pi_tasks t ON t.id=tc.task_id AND t.clinic_id=tc.clinic_id WHERE tc.id=? AND tc.clinic_id=? AND tc.deleted_at IS NULL"
            ),
            'operational.tasks_notices.04.page_tasks.09' => (
                "UPDATE pi_task_comments SET body=?, edited_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL"
            ),
            'operational.tasks_notices.04.page_tasks.10' => (
                "SELECT tc.id,tc.task_id,tc.user_id FROM pi_task_comments tc INNER JOIN pi_tasks t ON t.id=tc.task_id AND t.clinic_id=tc.clinic_id WHERE tc.id=? AND tc.clinic_id=? AND tc.deleted_at IS NULL"
            ),
            'operational.tasks_notices.04.page_tasks.11' => (
                "UPDATE pi_task_comments SET deleted_at=NOW(), deleted_by=? WHERE id=? AND clinic_id=? AND deleted_at IS NULL"
            ),
            'operational.tasks_notices.04.page_tasks.12' => (
                "SELECT id FROM pi_tasks WHERE id=? AND clinic_id=?"
            ),
            'operational.tasks_notices.04.page_tasks.13' => (
                "INSERT INTO pi_task_comments (clinic_id,task_id,user_id,body,created_at) VALUES (?,?,?,?,NOW())"
            ),
            'operational.tasks_notices.04.page_tasks.14' => (
                "INSERT INTO pi_tasks (clinic_id,title,target_scope,target_role,target_user_id,assigned_to,due_at,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.tasks_notices.04.page_tasks.15' => (
                "INSERT INTO pi_task_details (task_id,clinic_id,description) VALUES (?,?,?)"
            ),
            'operational.tasks_notices.04.page_tasks.16' => (
                "SELECT t.id,t.title,td.description,t.target_scope,t.target_role,t.target_user_id,t.assigned_to,t.started_at,t.started_by,t.status,t.due_at,t.completed_at,t.created_at,td.patient_link_id,td.appointment_id,td.source_event,td.source_entity,td.source_entity_id,u.name AS assigned_name,su.name AS started_name,p.full_name AS patient_name
                 FROM pi_tasks t
                 LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id
                 LEFT JOIN pi_users u ON u.id=t.assigned_to AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=t.clinic_id AND ur.active=1)
                 LEFT JOIN pi_users su ON su.id=t.started_by AND EXISTS (SELECT 1 FROM pi_user_roles ur2 WHERE ur2.user_id=su.id AND ur2.clinic_id=t.clinic_id AND ur2.active=1)
                 LEFT JOIN pi_patients pl ON pl.id=td.patient_link_id AND pl.clinic_id=t.clinic_id
                 LEFT JOIN pi_persons p ON p.id=pl.person_id
                 WHERE " . TaskQuerySql::taskList($view, (bool) $search) . "
                 ORDER BY
                 CASE
                 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.assigned_to=? THEN 0
                 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.assigned_to IS NULL AND COALESCE(t.target_scope,'clinic')='role' THEN 1
                 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.assigned_to IS NULL AND COALESCE(t.target_scope,'clinic')='clinic' THEN 2
                 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.assigned_to IS NOT NULL THEN 3
                 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.due_at IS NOT NULL AND t.due_at<NOW() THEN 4
                 WHEN t.status IN ('aberta','em_andamento','aguardando') AND t.due_at>=? AND t.due_at<? THEN 5
                 WHEN t.status NOT IN ('aberta','em_andamento','aguardando') THEN 6
                 ELSE 7
                 END,
                 t.due_at IS NULL ASC,
                 t.due_at ASC,
                 t.id DESC
                 LIMIT 160"
            ),
            'operational.tasks_notices.04.page_tasks.17' => (
                "SELECT tc.id,tc.task_id,tc.user_id,tc.body,tc.created_at,tc.updated_at,u.name AS user_name FROM pi_task_comments tc LEFT JOIN pi_users u ON u.id=tc.user_id WHERE tc.clinic_id=? AND tc.deleted_at IS NULL AND tc.task_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ") ORDER BY tc.created_at ASC"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
