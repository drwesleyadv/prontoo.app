<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class DocumentsSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.documents.02.document_appointment_row.01' => (
                "SELECT a.id,a.patient_link_id,a.doctor_user_id,a.start_at,a.end_at,a.reason,a.notes,a.status,a.consultation_started_at,a.consultation_finished_at,COALESCE(pr.title,a.reason,'Consulta') AS procedure_title,u.name AS doctor_name,p.full_name AS patient_name FROM pi_appointments a LEFT JOIN pi_procedures pr ON pr.id=a.procedure_id AND pr.clinic_id=a.clinic_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE a.id=? AND a.clinic_id=? LIMIT 1"
            ),
            'operational.documents.02.document_appointment_options.01' => (
                "SELECT a.id,a.patient_link_id,a.start_at,a.end_at,a.reason,a.status,a.consultation_started_at,a.consultation_finished_at,COALESCE(pr.title,a.reason,'Consulta') AS procedure_title,u.name AS doctor_name,p.full_name AS patient_name FROM pi_appointments a LEFT JOIN pi_procedures pr ON pr.id=a.procedure_id AND pr.clinic_id=a.clinic_id LEFT JOIN pi_users u ON u.id=a.doctor_user_id LEFT JOIN pi_patients pl ON pl.id=a.patient_link_id AND pl.clinic_id=a.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE a.clinic_id=? AND COALESCE(a.status,'')<>'cancelado' ORDER BY ABS(TIMESTAMPDIFF(SECOND,a.start_at,NOW())) ASC, a.start_at DESC LIMIT " .
                                (int) $limit
            ),
            'operational.documents.02.document_context_ids_from_post.01' => (
                DocumentContextSql::entityExists((string) $entity)
            ),
            'operational.documents.02.document_context_select_options.01' => (
                "SELECT id,name,phone,interest,stage,created_at FROM pi_leads WHERE clinic_id=? ORDER BY updated_at DESC, created_at DESC, id DESC LIMIT " .
                                            (int) $limit
            ),
            'operational.documents.02.document_context_select_options.02' => (
                "SELECT t.id,t.title,t.status,t.due_at,u.name AS assigned_name FROM pi_tasks t LEFT JOIN pi_users u ON u.id=COALESCE(t.assigned_to,t.target_user_id) WHERE t.clinic_id=? ORDER BY FIELD(t.status,'aberta','em_andamento','concluida'), COALESCE(t.due_at,t.created_at) DESC, t.id DESC LIMIT " .
                                            (int) $limit
            ),
            'operational.documents.02.document_context_select_options.03' => (
                "SELECT c.id,c.record_type,c.title,c.created_at,p.full_name AS patient_name FROM pi_care c JOIN pi_patients pl ON pl.id=c.patient_link_id AND pl.clinic_id=c.clinic_id JOIN pi_persons p ON p.id=pl.person_id WHERE c.clinic_id=? AND c.deleted_at IS NULL ORDER BY c.created_at DESC,c.id DESC LIMIT " .
                                            (int) $limit
            ),
            'operational.documents.02.document_context_select_options.04' => (
                "SELECT u.id,u.name,u.email,GROUP_CONCAT(DISTINCT cr.label ORDER BY cr.sort_order SEPARATOR ', ') AS role_label FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE u.active=1 GROUP BY u.id,u.name,u.email ORDER BY u.name ASC LIMIT " .
                                            (int) $limit
            ),
            'operational.documents.02.document_context_select_options.05' => (
                "SELECT name FROM pi_leads WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.02.document_context_select_options.06' => (
                "SELECT title FROM pi_procedures WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.02.document_context_select_options.07' => (
                "SELECT title FROM pi_tasks WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.02.document_context_select_options.08' => (
                "SELECT COALESCE(title,record_type) FROM pi_care WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.02.document_context_select_options.09' => (
                "SELECT name FROM pi_users u WHERE id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1)"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
