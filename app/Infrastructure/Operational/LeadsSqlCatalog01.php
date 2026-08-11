<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Operational;

use RuntimeException;

final class LeadsSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operationId, array $bindings): string
    {
        extract($bindings, EXTR_SKIP);
        return match ($operationId) {
            'operational.leads.01.lead_find_by_phone.01' => (
                "SELECT id,person_id,name,phone,phone_digits,source,interest,stage,next_action_at,notes,created_by,created_at,updated_at FROM pi_leads WHERE clinic_id=? AND (phone_digits=? OR LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),'(',''),')',''),' ',''),'-',''),'.',''),11)=?)" .
                                (!empty($excludeLead) ? " AND id<>?" : "") .
                                " ORDER BY CASE WHEN " .
                                LeadQuerySql::active("stage") .
                                " THEN 0 WHEN stage='arquivado' THEN 1 ELSE 2 END, COALESCE(updated_at,created_at) DESC, id DESC LIMIT 1"
            ),
            'operational.leads.01.lead_event_create.01' => (
                "INSERT INTO pi_lead_events (clinic_id,lead_id,event_type,stage_from,stage_to,phone,source,interest,next_action_at,body,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())"
            ),
            'operational.leads.01.lead_prepare_person_for_patient.01' => (
                "SELECT id FROM pi_persons WHERE cpf=? LIMIT 1"
            ),
            'operational.leads.01.lead_prepare_person_for_patient.02' => (
                "UPDATE pi_persons SET full_name=COALESCE(NULLIF(full_name,''),?), assinatura=COALESCE(NULLIF(assinatura,''),?), clinic_id=COALESCE(clinic_id,?), updated_at=NOW() WHERE id=?"
            ),
            'operational.leads.01.lead_prepare_person_for_patient.03' => (
                "UPDATE pi_leads SET person_id=?, name=COALESCE(NULLIF(name,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.01.lead_prepare_person_for_patient.04' => (
                "UPDATE pi_persons SET full_name=COALESCE(NULLIF(full_name,''),?), cpf=?, birth_date=?, assinatura=COALESCE(NULLIF(assinatura,''),?), clinic_id=COALESCE(clinic_id,?), updated_at=NOW() WHERE id=?"
            ),
            'operational.leads.01.lead_prepare_person_for_patient.05' => (
                "UPDATE pi_leads SET person_id=?, name=COALESCE(NULLIF(name,''),?), updated_at=NOW() WHERE id=? AND clinic_id=?"
            ),
            'operational.leads.01.lead_patient_by_cpf.01' => (
                "SELECT pat.id patient_id, pat.person_id, per.full_name, per.cpf, per.birth_date FROM pi_patients pat JOIN pi_persons per ON per.id=pat.person_id WHERE pat.clinic_id=? AND pat.active=1 AND per.cpf=? LIMIT 1"
            ),
            'operational.leads.01.lead_patient_by_phone.01' => (
                "SELECT pat.id patient_id, pat.person_id, pat.phone, per.full_name, per.cpf, per.birth_date FROM pi_patients pat JOIN pi_persons per ON per.id=pat.person_id WHERE pat.clinic_id=? AND pat.active=1 AND LEFT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(pat.phone,''),'(',''),')',''),' ',''),'-',''),'.',''),11)=? LIMIT 1"
            ),
            'operational.leads.01.page_lead_patient_lookup.01' => (
                "SELECT p.id,p.full_name,p.cpf,p.birth_date
                             FROM pi_persons p
                             WHERE p.cpf=?
                               AND (
                                 EXISTS (SELECT 1 FROM pi_patients pat WHERE pat.person_id=p.id AND pat.clinic_id=?)
                                 OR EXISTS (SELECT 1 FROM pi_leads l WHERE l.person_id=p.id AND l.clinic_id=?)
                                 OR EXISTS (
                                   SELECT 1
                                   FROM pi_users u
                                   JOIN pi_user_roles ur ON ur.user_id=u.id
                                   WHERE u.person_id=p.id AND ur.clinic_id=?
                                 )
                               )
                             LIMIT 1"
            ),
            'operational.leads.01.page_lead_patient_lookup.02' => (
                "SELECT id FROM pi_patients WHERE clinic_id=? AND person_id=? AND active=1 LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL operacional desconhecida.'),
        };
    }
}
