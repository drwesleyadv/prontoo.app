<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentitySecuritySqlCatalog03
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.security03.require_same_clinic_entity.01' => (
                match ((string) $entity) {
                    'patient' => 'SELECT id,person_id,active,deleted_at FROM pi_patients WHERE id=? AND clinic_id=? LIMIT 1',
                    default => throw new \InvalidArgumentException('Entidade com escopo inválida.'),
                }
            ),
            'identity.security03.effective_allowed_modules_for_roles.01' => (
                'SELECT DISTINCT action_key FROM pi_permissions WHERE clinic_id=? AND role_code IN (' .
                implode(',', array_fill(0, max(1, (int) ($role_count ?? 0)), '?')) .
                ') AND allowed=1'
            ),
            'identity.security03.seed_permissions.01' => (
                "INSERT INTO pi_permissions (clinic_id, role_code, action_key, allowed) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE allowed=allowed"
            ),
            'identity.security03.secret_key.01' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key='app_secret'"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
