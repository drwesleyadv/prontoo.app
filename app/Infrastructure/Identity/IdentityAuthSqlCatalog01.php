<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityAuthSqlCatalog01
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.auth01.onboarding_tip_dismissed.01' => (
                "SELECT id FROM pi_user_onboarding_tips WHERE user_id=? AND tip_key=? LIMIT 1"
            ),
            'identity.auth01.onboarding_tip_dismiss.01' => (
                "INSERT INTO pi_user_onboarding_tips (user_id,tip_key,dismissed_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE dismissed_at=VALUES(dismissed_at)"
            ),
            'identity.auth01.login_last_credential_remember.01' => (
                "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?)
                                 ON DUPLICATE KEY UPDATE
                                   meta_value=IF(meta_value<>VALUES(meta_value),VALUES(meta_value),meta_value)"
            ),
            'identity.auth01.login_last_credential_from_meta.01' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1"
            ),
            'identity.auth01.developer_first_login_clear_json_cache.01' => (
                "SELECT is_global_admin FROM pi_users WHERE id=? AND active=1 LIMIT 1"
            ),
            'identity.auth01.developer_first_login_clear_json_cache.02' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1"
            ),
            'identity.auth01.developer_first_login_clear_json_cache.03' => (
                "SELECT GET_LOCK(?,5)"
            ),
            'identity.auth01.developer_first_login_clear_json_cache.04' => (
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1"
            ),
            'identity.auth01.developer_first_login_clear_json_cache.05' => (
                "INSERT INTO pi_meta (meta_key,meta_value,updated_at) VALUES (?, '1', ?) ON DUPLICATE KEY UPDATE meta_value='1',updated_at=VALUES(updated_at)"
            ),
            'identity.auth01.developer_first_login_clear_json_cache.06' => (
                "SELECT RELEASE_LOCK(?)"
            ),
            'identity.auth01.mfa_pending_login_user.01' => (
                "SELECT u.id,u.name,u.email,u.password_hash,u.is_global_admin,u.active,
                                    m.meta_value user_auth_generation
                             FROM pi_users u
                             LEFT JOIN pi_meta m ON m.meta_key=CONCAT('auth_user_',u.id)
                             WHERE u.id=? AND u.active=1 LIMIT 1"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
