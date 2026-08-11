<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentitySecuritySqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.security01.mfa_enroll_user.01' => (
                "SELECT GET_LOCK(?,5)"
            ),
            'identity.security01.mfa_enroll_user.02' => (
                "SELECT RELEASE_LOCK(?)"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
