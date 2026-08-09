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

final class SecurityAccessRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function boot_security(): void
    
    {
    
        if (function_exists("security_disable_runtime_error_display")) {
            \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_disable_runtime_error_display();
        }
        if (
            function_exists("security_storage_deny_file") &&
            is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'storage_path'])
        ) {
            $storageGuardRoot = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache");
            $storageGuardVersion = defined("PRONTOO_VERSION")
                ? PRONTOO_VERSION
                : "runtime";
            $storageGuardMarker =
                $storageGuardRoot .
                "/.security-storage-" .
                hash("sha256", $storageGuardVersion) .
                ".json";
            $storageGuardFiles = [
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path() . "/.htaccess",
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path() . "/index.html",
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache") . "/.htaccess",
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache") . "/index.html",
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("logs") . "/.htaccess",
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("logs") . "/index.html",
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("maestro-deferred") . "/.htaccess",
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("maestro-deferred") . "/index.html",
            ];
            $storageGuardFilesPresent = true;
            foreach ($storageGuardFiles as $storageGuardFile) {
                if (!is_file($storageGuardFile)) {
                    $storageGuardFilesPresent = false;
                    break;
                }
            }
            $storageGuardFresh =
                $storageGuardFilesPresent &&
                is_file($storageGuardMarker) &&
                time() - (int) filemtime($storageGuardMarker) < 3600;
            if (!$storageGuardFresh) {
                \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path());
                \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("cache"));
                \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("logs"));
                \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file(\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("maestro-deferred"));
                if (is_dir($storageGuardRoot)) {
                    @file_put_contents(
                        $storageGuardMarker,
                        json_encode(
                            ["ok" => true, "version" => $storageGuardVersion],
                            JSON_UNESCAPED_SLASHES,
                        ),
                        LOCK_EX,
                    );
                    @chmod($storageGuardMarker, 0640);
                }
            }
        }
        ini_set("session.use_strict_mode", "1");
        ini_set("session.use_only_cookies", "1");
        ini_set("session.cookie_httponly", "1");
        $secure = function_exists("security_https_active")
            ? \Prontoo\Runtime\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_https_active()
            : (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
                (string) ($_SERVER["SERVER_PORT"] ?? "") === "443";
        session_name("PRONTOO");
        session_set_cookie_params([
            "lifetime" => 0,
            "path" => "/",
            "secure" => $secure,
            "httponly" => true,
            "samesite" => "Lax",
        ]);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations02::security_clear_legacy_device_cookie();
        $now = time();
        if (empty($_SESSION["born"])) {
            $_SESSION["born"] = $now;
        }
        $fp = hash("sha256", ($_SERVER["HTTP_USER_AGENT"] ?? "") . "|prontoo");
        if (empty($_SESSION["fp"])) {
            $_SESSION["fp"] = $fp;
        }
        if (!hash_equals((string) $_SESSION["fp"], $fp)) {
            \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::secure_session_destroy();
            header("Location: " . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("login"));
            exit();
        }
        if (!empty($_SESSION["uid"])) {
            $idle = (int) (defined("PRONTOO_SESSION_IDLE_SECONDS")
                ? PRONTOO_SESSION_IDLE_SECONDS
                : 3600);
            $absolute = (int) (defined("PRONTOO_SESSION_ABSOLUTE_SECONDS")
                ? PRONTOO_SESSION_ABSOLUTE_SECONDS
                : 43200);
            $last = (int) ($_SESSION["last_activity"] ?? $now);
            $born = (int) ($_SESSION["born"] ?? $now);
            if (
                ($idle > 0 && $now - $last >= $idle) ||
                ($absolute > 0 && $now - $born > $absolute)
            ) {
                try {
                    if (is_callable([\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::class, 'audit'])) {
                        \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("sessao_expirada", "seguranca", null, [
                            "audit_body" =>
                                "Sessão encerrada automaticamente por tempo de inatividade ou duração máxima.",
                        ]);
                    }
                } catch (Throwable $e) {
                    error_log(
                        "[Prontoo recoverable " .
                            __FUNCTION__ .
                            "] " .
                            $e->getMessage(),
                    );
                }
                \Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::secure_session_destroy();
                header("Location: " . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("login"));
                exit();
            }
        }
        $_SESSION["last_activity"] = $now;
        if (empty($_SESSION["rot"]) || $now - (int) $_SESSION["rot"] > 900) {
            session_regenerate_id(true);
            $_SESSION["rot"] = $now;
        }
    
    }

    public static function headers_secure(bool $public = false): void
    
    {
    
        $img = "'self' data:";
        $style = "'self' 'unsafe-inline' https://fonts.googleapis.com";
        $GLOBALS["csp_nonce"] = bin2hex(random_bytes(16));
        $script = "'self' 'nonce-" . $GLOBALS["csp_nonce"] . "'";
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-Permitted-Cross-Domain-Policies: none");
        header("Cross-Origin-Opener-Policy: same-origin");
        header("Referrer-Policy: same-origin");
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
        $upgrade =
            \Prontoo\Runtime\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_https_active()
                ? "; upgrade-insecure-requests"
                : "";
        $csp = "default-src 'self'; script-src $script; style-src $style; font-src 'self' https://fonts.gstatic.com; img-src $img; connect-src 'self' https://servicodados.ibge.gov.br https://viacep.com.br https://brasilapi.com.br; object-src 'none'; frame-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'$upgrade";
        header("Content-Security-Policy: " . $csp);
        header(
            "Content-Security-Policy-Report-Only: default-src 'self'; script-src $script; style-src 'self' https://fonts.googleapis.com; style-src-attr 'none'; font-src 'self' https://fonts.gstatic.com; img-src $img; connect-src 'self' https://servicodados.ibge.gov.br https://viacep.com.br https://brasilapi.com.br; object-src 'none'; frame-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'$upgrade",
        );
        if (!$public) {
            header(
                "Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0",
            );
            header("Pragma: no-cache");
            header("Expires: 0");
        }
        if (
            function_exists("security_https_active")
                ? \Prontoo\Runtime\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_https_active()
                : (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
                    (string) ($_SERVER["SERVER_PORT"] ?? "") === "443"
        ) {
            header(
                "Strict-Transport-Security: max-age=31536000; includeSubDomains",
            );
        }
    
    }

    public static function posted_identity_document_error(
        array $data,
        string $prefix = "",
    ): ?string 
    {
    
        foreach ($data as $key => $value) {
            $name = $prefix === "" ? (string) $key : $prefix . "." . (string) $key;
            if (is_array($value)) {
                $err = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::posted_identity_document_error($value, $name);
                if ($err !== null) {
                    return $err;
                }
                continue;
            }
            $field = strtolower((string) $key);
            if (str_ends_with($field, "_omitted") || str_contains($field, "omit")) {
                continue;
            }
            $digits = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) $value);
            $isCpfField =
                (bool) preg_match('/(^|_|-)cpf($|_|-)/', $field) ||
                in_array(
                    $field,
                    ["cpf", "admin_cpf", "team_cpf", "guardian_cpf"],
                    true,
                );
            $isDocField =
                in_array(
                    $field,
                    [
                        "legal_document",
                        "doc",
                        "document",
                        "documento",
                        "documento_legal",
                    ],
                    true,
                ) ||
                (str_contains($field, "document") &&
                    !str_contains($field, "proof"));
            if ($isCpfField && !$isDocField) {
                if ($digits !== "" && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($digits)) {
                    return "Informe um CPF válido.";
                }
                continue;
            }
            if ($isDocField) {
                if ($digits === "") {
                    continue;
                }
                if (strlen($digits) === 11 && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($digits)) {
                    return "Informe um CPF válido.";
                }
                if (strlen($digits) === 14 && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cnpj($digits)) {
                    return "Informe um CNPJ válido.";
                }
                if (!in_array(strlen($digits), [11, 14], true)) {
                    return "Informe CPF ou CNPJ válido.";
                }
            }
        }
        return null;
    
    }

    public static function enforce_posted_identity_documents(): void
    
    {
    
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
            return;
        }
        $err = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::posted_identity_document_error($_POST);
        if ($err !== null) {
            http_response_code(400);
            header("Content-Type: text/plain; charset=utf-8");
            exit($err);
        }
    
    }

    public static function guard_request(): void
    
    {
    
        if (is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::class, 'app_enforce_canonical_host'])) {
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_enforce_canonical_host();
        }
        $method = $_SERVER["REQUEST_METHOD"] ?? "GET";
        if (!in_array($method, ["GET", "POST"], true)) {
            http_response_code(405);
            exit("Método não permitido.");
        }
        if ($method === "POST") {
            $routeHint = preg_replace(
                "/[^a-z0-9_\-]/i",
                "",
                (string) ($_GET["r"] ?? ""),
            );
            $maxContent = match ($routeHint) {
                "settings" => 6291456,
                default => 4194304,
            };
            if ((int) ($_SERVER["CONTENT_LENGTH"] ?? 0) > $maxContent) {
                http_response_code(413);
                exit("Requisição maior que o permitido.");
            }
            if (count($_POST, COUNT_RECURSIVE) > 160) {
                http_response_code(400);
                exit("Formulário maior que o permitido.");
            }
            $site = $_SERVER["HTTP_SEC_FETCH_SITE"] ?? "";
            if (
                $site &&
                !in_array($site, ["same-origin", "same-site", "none"], true)
            ) {
                http_response_code(403);
                exit("Origem recusada.");
            }
            $origin = $_SERVER["HTTP_ORIGIN"] ?? "";
            $hostHeader = strtolower((string) ($_SERVER["HTTP_HOST"] ?? ""));
            $originHost = $origin
                ? strtolower((string) parse_url($origin, PHP_URL_HOST))
                : "";
            if (
                $origin &&
                $originHost !== preg_replace('/:\d+$/', "", $hostHeader)
            ) {
                http_response_code(403);
                exit("Origem inválida.");
            }
            if (count($_FILES, COUNT_RECURSIVE) > 40) {
                http_response_code(400);
                exit("Envio maior que o permitido.");
            }
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::enforce_posted_identity_documents();
        }
    
    }

    public static function safe_val(string $sql, array $p = [], mixed $fallback = 0): mixed
    
    {
    
        try {
            return \Prontoo\Core\Architecture\OperationGateway::invoke('val', $sql, $p) ?? $fallback;
        } catch (Throwable $e) {
            error_log("[Prontoo safe_val] " . $e->getMessage());
            return $fallback;
        }
    
    }

    public static function csrf_field(): string
    
    {
    
        return '<input type="hidden" name="csrf" value="' . \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::csrf()) . '">';
    
    }

    public static function check_csrf(): void
    
    {
    
        if (
            ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" &&
            !hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"] ?? "")
        ) {
            \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations04::audit("csrf_bloqueado", "seguranca", null, ["janela" => \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route()]);
            throw new ProntooHttpError(
                403,
                "Sessão expirada ou formulário inválido.",
            );
        }
    
    }

    public static function mfa_crypto_key(): string
    
    {
        return hash_hmac(
            "sha256",
            "prontoo-mfa-secret-v1",
            \Prontoo\Core\Architecture\OperationGateway::invoke('secret_key'),
            true,
        );
    
    }

    public static function mfa_secret_encrypt(string $secret): string
    
    {
        if (!function_exists("openssl_encrypt")) {
            throw new RuntimeException(
                "Criptografia necessária para o MFA não está disponível.",
            );
        }
        $nonce = random_bytes(12);
        $tag = "";
        $ciphertext = openssl_encrypt(
            $secret,
            "aes-256-gcm",
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_crypto_key(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            "prontoo-mfa-v1",
            16,
        );
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException("Não foi possível proteger o segredo MFA.");
        }
        return base64_encode($nonce . $tag . $ciphertext);
    
    }

    public static function mfa_secret_decrypt(string $encrypted): string
    
    {
        if (!function_exists("openssl_decrypt")) {
            throw new RuntimeException(
                "Criptografia necessária para o MFA não está disponível.",
            );
        }
        $payload = base64_decode($encrypted, true);
        if (!is_string($payload) || strlen($payload) < 29) {
            throw new RuntimeException("Cadastro MFA inválido.");
        }
        $secret = openssl_decrypt(
            substr($payload, 28),
            "aes-256-gcm",
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_crypto_key(),
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16),
            "prontoo-mfa-v1",
        );
        if (!is_string($secret) || $secret === "") {
            throw new RuntimeException("Não foi possível validar o cadastro MFA.");
        }
        return $secret;
    
    }

    public static function mfa_record_load(int $uid): ?array
    
    {
        if ($uid <= 0 || !\Prontoo\Core\Architecture\OperationGateway::invoke('has_cfg')) {
            return null;
        }
        $raw = \Prontoo\Core\Architecture\OperationGateway::invoke('val', 
            "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
            [\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_meta_key($uid)],
        );
        if (!is_string($raw) || trim($raw) === "") {
            return null;
        }
        try {
            $record = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Cadastro MFA corrompido.", 0, $e);
        }
        if (
            !is_array($record) ||
            (int) ($record["v"] ?? 0) !== 1 ||
            empty($record["secret"])
        ) {
            throw new RuntimeException("Cadastro MFA incompleto.");
        }
        return $record;
    
    }

    public static function mfa_record_save(int $uid, array $record): void
    
    {
        $encoded = json_encode(
            $record,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_set(\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_meta_key($uid), $encoded);
    
    }

    public static function mfa_enrollment_state(int $uid): string
    
    {
        if ($uid <= 0 || !\Prontoo\Core\Architecture\OperationGateway::invoke('has_cfg')) {
            return "unavailable";
        }
        try {
            $record = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_load($uid);
            if ($record === null) {
                return "inactive";
            }
            $secret = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_secret_decrypt((string) $record["secret"]);
            if (strlen(\Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_base32_decode($secret)) < 16) {
                throw new RuntimeException("Segredo MFA inválido.");
            }
            return "active";
        } catch (Throwable $e) {
            error_log("[Prontoo MFA state] " . $e->getMessage());
            return "unavailable";
        }
    
    }

    public static function mfa_is_enrolled(int $uid): bool
    
    {
        return \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_enrollment_state($uid) === "active";
    
    }

    public static function mfa_recovery_code_hash(string $code): string
    
    {
        return hash_hmac(
            "sha256",
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_recovery_code_normalize($code),
            \Prontoo\Core\Architecture\OperationGateway::invoke('secret_key'),
        );
    
    }

    public static function mfa_enroll_user(
        int $uid,
        string $secret,
        string $firstCode,
    ): array 
    {
        $lock = "prontoo_mfa_user_" . max(0, $uid);
        $locked = false;
        try {
            $locked = (int) \Prontoo\Core\Architecture\OperationGateway::invoke('val', "SELECT GET_LOCK(?,5)", [$lock]) === 1;
            if (!$locked) {
                throw new RuntimeException(
                    "Não foi possível proteger o cadastro MFA.",
                );
            }
            if (\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_load($uid) !== null) {
                throw new RuntimeException("O MFA já está cadastrado.");
            }
            $counter = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_totp_matching_counter($secret, $firstCode);
            if ($counter === null) {
                throw new RuntimeException(
                    "O código do autenticador não confere.",
                );
            }
            $codes = \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::mfa_recovery_codes_generate();
            \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_record_save($uid, [
                "v" => 1,
                "secret" => \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations01::mfa_secret_encrypt($secret),
                "recovery" => array_map("mfa_recovery_code_hash", $codes),
                "last_counter" => $counter,
                "enrolled_at" => time(),
                "updated_at" => time(),
            ]);
            return $codes;
        } finally {
            if ($locked) {
                try {
                    \Prontoo\Core\Architecture\OperationGateway::invoke('val', "SELECT RELEASE_LOCK(?)", [$lock]);
                } catch (Throwable $e) {
                    error_log("[Prontoo MFA enroll unlock] " . $e->getMessage());
                }
            }
        }
    
    }
}
