<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class DocumentsSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.documents.01.document_assign_identifier.01' => (
                "SELECT id,document_identifier FROM pi_documents WHERE id=? AND clinic_id=? LIMIT 1 FOR UPDATE"
            ),
            'operational.documents.01.document_assign_identifier.02' => (
                "SELECT id,document_code_alphabet,document_sequence FROM pi_clinics WHERE id=? LIMIT 1 FOR UPDATE"
            ),
            'operational.documents.01.document_assign_identifier.03' => (
                "SELECT COALESCE(MAX(document_sequence),0) FROM pi_documents WHERE clinic_id=?"
            ),
            'operational.documents.01.document_assign_identifier.04' => (
                "SELECT COUNT(*) FROM pi_documents WHERE clinic_id=? AND document_identifier=? AND id<>?"
            ),
            'operational.documents.01.document_assign_identifier.05' => (
                "UPDATE pi_clinics SET document_code_alphabet=?, document_sequence=? WHERE id=?"
            ),
            'operational.documents.01.document_assign_identifier.06' => (
                "UPDATE pi_documents SET document_sequence=?, document_identifier=? WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.01.create_document_draft_from_template.01' => (
                "SELECT dt.id,dt.owner_user_id,dt.owner_role,dt.type_key,dt.title,dt.body,dt.status FROM pi_document_templates dt WHERE dt.id=? AND dt.clinic_id=? AND " . DocumentQuerySql::templateVisibility($visibilityMode)
            ),
            'operational.documents.01.create_document_draft_from_template.02' => (
                "INSERT INTO pi_documents (clinic_id,template_id,patient_link_id,appointment_id,context_json,issued_by,type_key,title,content,document_status,issued_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?, 'preparado',NOW(),NOW())"
            ),
            'operational.documents.01.save_document_draft.01' => (
                "SELECT dt.id,dt.title,dt.body,dt.type_key FROM pi_document_templates dt WHERE dt.id=? AND dt.clinic_id=? AND dt.status='approved'"
            ),
            'operational.documents.01.save_document_draft.02' => (
                "UPDATE pi_documents SET title=?, patient_link_id=?, appointment_id=?, context_json=?, content=?, document_status='preparado', updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.01.discard_document_draft.01' => (
                "UPDATE pi_documents SET document_status='cancelado', updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.01.confirm_document_issue.01' => (
                "UPDATE pi_documents SET document_status='emitido', confirmed_at=NOW(), issued_at=NOW(), updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
