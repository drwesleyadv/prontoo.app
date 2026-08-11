<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityAuthSqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.auth02.page_global_reauth.01' => (
                "SELECT id,name,password_hash,is_global_admin,active FROM pi_users WHERE id=? AND active=1 LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
