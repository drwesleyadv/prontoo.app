<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityPermissionsSqlCatalog04
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        $userFilter = match ((string) ($filter ?? '')) {
            'ativos' => ' AND ur.active=1 AND u.active=1',
            'profissionais' => " AND ur.active=1 AND u.active=1 AND ur.role_code='medico'",
            'sem_email' => " AND ur.active=1 AND u.active=1 AND COALESCE(u.email,'')=''",
            'inativos' => ' AND (ur.active=0 OR u.active=0)',
            default => '',
        };
        $userSearch = !empty($has_search)
            ? ' AND (u.name LIKE ? OR u.email LIKE ? OR p.full_name LIKE ? OR p.cpf LIKE ? OR p.phone LIKE ? OR ur.role_code LIKE ?)'
            : '';
        return match ($operation) {
            'identity.permissions04.page_users.01' => (
                "SELECT COUNT(*) FROM pi_user_roles WHERE user_id=? AND clinic_id=?"
            ),
            'identity.permissions04.page_users.02' => (
                "UPDATE pi_user_roles SET active=0 WHERE user_id=? AND clinic_id=?"
            ),
            'identity.permissions04.page_users.03' => (
                "SELECT COUNT(*) FROM pi_user_roles WHERE user_id=? AND active=1"
            ),
            'identity.permissions04.page_users.04' => (
                "UPDATE pi_users SET active=0, updated_at=NOW() WHERE id=?"
            ),
            'identity.permissions04.page_users.05' => (
                "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id WHERE ur.clinic_id=?"
            ),
            'identity.permissions04.page_users.06' => (
                "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1"
            ),
            'identity.permissions04.page_users.07' => (
                "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1 AND ur.role_code='medico'"
            ),
            'identity.permissions04.page_users.08' => (
                "SELECT COUNT(DISTINCT ur.user_id) FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id WHERE ur.clinic_id=? AND ur.active=1 AND u.active=1 AND COALESCE(u.email,'')=''"
            ),
            'identity.permissions04.page_users.09' => (
                "SELECT u.id user_id,u.name,u.email,u.active user_active,u.last_login_at,p.cpf,p.birth_date,p.phone,GROUP_CONCAT(CASE WHEN ur.active=1 THEN ur.role_code END ORDER BY FIELD(ur.role_code,'recepcionista','assistente','medico','gerente') SEPARATOR ',') active_roles,MAX(ur.active) any_role_active FROM pi_user_roles ur JOIN pi_users u ON u.id=ur.user_id LEFT JOIN pi_persons p ON p.id=u.person_id WHERE ur.clinic_id=?{$userFilter}{$userSearch} GROUP BY u.id,u.name,u.email,u.active,u.last_login_at,p.cpf,p.birth_date,p.phone ORDER BY any_role_active DESC,u.name ASC LIMIT 220"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
