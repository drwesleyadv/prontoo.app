<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityPermissionsSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.permissions02.clinic_enable_roles_from_active_user_links.01' => (
                "SELECT DISTINCT ur.clinic_id,ur.role_code FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id AND c.active=1 LEFT JOIN pi_clinic_roles cr ON cr.clinic_id=ur.clinic_id AND cr.role_code=ur.role_code WHERE ur.user_id=? AND ur.active=1 AND (cr.role_code IS NULL OR COALESCE(cr.enabled,0)=0)"
            ),
            'identity.permissions02.sync_user_roles_for_clinic.01' => (
                "SELECT COALESCE(MAX(is_owner),0) FROM pi_user_roles WHERE user_id=? AND clinic_id=?"
            ),
            'identity.permissions02.sync_user_roles_for_clinic.02' => (
                'UPDATE pi_user_roles SET active=0 WHERE user_id=? AND clinic_id=? AND active=1 AND role_code NOT IN (' .
                implode(',', array_fill(0, max(1, (int) ($role_count ?? 0)), '?')) . ')'
            ),
            'identity.permissions02.sync_user_roles_for_clinic.03' => (
                "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active,created_at) VALUES (?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE active=1,is_owner=GREATEST(is_owner,VALUES(is_owner))"
            ),
            'identity.permissions02.active_role_codes_for_user_in_clinic.01' => (
                "SELECT role_code FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND active=1 ORDER BY FIELD(role_code,'recepcionista','assistente','medico','gerente'), id ASC"
            ),
            'identity.permissions02.preferred_active_role_link_for_user.01' => (
                "SELECT id,clinic_id,role_code FROM pi_user_roles WHERE id=? AND user_id=? AND clinic_id=? AND active=1 LIMIT 1"
            ),
            'identity.permissions02.preferred_active_role_link_for_user.02' => (
                "SELECT id,clinic_id,role_code FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND active=1 ORDER BY is_owner DESC, FIELD(role_code,'gerente','medico','assistente','recepcionista'), id ASC LIMIT 1"
            ),
            'identity.permissions02.team_options.01' => (
                "SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND active=1 ORDER BY id ASC LIMIT 200"
            ),
            'identity.permissions02.clinic_role_user_ids.01' => (
                'SELECT user_id FROM pi_user_roles WHERE clinic_id=? AND active=1 AND role_code IN (' .
                implode(',', array_fill(0, max(1, (int) ($role_count ?? 0)), '?')) .
                ') ORDER BY id DESC LIMIT 800'
            ),
            'identity.permissions02.permission_rules_for_role.01' => (
                "SELECT action_key,operation_key,allowed FROM pi_permission_rules WHERE clinic_id=? AND role_code=?"
            ),
            'identity.permissions02.persist_permission_rules.01' => (
                "INSERT INTO pi_permission_rules (clinic_id,role_code,action_key,operation_key,allowed) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)"
            ),
            'identity.permissions02.persist_permission_rules.02' => (
                "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
