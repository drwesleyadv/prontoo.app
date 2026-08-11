<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityAuthSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.auth03.page_login.01' => (
                "SELECT GET_LOCK(?,2)"
            ),
            'identity.auth03.page_login.02' => (
                "SELECT p.full_name,p.cpf,p.birth_date,u.id uid,u.password_hash,u.active,u.is_global_admin
                                                 FROM pi_persons p
                                                 JOIN pi_users u ON u.person_id=p.id
                                                 WHERE p.cpf=? LIMIT 1"
            ),
            'identity.auth03.page_login.03' => (
                "SELECT RELEASE_LOCK(?)"
            ),
            'identity.auth03.login_locks_cleanup_maybe.01' => (
                "DELETE FROM pi_login_locks WHERE locked_until<UNIX_TIMESTAMP()-604800 LIMIT 500"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
