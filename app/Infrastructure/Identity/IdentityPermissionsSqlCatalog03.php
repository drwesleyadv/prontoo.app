<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityPermissionsSqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.permissions03.collaborator_permission_matrix_base.01' => (
                "SELECT action_key,allowed FROM pi_permissions WHERE clinic_id=? AND role_code=?"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
