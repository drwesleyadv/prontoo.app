<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentitySecuritySqlCatalog04
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.security04.ctx.01' => (
                "SELECT id,person_id,name,email,password_hash,is_global_admin,active,failed_login_count,locked_until,last_login_at,created_at,updated_at FROM pi_users WHERE id=? AND active=1"
            ),
            'identity.security04.ctx.02' => (
                "SELECT cpf,birth_date FROM pi_persons WHERE id=?"
            ),
            'identity.security04.ctx.03' => (
                "SELECT id,user_id,clinic_id,role_code,is_owner,active,created_at FROM pi_user_roles WHERE user_id=? AND active=1 ORDER BY clinic_id ASC, is_owner DESC, FIELD(role_code,'gerente','medico','assistente','recepcionista'), id ASC LIMIT 80"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
