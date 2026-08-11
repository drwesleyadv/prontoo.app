<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class MaestroSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.maestro.03.maestro_fetch_candidates.01' => (
                "SELECT a.id,a.patient_link_id,a.doctor_user_id,a.start_at,a.reason,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.start_at>=? AND a.start_at<? AND a.status NOT IN ('cancelado','nao_compareceu','reagendado','atendimento_concluido','finalizado') ORDER BY a.start_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.02' => (
                "SELECT a.id,a.patient_link_id,a.start_at,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) AND a.status='agendado' ORDER BY a.created_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.03' => (
                "SELECT a.id,a.patient_link_id,a.start_at,a.change_reason,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.updated_at>=DATE_SUB(NOW(), INTERVAL ? DAY) AND a.change_reason IS NOT NULL AND a.status<>'cancelado' ORDER BY a.updated_at DESC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.04' => (
                "SELECT a.id,a.patient_link_id,a.start_at,a.cancel_reason,p.full_name AS patient_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE a.clinic_id=? AND COALESCE(a.updated_at,a.created_at)>=DATE_SUB(NOW(), INTERVAL ? DAY) AND (a.status='cancelado' OR a.cancel_reason IS NOT NULL) ORDER BY COALESCE(a.updated_at,a.created_at) DESC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.05' => (
                "SELECT a.id,a.patient_link_id,a.arrived_at,a.start_at,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.arrived_at IS NOT NULL AND a.consultation_started_at IS NULL AND a.consultation_finished_at IS NULL AND a.arrived_at<=DATE_SUB(NOW(), INTERVAL ? MINUTE) AND a.status NOT IN ('cancelado','nao_compareceu','reagendado','atendimento_concluido','finalizado') ORDER BY a.arrived_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.06' => (
                "SELECT a.id,a.patient_link_id,a.consultation_finished_at,p.full_name AS patient_name,u.name AS doctor_name FROM pi_appointments a LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_doc WHERE ur_doc.user_id=u.id AND ur_doc.clinic_id=a.clinic_id AND ur_doc.active=1) WHERE a.clinic_id=? AND a.consultation_finished_at IS NOT NULL AND a.consultation_finished_at<=DATE_SUB(NOW(), INTERVAL ? HOUR) AND NOT EXISTS (SELECT 1 FROM pi_care c WHERE c.clinic_id=a.clinic_id AND c.appointment_id=a.id AND c.deleted_at IS NULL LIMIT 1) ORDER BY a.consultation_finished_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.07' => (
                "SELECT id,start_at,reason FROM pi_appointments WHERE clinic_id=? AND start_at>=? AND start_at<? AND patient_link_id IS NULL AND status<>'cancelado' ORDER BY start_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.08' => (
                "SELECT id,name,created_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NULL AND created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY created_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.09' => (
                "SELECT id,name,next_action_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NOT NULL AND next_action_at>=? AND next_action_at<? ORDER BY next_action_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.10' => (
                "SELECT id,name,next_action_at,interest FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND next_action_at IS NOT NULL AND next_action_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY next_action_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.11' => (
                "SELECT id,name,stage,COALESCE(updated_at,created_at) AS moved_at FROM pi_leads WHERE clinic_id=? AND stage NOT IN ('convertido','arquivado','descartado') AND COALESCE(updated_at,created_at)<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY moved_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.12' => (
                "SELECT id,name,COALESCE(updated_at,created_at) AS moved_at FROM pi_leads WHERE clinic_id=? AND stage='convertido' AND COALESCE(updated_at,created_at)>=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY moved_at DESC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.13' => (
                "SELECT pl.id,p.full_name FROM pi_patients pl INNER JOIN pi_persons p ON p.id=pl.person_id WHERE pl.clinic_id=? AND pl.active=1 AND pl.deleted_at IS NULL AND (pl.registration_needs_update=1 OR pl.phone IS NULL OR pl.phone='' OR pl.email IS NULL OR pl.email='' OR pl.address_city IS NULL OR pl.address_city='') ORDER BY pl.updated_at ASC,pl.id ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.14' => (
                "SELECT pl.id,p.full_name FROM pi_patients pl INNER JOIN pi_persons p ON p.id=pl.person_id WHERE pl.clinic_id=? AND pl.active=1 AND pl.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM pi_care c WHERE c.clinic_id=pl.clinic_id AND c.patient_link_id=pl.id AND c.deleted_at IS NULL AND c.created_at>=DATE_SUB(NOW(), INTERVAL ? DAY)) AND NOT EXISTS (SELECT 1 FROM pi_appointments a WHERE a.clinic_id=pl.clinic_id AND a.patient_link_id=pl.id AND a.start_at>=DATE_SUB(NOW(), INTERVAL ? DAY)) ORDER BY pl.updated_at ASC, pl.id ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.15' => (
                "SELECT pl.id AS patient_link_id, p.full_name, p.birth_date FROM pi_patients pl INNER JOIN pi_persons p ON p.id=pl.person_id WHERE pl.clinic_id=? AND pl.active=1 AND pl.deleted_at IS NULL AND p.birth_date IS NOT NULL AND DATE_FORMAT(p.birth_date,'%m-%d')=? ORDER BY p.full_name ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.16' => (
                "SELECT dt.id,dt.title,dt.status,u.name AS owner_name FROM pi_document_templates dt LEFT JOIN pi_users u ON u.id=dt.owner_user_id AND EXISTS (SELECT 1 FROM pi_user_roles ur_owner WHERE ur_owner.user_id=u.id AND ur_owner.clinic_id=dt.clinic_id AND ur_owner.active=1) WHERE dt.clinic_id=? AND dt.status='pending_approval' AND COALESCE(dt.updated_at,dt.created_at)<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY COALESCE(dt.updated_at,dt.created_at) ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.17' => (
                "SELECT id,title,patient_link_id,updated_at,issued_at FROM pi_documents WHERE clinic_id=? AND document_status='preparado' AND COALESCE(updated_at,issued_at)<=DATE_SUB(NOW(), INTERVAL ? HOUR) ORDER BY COALESCE(updated_at,issued_at) ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.18' => (
                "SELECT id,title,issued_at,document_identifier FROM pi_documents WHERE clinic_id=? AND document_status='emitido' AND patient_link_id IS NULL AND issued_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY issued_at DESC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.19' => (
                "SELECT id,title,patient_link_id,issued_at,document_identifier FROM pi_documents WHERE clinic_id=? AND document_status='emitido' AND issued_at>=? AND issued_at<? ORDER BY issued_at DESC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.20' => (
                "SELECT id,title,due_at FROM pi_tasks WHERE clinic_id=? AND status IN ($activeTask) AND due_at IS NOT NULL AND due_at>=? AND due_at<? ORDER BY due_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.21' => (
                "SELECT id,title,due_at FROM pi_tasks WHERE clinic_id=? AND status IN ($activeTask) AND due_at IS NOT NULL AND due_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY due_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.22' => (
                "SELECT id,title,COALESCE(updated_at,started_at,created_at) AS ref_at FROM pi_tasks WHERE clinic_id=? AND status='em_andamento' AND COALESCE(updated_at,started_at,created_at)<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY ref_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.23' => (
                "SELECT t.id,t.title,e.created_at FROM pi_task_events e INNER JOIN pi_tasks t ON t.id=e.task_id AND t.clinic_id=e.clinic_id WHERE e.clinic_id=? AND e.event_key='devolvida_fila' AND e.created_at>=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY e.created_at DESC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.24' => (
                "SELECT id,title,patient_link_id,expected_at,amount_cents FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND expected_at>=? AND expected_at<? ORDER BY expected_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.25' => (
                "SELECT id,title,patient_link_id,expected_at,amount_cents FROM pi_financial_revenues WHERE clinic_id=? AND status='prevista' AND expected_at IS NOT NULL AND expected_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY expected_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.26' => (
                "SELECT id,title,due_at,amount_cents FROM pi_financial_expenses WHERE clinic_id=? AND status='prevista' AND due_at=? ORDER BY due_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.27' => (
                "SELECT id,title,due_at,amount_cents FROM pi_financial_expenses WHERE clinic_id=? AND status='prevista' AND due_at IS NOT NULL AND due_at<=? ORDER BY due_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.28' => (
                "SELECT n.id,n.title,n.created_at FROM pi_notices n WHERE n.clinic_id=? AND n.requires_ack=1 AND n.created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) AND NOT EXISTS (SELECT 1 FROM pi_notice_reads r WHERE r.notice_id=n.id AND (r.ack_at IS NOT NULL OR r.hidden_at IS NOT NULL) LIMIT 1) ORDER BY n.created_at ASC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.29' => (
                "SELECT violation_key,sql_fingerprint,route,MAX(details) AS details,MAX(created_at) AS created_at,COUNT(*) AS occurrences FROM pi_scope_violations WHERE clinic_id=? AND violation_key<>'write_in_read_only' AND created_at>=DATE_SUB(NOW(), INTERVAL ? DAY) GROUP BY violation_key,sql_fingerprint,route ORDER BY created_at DESC LIMIT $limit"
            ),
            'operational.maestro.03.maestro_fetch_candidates.30' => (
                "SELECT id,message,route,created_at FROM pi_error_events WHERE clinic_id=? AND resolved_at IS NULL AND created_at<=DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY created_at ASC LIMIT $limit"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
