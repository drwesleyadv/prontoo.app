<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityAuthSqlCatalog07
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.auth07.page_onboarding.01' => (
                "SELECT id,display_name,legal_name,legal_document,phone,responsible_profession,clinic_icon,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,onboarding_done FROM pi_clinics WHERE id=?"
            ),
            'identity.auth07.page_onboarding.02' => (
                "UPDATE pi_clinics SET display_name=?, phone=?, responsible_profession=?, clinic_icon=?, accent_color=?, address_line=?, address_state=?, address_city=?, address_city_ibge=?, timezone=?, onboarding_done=1, onboarding_completed_at=NOW(), subscription_status='trial', trial_started_at=COALESCE(NULLIF(trial_started_at,0),?), trial_ends_at=IF(trial_ends_at IS NULL OR trial_ends_at=0 OR trial_ends_at<NOW(),?,trial_ends_at), paid_until=NULL, updated_at=NOW() WHERE id=?"
            ),
            'identity.auth07.page_onboarding.03' => (
                "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE label=VALUES(label), icon_name=IF(icon_name='' OR (icon_name='support_agent' AND role_code<>'recepcionista'),VALUES(icon_name),icon_name), enabled=VALUES(enabled), sort_order=VALUES(sort_order)"
            ),
            'identity.auth07.page_onboarding.04' => (
                "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)"
            ),
            'identity.auth07.page_onboarding.05' => (
                "SELECT role_code,enabled FROM pi_clinic_roles WHERE clinic_id=?"
            ),
            'identity.auth07.person_autosuggest_datalist.01' => (
                "SELECT DISTINCT p.id,p.full_name,p.cpf,p.birth_date FROM pi_persons p JOIN (SELECT person_id FROM pi_patients WHERE clinic_id=? AND person_id IS NOT NULL UNION SELECT person_id FROM pi_leads WHERE clinic_id=? AND person_id IS NOT NULL) x ON x.person_id=p.id WHERE p.full_name<>'' ORDER BY p.full_name ASC LIMIT 500"
            ),
            'identity.auth07.save_person_by_document.01' => (
                "SELECT id FROM pi_persons WHERE legal_document=? LIMIT 1"
            ),
            'identity.auth07.save_person_by_document.02' => (
                "UPDATE pi_persons SET full_name=COALESCE(NULLIF(full_name,''),?), updated_at=NOW() WHERE id=?"
            ),
            'identity.auth07.save_person_by_document.03' => (
                "INSERT INTO pi_persons (full_name,cpf,birth_date,legal_document,created_at) VALUES (?,NULL,NULL,?,NOW())"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
