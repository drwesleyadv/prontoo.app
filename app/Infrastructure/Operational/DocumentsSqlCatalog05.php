<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class DocumentsSqlCatalog05
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.documents.05.page_documents.01' => (
                "SELECT id,owner_user_id,owner_role FROM pi_document_templates WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.05.page_documents.02' => (
                "UPDATE pi_document_templates SET type_key=?,title=?,body=?,status=?,approval_required=?,approved_by=?,approved_at=?,updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.05.page_documents.03' => (
                "INSERT INTO pi_document_templates (clinic_id,owner_user_id,owner_role,type_key,title,body,status,approval_required,approved_by,approved_at,updated_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.documents.05.page_documents.04' => (
                "SELECT id,title FROM pi_document_templates WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.05.page_documents.05' => (
                "UPDATE pi_document_templates SET status=?,approval_required=0,approved_by=?,approved_at=NOW(),updated_by=?,updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.documents.05.page_documents.06' => (
                "SELECT dt.id,dt.owner_user_id,dt.owner_role,dt.type_key,dt.title,dt.status,u.name AS owner_name,(SELECT MAX(d.issued_at) FROM pi_documents d WHERE d.clinic_id=dt.clinic_id AND d.template_id=dt.id) AS last_used_at FROM pi_document_templates dt LEFT JOIN pi_users u ON u.id=dt.owner_user_id WHERE dt.clinic_id=? AND " .
                                DocumentQuerySql::templateVisibility($visibilityMode) .
                                " ORDER BY FIELD(dt.status,'pending_approval','approved','rejected','archived'), IF(dt.owner_role=?,0,1), COALESCE(last_used_at,dt.updated_at,dt.created_at) DESC, dt.title ASC LIMIT 240"
            ),
            'operational.documents.05.page_documents.07' => (
                "SELECT d.id,d.title,d.type_key,d.issued_at,d.updated_at,d.confirmed_at,d.document_status,d.patient_link_id,d.appointment_id,d.document_identifier,u.name AS issued_name,p.full_name AS patient_name,dt.owner_role FROM pi_documents d LEFT JOIN pi_document_templates dt ON dt.id=d.template_id AND dt.clinic_id=d.clinic_id LEFT JOIN pi_users u ON u.id=d.issued_by LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id AND pl.clinic_id=d.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.clinic_id=?" .
                                DocumentQuerySql::issuedDocumentVisibility($role) .
                                " AND d.document_status='emitido'" .
                                DocumentQuerySql::issuedDocumentSearch((bool) $search) .
                                " ORDER BY COALESCE(d.issued_at,d.confirmed_at,d.updated_at) DESC,d.id DESC LIMIT " .
                                (int) $docLimit
            ),
            'operational.documents.05.page_documents.08' => (
                "SELECT dt.id,dt.owner_user_id,dt.owner_role,dt.type_key,dt.title,dt.body,dt.status FROM pi_document_templates dt WHERE dt.id=? AND dt.clinic_id=? AND " .
                                DocumentQuerySql::templateVisibility($visibilityMode) .
                                " LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
