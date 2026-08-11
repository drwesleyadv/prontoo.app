<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class LeadsSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.leads.02.page_leads.01' => (
                "SELECT id,person_id,name,phone,notes,stage FROM pi_leads WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.02.page_leads.02' => (
                "UPDATE pi_leads SET stage='arquivado', updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.02.page_leads.03' => (
                "INSERT INTO pi_patients (clinic_id,person_id,phone,notes,created_by,created_at) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE phone=COALESCE(NULLIF(VALUES(phone),''),phone), notes=COALESCE(NULLIF(VALUES(notes),''),notes), active=1, updated_at=NOW()"
            ),
            'operational.leads.02.page_leads.04' => (
                "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? LIMIT 1"
            ),
            'operational.leads.02.page_leads.05' => (
                "UPDATE pi_leads SET person_id=?, stage='convertido', updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.02.page_leads.06' => (
                "SELECT id,name,phone,source,interest,stage,next_action_at FROM pi_leads WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.02.page_leads.07' => (
                "UPDATE pi_leads SET stage='arquivado', updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.02.page_leads.08' => (
                "SELECT id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes FROM pi_leads WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.02.page_leads.09' => (
                "UPDATE pi_leads SET name=?, source=?, interest=?, stage=?, next_action_at=?, notes=COALESCE(NULLIF(?,''),notes), phone_digits=COALESCE(NULLIF(phone_digits,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.02.page_leads.10' => (
                "UPDATE pi_leads SET person_id=COALESCE(NULLIF(?,0),person_id), name=?, source=COALESCE(NULLIF(?,''),source), interest=COALESCE(NULLIF(?,''),interest), stage=?, next_action_at=COALESCE(?,next_action_at), notes=COALESCE(NULLIF(?,''),notes), phone_digits=COALESCE(NULLIF(phone_digits,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.02.page_leads.11' => (
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND person_id=? AND " .
                                                LeadQuerySql::active("stage")
            ),
            'operational.leads.02.page_leads.12' => (
                "INSERT INTO pi_leads (clinic_id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.leads.02.page_leads.13' => (
                "SELECT id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes,created_at,updated_at FROM pi_leads WHERE clinic_id=?" .
                                (!empty($searchMode)
                                    ? " AND (name LIKE ? OR phone LIKE ? OR phone_digits LIKE ? OR source LIKE ? OR interest LIKE ?)"
                                    : " AND " . LeadQuerySql::column("stage") . "=?") .
                                " ORDER BY CASE WHEN stage='convertido' THEN 5 WHEN stage='arquivado' THEN 6 WHEN next_action_at IS NOT NULL AND next_action_at<NOW() THEN 0 WHEN next_action_at IS NOT NULL THEN 1 ELSE 2 END, COALESCE(next_action_at,created_at) ASC, id DESC LIMIT 140"
            ),
            'operational.leads.02.page_leads.14' => (
                "SELECT " .
                                LeadQuerySql::column("stage") .
                                " AS stage,COUNT(*) total FROM pi_leads WHERE clinic_id=? GROUP BY " .
                                LeadQuerySql::column("stage")
            ),
            'operational.leads.02.page_leads.15' => (
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                                    LeadQuerySql::active("stage")
            ),
            'operational.leads.02.page_leads.16' => (
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                                    LeadQuerySql::active("stage") .
                                    " AND created_at>=DATE_SUB(NOW(), INTERVAL 30 DAY)"
            ),
            'operational.leads.02.page_leads.17' => (
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                                    LeadQuerySql::active("stage") .
                                    " AND next_action_at>=? AND next_action_at<?"
            ),
            'operational.leads.02.page_leads.18' => (
                "SELECT COUNT(*) FROM pi_leads WHERE clinic_id=? AND " .
                                    LeadQuerySql::active("stage") .
                                    " AND next_action_at IS NOT NULL AND next_action_at<NOW()"
            ),
            'operational.leads.02.page_leads.19' => (
                "SELECT id,person_id FROM pi_patients WHERE clinic_id=? AND person_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ") AND active=1 LIMIT 160"
            ),
            'operational.leads.02.page_leads.20' => (
                "SELECT id,lead_id,event_type,stage_from,stage_to,body,created_by,created_at FROM pi_lead_events WHERE clinic_id=? AND lead_id IN (" . OperationalSequenceSql::placeholders((int) $itemCount) . ") ORDER BY created_at ASC,id ASC LIMIT 420"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
