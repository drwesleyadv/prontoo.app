<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SecurityAccess;

use \Closure;
use \DateInterval;
use \DateTime;
use \DateTimeImmutable;
use \DateTimeInterface;
use \DateTimeZone;
use \Exception;
use \GdImage;
use \InvalidArgumentException;
use \JsonException;
use \LogicException;
use \PDO;
use \PDOException;
use \ProntooHttpError;
use \RuntimeException;
use \Throwable;

final class SecurityAccessRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function mfa_record_verify_code(array &$record, string $code): bool
    
    {
        $secret = mfa_secret_decrypt((string) ($record["secret"] ?? ""));
        $counter = mfa_totp_matching_counter(
            $secret,
            $code,
            (int) ($record["last_counter"] ?? -1),
        );
        if ($counter !== null) {
            $record["last_counter"] = $counter;
            $record["updated_at"] = time();
            return true;
        }
        $normalized = mfa_recovery_code_normalize($code);
        if (strlen($normalized) !== 12) {
            return false;
        }
        $candidate = mfa_recovery_code_hash($normalized);
        foreach ((array) ($record["recovery"] ?? []) as $index => $hash) {
            if (hash_equals((string) $hash, $candidate)) {
                unset($record["recovery"][$index]);
                $record["recovery"] = array_values($record["recovery"]);
                $record["updated_at"] = time();
                return true;
            }
        }
        return false;
    
    }

    public static function mfa_verify_user_code(int $uid, string $code): bool
    
    {
        $lock = "prontoo_mfa_user_" . max(0, $uid);
        $locked = false;
        try {
            $locked = (int) val("SELECT GET_LOCK(?,5)", [$lock]) === 1;
            if (!$locked) {
                return false;
            }
            $record = mfa_record_load($uid);
            if (!$record) {
                return false;
            }
            if (!mfa_record_verify_code($record, $code)) {
                return false;
            }
            mfa_record_save($uid, $record);
            return true;
        } finally {
            if ($locked) {
                try {
                    val("SELECT RELEASE_LOCK(?)", [$lock]);
                } catch (Throwable $e) {
                    error_log("[Prontoo MFA unlock] " . $e->getMessage());
                }
            }
        }
    
    }

    public static function mfa_recovery_codes_regenerate(int $uid, string $currentCode): array
    
    {
        $lock = "prontoo_mfa_user_" . max(0, $uid);
        $locked = false;
        try {
            $locked = (int) val("SELECT GET_LOCK(?,5)", [$lock]) === 1;
            if (!$locked) {
                throw new RuntimeException(
                    "Não foi possível proteger a atualização MFA.",
                );
            }
            $record = mfa_record_load($uid);
            if (!$record || !mfa_record_verify_code($record, $currentCode)) {
                throw new RuntimeException(
                    "O código de autenticação não confere.",
                );
            }
            $codes = mfa_recovery_codes_generate();
            $record["recovery"] = array_map(
                "mfa_recovery_code_hash",
                $codes,
            );
            $record["updated_at"] = time();
            mfa_record_save($uid, $record);
            return $codes;
        } finally {
            if ($locked) {
                try {
                    val("SELECT RELEASE_LOCK(?)", [$lock]);
                } catch (Throwable $e) {
                    error_log(
                        "[Prontoo MFA recovery unlock] " . $e->getMessage(),
                    );
                }
            }
        }
    
    }

    public static function mfa_replace_user(
        int $uid,
        string $currentCode,
        string $newSecret,
        string $newCode,
    ): array 
    {
        $lock = "prontoo_mfa_user_" . max(0, $uid);
        $locked = false;
        try {
            $locked = (int) val("SELECT GET_LOCK(?,5)", [$lock]) === 1;
            if (!$locked) {
                throw new RuntimeException(
                    "Não foi possível proteger a troca do autenticador.",
                );
            }
            $record = mfa_record_load($uid);
            if (!$record || !mfa_record_verify_code($record, $currentCode)) {
                throw new RuntimeException(
                    "O código MFA atual não confere.",
                );
            }
            $counter = mfa_totp_matching_counter($newSecret, $newCode);
            if ($counter === null) {
                throw new RuntimeException(
                    "O código do novo autenticador não confere.",
                );
            }
            $codes = mfa_recovery_codes_generate();
            mfa_record_save($uid, [
                "v" => 1,
                "secret" => mfa_secret_encrypt($newSecret),
                "recovery" => array_map("mfa_recovery_code_hash", $codes),
                "last_counter" => $counter,
                "enrolled_at" => (int) ($record["enrolled_at"] ?? time()),
                "replaced_at" => time(),
                "updated_at" => time(),
            ]);
            return $codes;
        } finally {
            if ($locked) {
                try {
                    val("SELECT RELEASE_LOCK(?)", [$lock]);
                } catch (Throwable $e) {
                    error_log(
                        "[Prontoo MFA replace unlock] " . $e->getMessage(),
                    );
                }
            }
        }
    
    }

    public static function mfa_disable_user(int $uid, string $currentCode): void
    
    {
        $lock = "prontoo_mfa_user_" . max(0, $uid);
        $locked = false;
        try {
            $locked = (int) val("SELECT GET_LOCK(?,5)", [$lock]) === 1;
            if (!$locked) {
                throw new RuntimeException(
                    "Não foi possível proteger a desativação MFA.",
                );
            }
            if (
                (int) val(
                    "SELECT is_global_admin FROM pi_users WHERE id=? LIMIT 1",
                    [$uid],
                ) === 1
            ) {
                throw new RuntimeException(
                    "A proteção MFA é obrigatória para Desenvolvedor.",
                );
            }
            $record = mfa_record_load($uid);
            if (!$record || !mfa_record_verify_code($record, $currentCode)) {
                throw new RuntimeException(
                    "O código de autenticação não confere.",
                );
            }
            q("DELETE FROM pi_meta WHERE meta_key=?", [mfa_meta_key($uid)]);
        } finally {
            if ($locked) {
                try {
                    val("SELECT RELEASE_LOCK(?)", [$lock]);
                } catch (Throwable $e) {
                    error_log(
                        "[Prontoo MFA disable unlock] " . $e->getMessage(),
                    );
                }
            }
        }
    
    }

    public static function auth_generation_current(): string
    
    {
    
        if (!has_cfg()) {
            return "bootstrap";
        }
        try {
            return (string) meta_get("auth_generation", "0");
        } catch (Throwable $e) {
            error_log("[Prontoo auth generation] " . $e->getMessage());
            return "0";
        }
    
    }

    public static function user_auth_generation_current(int $uid): string
    
    {
        if ($uid <= 0 || !has_cfg()) {
            return "0";
        }
        try {
            return (string) (val(
                "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
                [user_auth_generation_key($uid)],
            ) ?? "0");
        } catch (Throwable $e) {
            error_log("[Prontoo user auth generation] " . $e->getMessage());
            return "0";
        }
    
    }

    public static function user_auth_generation_ensure(int $uid): string
    
    {
        $current = user_auth_generation_current($uid);
        if ($current !== "0" && $current !== "") {
            return $current;
        }
        $lock = "prontoo_auth_user_" . max(0, $uid);
        $locked = false;
        try {
            $locked = (int) val("SELECT GET_LOCK(?,5)", [$lock]) === 1;
            if (!$locked) {
                throw new RuntimeException(
                    "Não foi possível proteger a geração de autenticação.",
                );
            }
            $current = user_auth_generation_current($uid);
            if ($current === "0" || $current === "") {
                $current = bin2hex(random_bytes(24));
                meta_set(user_auth_generation_key($uid), $current);
            }
            return $current;
        } finally {
            if ($locked) {
                try {
                    val("SELECT RELEASE_LOCK(?)", [$lock]);
                } catch (Throwable $e) {
                    error_log(
                        "[Prontoo user auth generation unlock] " .
                            $e->getMessage(),
                    );
                }
            }
        }
    
    }

    public static function user_auth_generation_rotate(int $uid): string
    
    {
        if ($uid <= 0) {
            throw new RuntimeException("Usuário inválido para revogação de sessão.");
        }
        $generation = bin2hex(random_bytes(24));
        q(
            "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value)",
            [user_auth_generation_key($uid), $generation],
        );
        return $generation;
    
    }

    public static function session_harden_after_login(
        int $uid = 0,
        ?string $verifiedUserGeneration = null,
    ): void
    
    {
    
        $mfaVerified = !empty($_SESSION["mfa_verified_at"]);
        $privilegedVerified = !empty($_SESSION["privileged_auth_at"]);
        session_regenerate_id(true);
        $_SESSION["csrf"] = bin2hex(random_bytes(32));
        $now = time();
        $_SESSION["rot"] = $now;
        $_SESSION["born"] = $now;
        $_SESSION["last_activity"] = $now;
        if ($mfaVerified) {
            $_SESSION["mfa_verified_at"] = $now;
        }
        if ($privilegedVerified) {
            $_SESSION["privileged_auth_at"] = $now;
        }
        $_SESSION["auth_generation"] = auth_generation_current();
        $_SESSION["auth_policy_generation"] = defined(
            "PRONTOO_AUTH_POLICY_GENERATION",
        )
            ? PRONTOO_AUTH_POLICY_GENERATION
            : "password-session-v1";
        if ($uid > 0) {
            $verifiedUserGeneration = mb_trim((string) $verifiedUserGeneration);
            $_SESSION["user_auth_generation"] =
                $verifiedUserGeneration !== "" && $verifiedUserGeneration !== "0"
                    ? $verifiedUserGeneration
                    : user_auth_generation_ensure($uid);
        }
    
    }

    public static function security_session_generation_enforce(int $uid): void
    
    {
    
        if ($uid <= 0 || !has_cfg()) {
            return;
        }
        $current = auth_generation_current();
        $session = (string) ($_SESSION["auth_generation"] ?? "");
        $policy = defined("PRONTOO_AUTH_POLICY_GENERATION")
            ? PRONTOO_AUTH_POLICY_GENERATION
            : "password-session-v1";
        $sessionPolicy = (string) ($_SESSION["auth_policy_generation"] ?? "");
        $userCurrent = user_auth_generation_current($uid);
        $userSession = (string) ($_SESSION["user_auth_generation"] ?? "");
        if (
            $sessionPolicy === $policy &&
            ($current === "0" || $session === $current) &&
            $userCurrent !== "0" &&
            hash_equals($userCurrent, $userSession)
        ) {
            return;
        }
        try {
            audit("sessao_obsoleta_encerrada", "seguranca", $uid, [
                "clinic_id" => (int) ($_SESSION["clinic_id"] ?? 0) ?: null,
                "role_code" => (string) ($_SESSION["role_code"] ?? ""),
                "audit_body" =>
                    "Sessão encerrada no primeiro uso após divergência da geração canônica de autenticação do usuário.",
            ], [
                "skip_runtime_context" => true,
                "skip_context_enrichment" => true,
            ]);
        } catch (Throwable $e) {
            error_log("[Prontoo auth generation audit] " . $e->getMessage());
        }
        security_clear_legacy_device_cookie();
        secure_session_destroy();
        if (!headers_sent()) {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Location: " . href("login", ["relogin" => "1"]));
        }
        exit();
    
    }

    public static function device_secure_cookie(): bool
    
    {
    
        return function_exists("security_https_active")
            ? \Prontoo\Runtime\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_https_active()
            : (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
                (string) ($_SERVER["SERVER_PORT"] ?? "") === "443";
    
    }

    public static function device_cookie_set(string $value, int $expires): void
    
    {
    
        if (headers_sent()) {
            return;
        }
    
        setcookie(device_cookie_name(), "", [
            "expires" => time() - 42000,
            "path" => "/",
            "secure" => device_secure_cookie(),
            "httponly" => true,
            "samesite" => "Lax",
        ]);
        unset($_COOKIE[device_cookie_name()]);
    
    }

    public static function device_cookie_clear(): void
    
    {
    
        device_cookie_set("", time() - 42000);
        unset($_COOKIE[device_cookie_name()]);
    
    }

    public static function security_clear_legacy_device_cookie(): void
    
    {
        if (
            isset($_COOKIE["PRONTOO_DEVICE"]) ||
            isset($_SERVER["HTTP_COOKIE"]) &&
                str_contains((string) $_SERVER["HTTP_COOKIE"], "PRONTOO_DEVICE=")
        ) {
            device_cookie_clear();
        }
    
    }

    public static function security_retire_persistent_devices_for_user(int $uid): void
    
    {
        if ($uid <= 0 || !has_cfg()) {
            security_clear_legacy_device_cookie();
            return;
        }
        try {
            q(
                "UPDATE pi_user_devices SET revoked_at=COALESCE(revoked_at,NOW()), logout_at=COALESCE(logout_at,NOW()), token_hash=SHA2(CONCAT(token_hash,':retired:',id),256), updated_at=NOW() WHERE user_id=? AND (revoked_at IS NULL OR logout_at IS NULL)",
                [$uid],
            );
        } catch (Throwable $e) {
            error_log(
                "[Prontoo persistent device retirement] " . $e->getMessage(),
            );
        }
        security_clear_legacy_device_cookie();
    
    }

    public static function device_token_hash(string $token): string
    
    {
    
        return hash_hmac("sha256", $token, secret_key());
    
    }

    public static function device_fallback_hash(): string
    
    {
    
        return hash_hmac(
            "sha256",
            ($_SERVER["HTTP_USER_AGENT"] ?? "") .
                "|" .
                ($_SERVER["HTTP_ACCEPT_LANGUAGE"] ?? "") .
                "|" .
                ($_SERVER["REMOTE_ADDR"] ?? "") .
                "|prontoo-device",
            secret_key(),
        );
    
    }

    public static function device_client_hash_from_post(): string
    
    {
    
        $hash = strtolower(mb_trim((string) ($_POST["device_hash"] ?? "")));
        return device_hash_is_valid($hash) ? $hash : device_fallback_hash();
    
    }

    public static function device_login_payload_from_post(): array
    
    {
    
        $meta = mb_trim((string) ($_POST["device_meta"] ?? ""));
        if ($meta !== "" && json_decode($meta, true) === null) {
            $meta = "";
        }
        return [
            "hash" => device_client_hash_from_post(),
            "label" => mb_substr(
                mb_trim((string) ($_POST["device_label"] ?? "")),
                0,
                160,
            ),
            "platform" => mb_substr(
                mb_trim((string) ($_POST["device_platform"] ?? "")),
                0,
                120,
            ),
            "meta" => mb_substr($meta, 0, 1200),
            "ua" => mb_substr((string) ($_SERVER["HTTP_USER_AGENT"] ?? ""), 0, 600),
            "ip" => mb_substr((string) ($_SERVER["REMOTE_ADDR"] ?? ""), 0, 45),
        ];
    
    }

    public static function device_cookie_unpack(): ?array
    
    {
    
        $raw = (string) ($_COOKIE[device_cookie_name()] ?? "");
        if ($raw === "") {
            return null;
        }
        $parts = explode(".", $raw, 3);
        if (count($parts) !== 3) {
            return null;
        }
        [$uid, $hash, $token] = $parts;
        $uid = (int) $uid;
        $hash = strtolower($hash);
        if (
            $uid <= 0 ||
            !device_hash_is_valid($hash) ||
            !preg_match('/^[a-f0-9]{64}$/', $token)
        ) {
            return null;
        }
        return ["uid" => $uid, "hash" => $hash, "token" => $token];
    
    }

    public static function device_session_context_payload(
        string $scope,
        ?int $clinicRoleId = null,
    ): array 
    {
    
        $scope = $scope === "global" ? "global" : "clinic";
        $clinicId = null;
        $roleCode = null;
        if ($scope === "clinic" && $clinicRoleId && $clinicRoleId > 0) {
            try {
                $r = one(
                    "SELECT clinic_id,role_code FROM pi_user_roles WHERE id=? LIMIT 1",
                    [$clinicRoleId],
                );
                if ($r) {
                    $clinicId = (int) $r["clinic_id"];
                    $roleCode = (string) $r["role_code"];
                }
            } catch (Throwable $e) {
                error_log("[Prontoo device context] " . $e->getMessage());
            }
        }
        return [$scope, $clinicRoleId ?: null, $clinicId, $roleCode];
    
    }

    public static function device_session_remember_after_login(
        int $uid,
        string $scope,
        ?int $clinicRoleId = null,
        ?array $payload = null,
    ): void 
    {
    
        security_retire_persistent_devices_for_user($uid);
    
    }

    public static function device_session_update_current_context(
        string $scope,
        ?int $clinicRoleId = null,
    ): void 
    {
    
        security_clear_legacy_device_cookie();
    
    }

    public static function device_session_enforce_current(int $uid): void
    
    {
    
        security_clear_legacy_device_cookie();
    
    }

    public static function device_session_auto_login(): bool
    
    {
    
        security_clear_legacy_device_cookie();
        return false;
    
    }
}
