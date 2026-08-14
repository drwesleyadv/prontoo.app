<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentitySecuritySqlCatalog02
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.security02.mfa_verify_user_code.01' => (
                "SELECT GET_LOCK(?,5)"
            ),
            'identity.security02.mfa_verify_user_code.02' => (
                "SELECT RELEASE_LOCK(?)"
            ),
            'identity.security02.mfa_recovery_codes_regenerate.01' => (
                "SELECT GET_LOCK(?,5)"
            ),
            'identity.security02.mfa_recovery_codes_regenerate.02' => (
                "SELECT RELEASE_LOCK(?)"
            ),
            'identity.security02.mfa_replace_user.01' => (
                "SELECT GET_LOCK(?,5)"
            ),
            'identity.security02.mfa_replace_user.02' => (
                "SELECT RELEASE_LOCK(?)"
            ),
            'identity.security02.mfa_disable_user.01' => (
                "SELECT GET_LOCK(?,5)"
            ),
            'identity.security02.mfa_disable_user.02' => (
                "SELECT is_global_admin FROM pi_users WHERE id=? LIMIT 1"
            ),
            'identity.security02.mfa_disable_user.03' => (
                "SELECT RELEASE_LOCK(?)"
            ),
            'identity.security02.user_auth_generation_current.01' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1"
            ),
            'identity.security02.auth_generation_current.01' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1"
            ),
            'identity.security02.user_auth_generation_ensure.01' => (
                "SELECT GET_LOCK(?,5)"
            ),
            'identity.security02.user_auth_generation_ensure.02' => (
                "SELECT RELEASE_LOCK(?)"
            ),
            'identity.security02.user_auth_generation_ensure.03' => (
                "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), updated_at=NOW()"
            ),
            'identity.security02.user_auth_generation_rotate.01' => (
                "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
