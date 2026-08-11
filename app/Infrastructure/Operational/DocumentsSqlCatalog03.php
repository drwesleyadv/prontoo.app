<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class DocumentsSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.documents.03.document_issue_context.01' => (
                "SELECT p.full_name,p.cpf,p.birth_date,pl.phone,pl.email,pl.address,pl.address_number,pl.address_neighborhood,pl.address_city,pl.address_state,pl.address_zip FROM pi_patients pl JOIN pi_persons p ON p.id=pl.person_id WHERE pl.id=? AND pl.clinic_id=? AND pl.active=1"
            ),
            'operational.documents.03.document_issue_context.02' => (
                "SELECT display_name,legal_name,address_city,address_state,address_line FROM pi_clinics WHERE id=?"
            ),
            'operational.documents.03.document_issue_context.03' => (
                "SELECT u.id,u.name,u.email,GROUP_CONCAT(DISTINCT ur.role_code ORDER BY ur.role_code SEPARATOR ',') AS role_codes,GROUP_CONCAT(DISTINCT cr.label ORDER BY cr.sort_order SEPARATOR ', ') AS role_labels FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE u.id=? GROUP BY u.id,u.name,u.email"
            ),
            'operational.documents.03.document_issue_context.04' => (
                "SELECT name,phone,source,interest,stage,next_action_at,notes FROM pi_leads WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.03.document_issue_context.05' => (
                "SELECT title,category,description,duration_minutes,price_cents,payment_methods,pre_instructions,post_care FROM pi_procedures WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.03.document_issue_context.06' => (
                "SELECT t.title,t.status,t.due_at,td.description,u.name AS assigned_name FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id LEFT JOIN pi_users u ON u.id=COALESCE(t.assigned_to,t.target_user_id) WHERE t.id=? AND t.clinic_id=?"
            ),
            'operational.documents.03.document_issue_context.07' => (
                "SELECT c.title,c.record_type,c.created_at,cc.content,p.full_name AS patient_name FROM pi_care c LEFT JOIN pi_care_content cc ON cc.care_id=c.id AND cc.clinic_id=c.clinic_id JOIN pi_patients pl ON pl.id=c.patient_link_id AND pl.clinic_id=c.clinic_id JOIN pi_persons p ON p.id=pl.person_id WHERE c.id=? AND c.clinic_id=? AND c.deleted_at IS NULL"
            ),
            'operational.documents.03.approved_document_template_options.01' => (
                "SELECT dt.id,dt.title,dt.type_key FROM pi_document_templates dt WHERE dt.clinic_id=? AND dt.status='approved' AND " . DocumentQuerySql::templateVisibility($visibilityMode) . " ORDER BY dt.title ASC, dt.id DESC"
            ),
            'operational.documents.03.patient_document_timeline_items.01' => (
                "SELECT d.id,d.title,d.type_key,d.issued_at,u.name AS issued_name FROM pi_documents d LEFT JOIN pi_users u ON u.id=d.issued_by WHERE d.clinic_id=? AND d.patient_link_id=? ORDER BY d.issued_at DESC,d.id DESC LIMIT $limit"
            ),
            'operational.documents.03.fetch_document_for_current_user.01' => (
                "SELECT d.*,dt.owner_user_id,dt.owner_role,u.name AS issued_name,p.full_name AS patient_name,p.cpf AS patient_cpf,p.birth_date AS patient_birth_date FROM pi_documents d LEFT JOIN pi_document_templates dt ON dt.id=d.template_id AND dt.clinic_id=d.clinic_id LEFT JOIN pi_users u ON u.id=d.issued_by LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id AND pl.clinic_id=d.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.id=? AND d.clinic_id=? LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
