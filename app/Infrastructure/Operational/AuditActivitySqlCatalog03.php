<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class AuditActivitySqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.audit_activity.03.clinic_recent_metrics.01' => (
                "SELECT clinic_id,metric_key,SUM(metric_value) AS metric_value FROM pi_clinic_daily_stats WHERE clinic_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ") AND day_date>=? GROUP BY clinic_id,metric_key"
            ),
            'operational.audit_activity.03.audit_patient_name_by_link.01' => (
                "SELECT id,person_id FROM pi_patients WHERE id=? AND clinic_id=?"
            ),
            'operational.audit_activity.03.audit_patient_name_by_link.02' => (
                "SELECT id,person_id FROM pi_patients WHERE id=?"
            ),
            'operational.audit_activity.03.audit_patient_name_by_link.03' => (
                "SELECT full_name FROM pi_persons WHERE id=?"
            ),
            'operational.audit_activity.03.audit_user_name_lookup.01' => (
                "SELECT u.id,u.name FROM pi_users u WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=? AND ur.active=1) LIMIT 1"
            ),
            'operational.audit_activity.03.audit_user_name_lookup.02' => (
                "SELECT id,name FROM pi_users WHERE id=?"
            ),
            'operational.audit_activity.03.audit_clinic_name_lookup.01' => (
                "SELECT id,display_name FROM pi_clinics WHERE id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.01' => (
                "SELECT id,title FROM pi_tasks WHERE id=? AND clinic_id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.02' => (
                "SELECT id,title FROM pi_tasks WHERE id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.03' => (
                "SELECT id,title,type_key,status FROM pi_document_templates WHERE id=? AND clinic_id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.04' => (
                "SELECT id,title,type_key,status FROM pi_document_templates WHERE id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.05' => (
                "SELECT d.id,d.title,d.type_key,d.patient_link_id,p.full_name AS patient_name FROM pi_documents d LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id AND pl.clinic_id=d.clinic_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.id=? AND d.clinic_id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.06' => (
                "SELECT d.id,d.title,d.type_key,d.patient_link_id,p.full_name AS patient_name FROM pi_documents d LEFT JOIN pi_patients pl ON pl.id=d.patient_link_id LEFT JOIN pi_persons p ON p.id=pl.person_id WHERE d.id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.07' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,reason FROM pi_appointments WHERE id=? AND clinic_id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.08' => (
                "SELECT id,patient_link_id,doctor_user_id,start_at,end_at,reason FROM pi_appointments WHERE id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.09' => (
                "SELECT t.id,t.title,t.assigned_to,td.patient_link_id,t.due_at FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id AND td.clinic_id=t.clinic_id WHERE t.id=? AND t.clinic_id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.10' => (
                "SELECT t.id,t.title,t.assigned_to,td.patient_link_id,t.due_at FROM pi_tasks t LEFT JOIN pi_task_details td ON td.task_id=t.id WHERE t.id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.11' => (
                "SELECT id,title FROM pi_notices WHERE id=? AND clinic_id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.12' => (
                "SELECT id,title FROM pi_notices WHERE id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.13' => (
                "SELECT id,name,person_id FROM pi_leads WHERE id=? AND clinic_id=?"
            ),
            'operational.audit_activity.03.audit_enrich_context.14' => (
                "SELECT id,name,person_id FROM pi_leads WHERE id=?"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
