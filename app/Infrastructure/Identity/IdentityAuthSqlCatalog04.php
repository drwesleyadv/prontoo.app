<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Identity;

use RuntimeException;

final class IdentityAuthSqlCatalog04
{
    private function __construct()
    {
    }

    public static function statement(string $operation, array $context): string
    {
        extract($context, EXTR_SKIP);
        return match ($operation) {
            'identity.auth04.login_lock.01' => (
                "SELECT MAX(GREATEST(0,locked_until-?)) AS wait_seconds FROM pi_login_locks WHERE (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?)"
            ),
            'identity.auth04.login_fail.01' => (
                "INSERT INTO pi_login_locks (subject_hash,ip_hash,fail_count,locked_until,updated_at)
                                 VALUES
                                   (?,?,1,UNIX_TIMESTAMP()+2,NOW()),
                                   (?,?,1,0,NOW()),
                                   (?,?,1,0,NOW())
                                 ON DUPLICATE KEY UPDATE
                                   locked_until=CASE
                                     WHEN subject_hash=? AND ip_hash=? THEN
                                       UNIX_TIMESTAMP()+CAST(
                                         LEAST(900,2*POW(2,LEAST(10,GREATEST(0,fail_count))))
                                         AS UNSIGNED
                                       )
                                     WHEN subject_hash=? AND ip_hash=? THEN
                                       CASE
                                         WHEN fail_count+1>=5 THEN UNIX_TIMESTAMP()+CAST(
                                           LEAST(86400,60*POW(2,LEAST(10,GREATEST(0,fail_count+1-5))))
                                           AS UNSIGNED
                                         )
                                         ELSE COALESCE(locked_until,0)
                                       END
                                     ELSE
                                       CASE
                                         WHEN fail_count+1>=20 THEN UNIX_TIMESTAMP()+CAST(
                                           LEAST(3600,60*POW(2,LEAST(10,GREATEST(0,fail_count+1-20))))
                                           AS UNSIGNED
                                         )
                                         ELSE COALESCE(locked_until,0)
                                       END
                                   END,
                                   fail_count=LEAST(100000,fail_count+1),
                                   updated_at=NOW()"
            ),
            'identity.auth04.login_clear.01' => (
                "DELETE FROM pi_login_locks WHERE (subject_hash=? AND ip_hash=?) OR (subject_hash=? AND ip_hash=?)"
            ),
            'identity.auth04.mark_login_success.01' => (
                "UPDATE pi_users SET failed_login_count=0, locked_until=NULL, last_login_at=NOW() WHERE id=?"
            ),
            'identity.auth04.page_signup.01' => (
                "SELECT id,password_hash,active,email FROM pi_users WHERE person_id=? LIMIT 1"
            ),
            'identity.auth04.page_signup.02' => (
                "UPDATE pi_users SET email=?,updated_at=NOW() WHERE id=?"
            ),
            'identity.auth04.page_signup.03' => (
                "INSERT INTO pi_users (person_id,name,email,password_hash,active,created_at) VALUES (?,?,?,?,1,NOW())"
            ),
            'identity.auth04.page_signup.04' => (
                "INSERT INTO pi_clinics (legal_type,legal_name,legal_document,display_name,phone,responsible_profession,owner_user_id,manager_user_id,accent_color,address_line,address_state,address_city,address_city_ibge,timezone,onboarding_done,onboarding_completed_at,trial_started_at,trial_ends_at,subscription_status,monthly_price_cents,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,'trial',?,?)"
            ),
            'identity.auth04.page_signup.05' => (
                "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active) VALUES (?,?,?,1,1) ON DUPLICATE KEY UPDATE is_owner=1, active=1"
            ),
            'identity.auth04.page_signup.06' => (
                "INSERT INTO pi_user_roles (user_id,clinic_id,role_code,is_owner,active) VALUES (?,?,?,1,1) ON DUPLICATE KEY UPDATE is_owner=1, active=1"
            ),
            'identity.auth04.page_signup.07' => (
                "SELECT id FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND role_code='gerente' AND active=1 LIMIT 1"
            ),
            'identity.auth04.page_signup.08' => (
                "SELECT role_code FROM pi_user_roles WHERE user_id=? AND clinic_id=? AND active=1 AND role_code IN ('gerente','medico')"
            ),
            'identity.auth04.page_signup.09' => (
                "UPDATE pi_clinic_roles SET label=? WHERE clinic_id=? AND role_code='medico'"
            ),
            default => throw new RuntimeException('Operação SQL de identidade desconhecida.'),
        };
    }
}
