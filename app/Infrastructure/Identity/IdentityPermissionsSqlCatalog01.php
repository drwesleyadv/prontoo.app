<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityPermissionsSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.permissions01.save_team_member.01' => (
                "SELECT id,name,email,password_hash,active FROM pi_users WHERE person_id=? LIMIT 1"
            ),
            'identity.permissions01.save_team_member.02' => (
                "INSERT INTO pi_users (person_id,name,email,password_hash,active,created_at) VALUES (?,?,?,?,1,NOW())"
            ),
            'identity.permissions01.save_team_member.03' => (
                "SELECT id FROM pi_user_roles WHERE user_id=? AND clinic_id=? LIMIT 1"
            ),
            'identity.permissions01.save_team_member.04' => (
                "UPDATE pi_users SET active=1,updated_at=NOW() WHERE id=?"
            ),
            'identity.permissions01.save_team_member.05' => (
                "UPDATE pi_users SET name=?,email=COALESCE(NULLIF(?,''),email),updated_at=NOW() WHERE id=?"
            ),
            'identity.permissions01.clinic_user_exists.01' => (
                "SELECT ur.id FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.user_id=? AND ur.clinic_id=? AND ur.active=1" .
                ((int) ($role_count ?? 0) > 0
                    ? ' AND ur.role_code IN (' . implode(',', array_fill(0, (int) $role_count, '?')) . ')'
                    : '') .
                ' LIMIT 1'
            ),
            'identity.permissions01.person_has_other_clinic_links.01' => (
                "SELECT COUNT(*) FROM (SELECT clinic_id FROM pi_patients WHERE person_id=? AND clinic_id<>? UNION SELECT clinic_id FROM pi_leads WHERE person_id=? AND clinic_id<>? UNION SELECT ur.clinic_id FROM pi_users u JOIN pi_user_roles ur ON ur.user_id=u.id AND ur.active=1 WHERE u.person_id=? AND ur.clinic_id<>?) x"
            ),
            'identity.permissions01.clinic_active_role_count.01' => (
                "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.clinic_id=? AND ur.role_code=? AND ur.active=1" .
                (!empty($exclude_user) ? ' AND ur.user_id<>?' : '')
            ),
            'identity.permissions01.clinic_auto_assign_missing_managers.01' => (
                "SELECT c.id,c.owner_user_id,c.manager_user_id FROM pi_clinics c WHERE c.active=1 AND NOT EXISTS (SELECT 1 FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.clinic_id=c.id AND ur.role_code='gerente' AND ur.active=1) ORDER BY c.id ASC LIMIT {$limit}"
            ),
            'identity.permissions01.clinic_auto_assign_missing_managers.02' => (
                "SELECT ur.user_id FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id AND u.active=1 WHERE ur.clinic_id=? AND ur.active=1 ORDER BY COALESCE(ur.created_at,0) ASC, ur.id ASC LIMIT 1"
            ),
            'identity.permissions01.clinic_auto_assign_missing_managers.03' => (
                "SELECT COUNT(*) FROM pi_users WHERE id=? AND active=1"
            ),
            'identity.permissions01.clinic_auto_assign_missing_managers.04' => (
                "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active,created_at) VALUES (?,?, 'gerente',0,1,NOW()) ON DUPLICATE KEY UPDATE active=1"
            ),
            'identity.permissions01.clinic_auto_assign_missing_managers.05' => (
                "UPDATE pi_clinics SET manager_user_id=?, updated_at=NOW() WHERE id=? AND (manager_user_id IS NULL OR manager_user_id=0 OR NOT EXISTS (SELECT 1 FROM pi_users u WHERE u.id=pi_clinics.manager_user_id AND u.active=1))"
            ),
            'identity.permissions01.active_clinic_roles_for_user.01' => (
                "SELECT ur.id,ur.user_id,ur.clinic_id,ur.role_code,ur.is_owner,ur.active,c.display_name,c.timezone,c.address_state,c.address_city,c.active clinic_active FROM pi_user_roles ur JOIN pi_clinics c ON c.id=ur.clinic_id WHERE ur.user_id=? AND ur.active=1 AND c.active=1 ORDER BY c.display_name ASC, ur.is_owner DESC, FIELD(ur.role_code,'gerente','medico','assistente','recepcionista'), ur.id ASC LIMIT 80"
            ),
            'identity.permissions01.user_is_global_admin.01' => (
                "SELECT is_global_admin FROM pi_users WHERE id=? AND active=1"
            ),
            'identity.permissions01.clinic_restore_default_permissions_for_role.01' => (
                "INSERT INTO pi_permissions (clinic_id,role_code,action_key,allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)"
            ),
            'identity.permissions01.clinic_restore_default_permissions_for_role.02' => (
                "INSERT INTO pi_permission_rules (clinic_id,role_code,action_key,operation_key,allowed) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE allowed=VALUES(allowed)"
            ),
            'identity.permissions01.clinic_enable_roles_for_assignment.01' => (
                "SELECT enabled FROM pi_clinic_roles WHERE clinic_id=? AND role_code=? LIMIT 1"
            ),
            'identity.permissions01.clinic_enable_roles_for_assignment.02' => (
                "INSERT INTO pi_clinic_roles (clinic_id,role_code,label,icon_name,enabled,sort_order) VALUES (?,?,?,?,1,?) ON DUPLICATE KEY UPDATE enabled=1,label=COALESCE(NULLIF(label,''),VALUES(label)),icon_name=IF(icon_name='' OR icon_name='support_agent',VALUES(icon_name),icon_name),sort_order=IF(sort_order IS NULL OR sort_order=0,VALUES(sort_order),sort_order)"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
