<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityPermissionsSqlCatalog05
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.permissions05.page_user.01' => (
                "SELECT u.id user_id,u.name,u.email,u.active user_active,u.last_login_at,u.person_id,p.full_name,p.cpf,p.birth_date,p.phone,p.email person_email,p.address_zip,p.address,p.address_number,p.address_neighborhood,p.address_complement,p.address_state,p.address_city,p.address_city_ibge FROM pi_users u LEFT JOIN pi_persons p ON p.id=u.person_id WHERE u.id=? AND EXISTS (SELECT 1 FROM pi_user_roles ur WHERE ur.user_id=u.id AND ur.clinic_id=?) LIMIT 1"
            ),
            'identity.permissions05.page_user.02' => (
                "SELECT id,role_code,active FROM pi_user_roles WHERE user_id=? AND clinic_id=? ORDER BY FIELD(role_code,'recepcionista','assistente','medico','gerente')"
            ),
            'identity.permissions05.page_user.03' => (
                "UPDATE pi_persons SET full_name=?, updated_at=NOW() WHERE id=?"
            ),
            'identity.permissions05.page_user.04' => (
                "UPDATE pi_users SET name=?, email=NULLIF(?,''), active=1, updated_at=NOW() WHERE id=?"
            ),
            'identity.permissions05.page_user.05' => (
                "UPDATE pi_user_roles SET active=0 WHERE user_id=? AND clinic_id=?"
            ),
            'identity.permissions05.page_user.06' => (
                "SELECT COUNT(*) FROM pi_user_roles WHERE user_id=? AND active=1"
            ),
            'identity.permissions05.page_user.07' => (
                "UPDATE pi_users SET active=0, updated_at=NOW() WHERE id=?"
            ),
            'identity.permissions05.page_permissions.01' => (
                "SELECT DISTINCT user_id FROM pi_user_roles WHERE clinic_id=? AND role_code=? AND active=1"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
