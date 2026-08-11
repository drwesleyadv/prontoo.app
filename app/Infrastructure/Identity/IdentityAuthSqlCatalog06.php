<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityAuthSqlCatalog06
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.auth06.page_profile.01' => (
                "SELECT u.id,u.person_id,u.name,u.email,u.password_hash,u.is_global_admin,p.cpf,p.birth_date FROM pi_users u JOIN pi_persons p ON p.id=u.person_id WHERE u.id=? AND u.active=1 LIMIT 1"
            ),
            'identity.auth06.page_profile.02' => (
                "SELECT id FROM pi_users WHERE email=? AND id<>? LIMIT 1"
            ),
            'identity.auth06.page_profile.03' => (
                "UPDATE pi_persons SET full_name=?, updated_at=NOW() WHERE id=?"
            ),
            'identity.auth06.page_profile.04' => (
                "UPDATE pi_users SET name=?, email=?, updated_at=NOW() WHERE id=?"
            ),
            'identity.auth06.page_profile.05' => (
                "UPDATE pi_users SET password_hash=?, updated_at=NOW() WHERE id=?"
            ),
            'identity.auth06.page_profile.06' => (
                "SELECT ur.id,ur.clinic_id,ur.role_code,COALESCE(NULLIF(cr.label,''),ur.role_code) role_label FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id AND c.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE ur.id=? AND ur.user_id=? AND ur.active=1 LIMIT 1"
            ),
            'identity.auth06.page_profile.07' => (
                "SELECT ur.id,ur.clinic_id,ur.role_code,c.display_name clinic_name,COALESCE(NULLIF(cr.label,''),ur.role_code) role_label,COALESCE(NULLIF(cr.icon_name,''),'workspaces') icon_name FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id AND c.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE ur.user_id=? AND ur.active=1 ORDER BY c.display_name ASC, ur.is_owner DESC, FIELD(ur.role_code,'gerente','medico','assistente','recepcionista'), ur.id ASC"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
