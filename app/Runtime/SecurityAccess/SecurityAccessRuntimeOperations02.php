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
        $secret = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_secret_decrypt((string) ($record["secret"] ?? ""));
        $counter = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_matching_counter(
            $secret,
            $code,
            (int) ($record["last_counter"] ?? -1),
        );
        if ($counter !== null) {
            $record["last_counter"] = $counter;
            $record["updated_at"] = time();
            return true;
        }
        $normalized = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_recovery_code_normalize($code);
        if (strlen($normalized) !== 12) {
            return false;
        }
        $candidate = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_recovery_code_hash($normalized);
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
            $locked = (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.mfa_verify_user_code.01', [$lock], []) === 1;
            if (!$locked) {
                return false;
            }
            $record = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_load($uid);
            if (!$record) {
                return false;
            }
            if (!\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_record_verify_code($record, $code)) {
                return false;
            }
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_save($uid, $record);
            return true;
        } finally {
            if ($locked) {
                try {
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.mfa_verify_user_code.02', [$lock], []);
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
            $locked = (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.mfa_recovery_codes_regenerate.01', [$lock], []) === 1;
            if (!$locked) {
                throw new RuntimeException(
                    "Não foi possível proteger a atualização MFA.",
                );
            }
            $record = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_load($uid);
            if (!$record || !\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_record_verify_code($record, $currentCode)) {
                throw new RuntimeException(
                    "O código de autenticação não confere.",
                );
            }
            $codes = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_recovery_codes_generate();
            $record["recovery"] = array_map(
                "mfa_recovery_code_hash",
                $codes,
            );
            $record["updated_at"] = time();
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_save($uid, $record);
            return $codes;
        } finally {
            if ($locked) {
                try {
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.mfa_recovery_codes_regenerate.02', [$lock], []);
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
            $locked = (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.mfa_replace_user.01', [$lock], []) === 1;
            if (!$locked) {
                throw new RuntimeException(
                    "Não foi possível proteger a troca do autenticador.",
                );
            }
            $record = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_load($uid);
            if (!$record || !\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_record_verify_code($record, $currentCode)) {
                throw new RuntimeException(
                    "O código MFA atual não confere.",
                );
            }
            $counter = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_matching_counter($newSecret, $newCode);
            if ($counter === null) {
                throw new RuntimeException(
                    "O código do novo autenticador não confere.",
                );
            }
            $codes = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_recovery_codes_generate();
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_save($uid, [
                "v" => 1,
                "secret" => \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_secret_encrypt($newSecret),
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
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.mfa_replace_user.02', [$lock], []);
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
            $locked = (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.mfa_disable_user.01', [$lock], []) === 1;
            if (!$locked) {
                throw new RuntimeException(
                    "Não foi possível proteger a desativação MFA.",
                );
            }
            if (
                (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.mfa_disable_user.02', [$uid], []) === 1
            ) {
                throw new RuntimeException(
                    "A proteção MFA é obrigatória para Desenvolvedor.",
                );
            }
            $record = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_load($uid);
            if (!$record || !\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::mfa_record_verify_code($record, $currentCode)) {
                throw new RuntimeException(
                    "O código de autenticação não confere.",
                );
            }
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::mfaRecordService()->delete($uid);
        } finally {
            if ($locked) {
                try {
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.mfa_disable_user.03', [$lock], []);
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
    
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return "bootstrap";
        }
        try {
            return (string) (\Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar(
                'identity.security02.auth_generation_current.01',
                ['auth_generation'],
                [],
            ) ?? '0');
        } catch (Throwable $e) {
            error_log("[Prontoo auth generation] " . $e->getMessage());
            return "0";
        }
    
    }

    public static function user_auth_generation_current(int $uid): string
    
    {
        if ($uid <= 0 || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return "0";
        }
        try {
            return (string) (\Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.user_auth_generation_current.01', [\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::user_auth_generation_key($uid)], []) ?? "0");
        } catch (Throwable $e) {
            error_log("[Prontoo user auth generation] " . $e->getMessage());
            return "0";
        }
    
    }

    public static function user_auth_generation_ensure(int $uid): string
    
    {
        $current = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_current($uid);
        if ($current !== "0" && $current !== "") {
            return $current;
        }
        $lock = "prontoo_auth_user_" . max(0, $uid);
        $locked = false;
        try {
            $locked = (int) \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.user_auth_generation_ensure.01', [$lock], []) === 1;
            if (!$locked) {
                throw new RuntimeException(
                    "Não foi possível proteger a geração de autenticação.",
                );
            }
            $current = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_current($uid);
            if ($current === "0" || $current === "") {
                $current = bin2hex(random_bytes(24));
                \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result(
                    'identity.security02.user_auth_generation_ensure.03',
                    [\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::user_auth_generation_key($uid), $current],
                    [],
                );
            }
            return $current;
        } finally {
            if ($locked) {
                try {
                    \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->scalar('identity.security02.user_auth_generation_ensure.02', [$lock], []);
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
        \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.security02.user_auth_generation_rotate.01', [\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::user_auth_generation_key($uid), $generation], []);
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
        $_SESSION["auth_generation"] = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::auth_generation_current();
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
                    : \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_ensure($uid);
        }
    
    }

    public static function security_session_generation_enforce(int $uid): void
    
    {
    
        if ($uid <= 0 || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return;
        }
        $current = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::auth_generation_current();
        $session = (string) ($_SESSION["auth_generation"] ?? "");
        $policy = defined("PRONTOO_AUTH_POLICY_GENERATION")
            ? PRONTOO_AUTH_POLICY_GENERATION
            : "password-session-v1";
        $sessionPolicy = (string) ($_SESSION["auth_policy_generation"] ?? "");
        $userCurrent = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::user_auth_generation_current($uid);
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
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("sessao_obsoleta_encerrada", "seguranca", $uid, [
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
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();
        \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::secure_session_destroy();
        if (!headers_sent()) {
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("Location: " . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("login", ["relogin" => "1"]));
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
    
        setcookie(\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_cookie_name(), "", [
            "expires" => time() - 42000,
            "path" => "/",
            "secure" => \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_secure_cookie(),
            "httponly" => true,
            "samesite" => "Lax",
        ]);
        unset($_COOKIE[\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_cookie_name()]);
    
    }

    public static function device_cookie_clear(): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_cookie_set("", time() - 42000);
        unset($_COOKIE[\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_cookie_name()]);
    
    }

    public static function security_clear_legacy_device_cookie(): void
    
    {
        if (
            isset($_COOKIE["PRONTOO_DEVICE"]) ||
            isset($_SERVER["HTTP_COOKIE"]) &&
                str_contains((string) $_SERVER["HTTP_COOKIE"], "PRONTOO_DEVICE=")
        ) {
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_cookie_clear();
        }
    
    }

    public static function security_retire_persistent_devices_for_user(int $uid): void
    
    {
        if ($uid <= 0 || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();
            return;
        }
        try {
            \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->result('identity.security02.security_retire_persistent_devices_for_user.01', [$uid], []);
        } catch (Throwable $e) {
            error_log(
                "[Prontoo persistent device retirement] " . $e->getMessage(),
            );
        }
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();
    
    }

    public static function device_token_hash(string $token): string
    
    {
    
        return hash_hmac("sha256", $token, \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key());
    
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
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key(),
        );
    
    }

    public static function device_client_hash_from_post(): string
    
    {
    
        $hash = strtolower(mb_trim((string) ($_POST["device_hash"] ?? "")));
        return \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_hash_is_valid($hash) ? $hash : \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_fallback_hash();
    
    }

    public static function device_login_payload_from_post(): array
    
    {
    
        $meta = mb_trim((string) ($_POST["device_meta"] ?? ""));
        if ($meta !== "" && json_decode($meta, true) === null) {
            $meta = "";
        }
        return [
            "hash" => \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::device_client_hash_from_post(),
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
    
        $raw = (string) ($_COOKIE[\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_cookie_name()] ?? "");
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
            !\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::device_hash_is_valid($hash) ||
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
                $r = \Prontoo\Runtime\SecurityAccess\SecurityAccessComposition::dataService()->row('identity.security02.device_session_context_payload.01', [$clinicRoleId], []);
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
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_retire_persistent_devices_for_user($uid);
    
    }

    public static function device_session_update_current_context(
        string $scope,
        ?int $clinicRoleId = null,
    ): void 
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();
    
    }

    public static function device_session_enforce_current(int $uid): void
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();
    
    }

    public static function device_session_auto_login(): bool
    
    {
    
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();
        return false;
    
    }
}
