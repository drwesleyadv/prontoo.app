<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AppointmentsSqlCatalog05
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.appointments.05.page_appointments.01' => (
                "SELECT id,note_date,created_by FROM pi_agenda_notes WHERE id=? AND clinic_id=? AND deleted_at IS NULL LIMIT 1"
            ),
            'operational.appointments.05.page_appointments.02' => (
                "UPDATE pi_agenda_notes SET deleted_at=NOW(), deleted_by=?, updated_by=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL AND created_by=?"
            ),
            'operational.appointments.05.page_appointments.03' => (
                "INSERT INTO pi_agenda_notes (clinic_id,note_date,content,target_scope,target_role,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,NOW(),NOW())"
            ),
            'operational.appointments.05.page_appointments.04' => (
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.05' => (
                "UPDATE pi_appointments SET status='confirmado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            'operational.appointments.05.page_appointments.06' => (
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.07' => (
                "UPDATE pi_appointments SET status='chegou', arrived_at=COALESCE(arrived_at,NOW()), updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            'operational.appointments.05.page_appointments.08' => (
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.09' => (
                "UPDATE pi_appointments SET status='nao_compareceu', cancel_reason=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            'operational.appointments.05.page_appointments.10' => (
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at,payment_status,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.11' => (
                "UPDATE pi_appointments SET status='em_preparo', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='chegou' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            'operational.appointments.05.page_appointments.12' => (
                "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='em_andamento', t.started_by=COALESCE(t.started_by,?), t.started_at=COALESCE(t.started_at,NOW()), t.assigned_to=COALESCE(t.assigned_to,?), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='paciente_chegou' AND t.status IN ('aberta','aguardando')"
            ),
            'operational.appointments.05.page_appointments.13' => (
                "UPDATE pi_appointments SET status='pronto_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_preparo' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            'operational.appointments.05.page_appointments.14' => (
                "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='paciente_chegou' AND t.status IN ('aberta','em_andamento','aguardando')"
            ),
            'operational.appointments.05.page_appointments.15' => (
                "SELECT doctor_user_id FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.16' => (
                "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), status='em_atendimento', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='pronto_atendimento' AND consultation_started_at IS NULL AND consultation_finished_at IS NULL AND (doctor_user_id IS NULL OR doctor_user_id=?)"
            ),
            'operational.appointments.05.page_appointments.17' => (
                "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='em_andamento', t.started_by=COALESCE(t.started_by,?), t.started_at=COALESCE(t.started_at,NOW()), t.assigned_to=COALESCE(t.assigned_to,?), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','aguardando')"
            ),
            'operational.appointments.05.page_appointments.18' => (
                "UPDATE pi_appointments SET consultation_started_at=COALESCE(consultation_started_at,NOW()), consultation_finished_at=COALESCE(consultation_finished_at,NOW()), status='atendimento_concluido', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='em_atendimento' AND consultation_finished_at IS NULL"
            ),
            'operational.appointments.05.page_appointments.19' => (
                "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='preparo_concluido' AND t.status IN ('aberta','em_andamento','aguardando')"
            ),
            'operational.appointments.05.page_appointments.20' => (
                "UPDATE pi_appointments SET status='finalizado', updated_at=NOW() WHERE id=? AND clinic_id=? AND status='atendimento_concluido' AND consultation_finished_at IS NOT NULL"
            ),
            'operational.appointments.05.page_appointments.21' => (
                "UPDATE pi_tasks t JOIN pi_task_details d ON d.task_id=t.id AND d.clinic_id=t.clinic_id SET t.status='concluida', t.completed_by=COALESCE(t.completed_by,?), t.completed_at=COALESCE(t.completed_at,NOW()), t.updated_at=NOW() WHERE t.clinic_id=? AND d.appointment_id=? AND d.source_event='atendimento_finalizado' AND t.status IN ('aberta','em_andamento','aguardando')"
            ),
            'operational.appointments.05.page_appointments.22' => (
                "SELECT id FROM pi_clinics WHERE id=? FOR UPDATE"
            ),
            'operational.appointments.05.page_appointments.23' => (
                "INSERT INTO pi_blocks (clinic_id,doctor_user_id,start_at,end_at,reason,created_by,created_at) VALUES (?,?,?,?,?,?,NOW())"
            ),
            'operational.appointments.05.page_appointments.24' => (
                "SELECT id FROM pi_blocks WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.25' => (
                "SELECT id FROM pi_clinics WHERE id=? FOR UPDATE"
            ),
            'operational.appointments.05.page_appointments.26' => (
                "UPDATE pi_blocks SET doctor_user_id=?, start_at=?, end_at=?, reason=?, updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.27' => (
                "SELECT id FROM pi_blocks WHERE id=? AND clinic_id=? AND deleted_at IS NULL"
            ),
            'operational.appointments.05.page_appointments.28' => (
                "UPDATE pi_blocks SET deleted_at=NOW(),deleted_by=?,cancel_reason=?,updated_at=NOW() WHERE id=? AND clinic_id=? AND deleted_at IS NULL"
            ),
            'operational.appointments.05.page_appointments.29' => (
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_amount_cents FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.30' => (
                "SELECT id FROM pi_clinics WHERE id=? FOR UPDATE"
            ),
            'operational.appointments.05.page_appointments.31' => (
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=? FOR UPDATE"
            ),
            'operational.appointments.05.page_appointments.32' => (
                "UPDATE pi_appointments SET patient_link_id=?, doctor_user_id=?, start_at=?, end_at=?, procedure_id=?, reason=?, notes=?, change_reason=?, payment_amount_cents=?, payment_method=?, payment_status=?, payment_confirmed_at=IF(?,COALESCE(payment_confirmed_at,NOW()),NULL), updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.33' => (
                "SELECT id,patient_link_id,doctor_user_id,status,start_at,arrived_at,consultation_started_at,consultation_finished_at FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.appointments.05.page_appointments.34' => (
                "UPDATE pi_appointments SET status='cancelado', cancel_reason=?, updated_at=NOW() WHERE id=? AND clinic_id=? AND status IN ('agendado','confirmado') AND arrived_at IS NULL AND consultation_started_at IS NULL AND consultation_finished_at IS NULL"
            ),
            'operational.appointments.05.page_appointments.35' => (
                "SELECT id FROM pi_clinics WHERE id=? FOR UPDATE"
            ),
            'operational.appointments.05.page_appointments.36' => (
                "SELECT id,title,duration_minutes FROM pi_procedures WHERE id=? AND clinic_id=? AND active=1 FOR UPDATE"
            ),
            'operational.appointments.05.page_appointments.37' => (
                "INSERT INTO pi_appointments (clinic_id,patient_link_id,doctor_user_id,start_at,end_at,status,procedure_id,reason,notes,payment_amount_cents,payment_method,payment_status,payment_confirmed_at,created_by,created_at) VALUES (?,?,?,?,?,'agendado',?,?,?,?,?,?,IF(?,NOW(),NULL),?,NOW())"
            ),
            'operational.appointments.05.page_appointments.38' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,notes,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_status,payment_method,payment_amount_cents,payment_confirmed_at,revenue_id FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?" .
                (!empty($doctorScoped) ? " AND doctor_user_id=?" : "") .
                " ORDER BY start_at ASC LIMIT 180"
            ),
            'operational.appointments.05.page_appointments.39' => (
                "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>?" .
                (!empty($doctorScoped) ? " AND (doctor_user_id IS NULL OR doctor_user_id=?)" : "") .
                " ORDER BY start_at ASC LIMIT 100"
            ),
            'operational.appointments.05.page_appointments.40' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,status,reason,notes,arrived_at,consultation_started_at,consultation_finished_at,procedure_id,payment_status,payment_method,payment_amount_cents,payment_confirmed_at,revenue_id FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?" .
                (!empty($doctorScoped) ? " AND doctor_user_id=?" : "") .
                " ORDER BY start_at ASC LIMIT 600"
            ),
            'operational.appointments.05.page_appointments.41' => (
                "SELECT id,doctor_user_id,start_at,end_at,reason,created_by FROM pi_blocks WHERE clinic_id=? AND deleted_at IS NULL AND start_at<? AND end_at>?" .
                (!empty($doctorScoped) ? " AND (doctor_user_id IS NULL OR doctor_user_id=?)" : "") .
                " ORDER BY start_at ASC LIMIT 300"
            ),
            'operational.appointments.05.page_appointments.42' => (
                "SELECT id,doctor_user_id,start_at,end_at,status FROM pi_appointments WHERE clinic_id=? AND status NOT IN ('cancelado') AND start_at>=? AND start_at<?" .
                (!empty($doctorScoped) ? " AND doctor_user_id=?" : "") .
                " ORDER BY start_at ASC LIMIT 1500"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
