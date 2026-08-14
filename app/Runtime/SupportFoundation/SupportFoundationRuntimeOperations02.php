<?php
declare(strict_types=1);

namespace Prontoo\Runtime\SupportFoundation;

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

final class SupportFoundationRuntimeOperations02
{
    private function __construct()
    {
    }

    public static function page_login_autotest(): void
    
    {
    
        if (!headers_sent()) {
            header("Content-Type: application/json; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
            header("X-Content-Type-Options: nosniff");
        }
        if ((string) ($_SERVER["REQUEST_METHOD"] ?? "GET") !== "GET") {
            http_response_code(405);
            header("Allow: GET");
            echo json_encode(
                [
                    "ok" => false,
                    "auto_login" => false,
                    "redirect" => "",
                    "version" => PRONTOO_VERSION,
                    "mode" => "login_light",
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            exit();
        }
        if (
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_ip_bucket("login_autotest"), 20, 300) ||
            \Prontoo\Infrastructure\SecurityAccess\SecurityAccessInfrastructureOperations01::security_rate_limit(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::security_client_bucket("login_autotest"), 30, 300)
        ) {
            http_response_code(429);
            echo json_encode(
                [
                    "ok" => false,
                    "auto_login" => false,
                    "redirect" => "",
                    "version" => PRONTOO_VERSION,
                    "mode" => "login_light",
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            exit();
        }
        $checks = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::prontoo_login_selftest_light();
        echo json_encode(
            [
                "ok" => !empty($checks["ok"]),
                "auto_login" => false,
                "redirect" => "",
                "version" => PRONTOO_VERSION,
                "mode" => $checks["mode"] ?? "login_light",
                "maintenance_deferred" => true,
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        exit();
    
    }

    public static function person_signature_value(
        string $identity,
        string $name,
        ?string $date,
    ): string 
    {
    
        $base =
            \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($identity) .
            "|" .
            mb_strtolower(trim($name), "UTF-8") .
            "|" .
            mb_trim((string) $date);
        return hash_hmac("sha256", $base, \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations03::secret_key());
    
    }

    public static function person_signature_sync(int $personId, bool $verify = false): void
    
    {
    
        if ($personId <= 0) {
            return;
        }
        try {
            $person = \Prontoo\Runtime\Operational\OperationalComposition::platform()->row('operational.support_foundation.02.person_signature_sync.01', [$personId], []);
            if (!$person) {
                if ($verify) {
                    throw new RuntimeException("Pessoa não encontrada para validar a identidade.");
                }
                return;
            }
            $expected = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_value(
                (string) ($person["cpf"] ?? ""),
                (string) ($person["full_name"] ?? ""),
                (string) ($person["birth_date"] ?? ""),
            );
            $current = mb_trim((string) ($person["assinatura"] ?? ""));
            if ($current === "" || !hash_equals($expected, $current)) {
                \Prontoo\Runtime\Operational\OperationalComposition::platform()->result('operational.support_foundation.02.person_signature_sync.02', [$expected, $personId], []);
            }
            if ($verify) {
                $stored = mb_trim((string) \Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.02.person_signature_sync.03', [$personId], []));
                if ($stored === "" || !hash_equals($expected, $stored)) {
                    throw new RuntimeException(
                        "A assinatura de identidade da pessoa não pôde ser atualizada.",
                    );
                }
            }
        } catch (Throwable $e) {
            if ($verify) {
                throw $e;
            }
            error_log("[Prontoo person signature] " . $e->getMessage());
        }
    
    }

    public static function person_signature_refresh(int $personId): void
    
    {
    
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_sync($personId, false);
    
    }

    public static function person_signature_refresh_verified(int $personId): void
    
    {
    
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_signature_sync($personId, true);
    
    }

    public static function person_identity_immutable_values(
        int $personId,
        ?string $cpfInput = null,
        ?string $birthInput = null,
        bool $requireMissing = false,
    ): array 
    {
    
        $current =
            $personId > 0
                ? (\Prontoo\Runtime\Operational\OperationalComposition::platform()->row('operational.support_foundation.02.person_identity_immutable_values.01', [
                    $personId,
                ], []) ?:
                [])
                : [];
        $currentCpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($current["cpf"] ?? ""));
        $currentBirthRaw = mb_trim((string) ($current["birth_date"] ?? ""));
        $currentBirth = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($currentBirthRaw);
        $newCpf = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits((string) ($cpfInput ?? ""));
        $newBirth = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage(mb_trim((string) ($birthInput ?? "")));
        if ($currentCpf !== "") {
            if ($newCpf !== "" && $newCpf !== $currentCpf) {
                throw new RuntimeException(
                    "CPF é dado de identidade imutável e não pode ser alterado.",
                );
            }
            $newCpf = $currentCpf;
        } else {
            if ($newCpf !== "" && !\Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations01::valid_cpf($newCpf)) {
                throw new RuntimeException("Este CPF não existe.");
            }
            if ($requireMissing && $newCpf === "") {
                throw new RuntimeException("Informe um CPF válido.");
            }
        }
        if ($currentBirth !== "") {
            if ($newBirth !== "" && $newBirth !== $currentBirth) {
                throw new RuntimeException(
                    "Nascimento é dado de identidade imutável e não pode ser alterado.",
                );
            }
            $newBirth = $currentBirthRaw !== "" ? $currentBirthRaw : $currentBirth;
        } else {
            if ($newBirth !== "" && !\Prontoo\Presentation\AuthOnboarding\AuthOnboardingPresentationOperations01::valid_birth_date($newBirth)) {
                throw new RuntimeException("Nascimento inválido.");
            }
            if ($requireMissing && $newBirth === "") {
                throw new RuntimeException("Nascimento inválido.");
            }
        }
        return ["cpf" => $newCpf, "birth_date" => $newBirth];
    
    }

    public static function count_recent_or_counter(
        string $counter,
        string $query,
        array $p = [],
        int $ttl = PRONTOO_DASHBOARD_COUNTER_TTL,
        array $context = [],
    ): int 
    {
    
        try {
            return (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::cached_val(
                "counter_" . $counter,
                max(0, $ttl),
                $query,
                $p,
                $context,
            );
        } catch (Throwable $e) {
            error_log(
                "[Prontoo realtime counter] " . $counter . " " . $e->getMessage(),
            );
            return 0;
        }
    
    }

    public static function meta_get(string $key, mixed $default = null): mixed
    
    {
    
        $loader = function () use ($key, $default): mixed {
    
            try {
                $value = \Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.02.meta_get.01', [$key], []);
                return $value === null ? $default : $value;
            } catch (Throwable $e) {
                error_log("[Prontoo meta_get] " . $e->getMessage());
                return $default;
            }
        };
        if (is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember'])) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "meta",
                \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_safe_key("meta", [$key]),
                \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::meta_cache_ttl($key),
                $loader,
                ["meta:" . $key],
            );
        }
        return $loader();
    
    }

    public static function meta_set(string $key, mixed $value): void
    
    {
    
        \Prontoo\Runtime\Operational\OperationalComposition::platform()->result('operational.support_foundation.02.meta_set.01', [$key, (string) $value], []);
        if (is_callable([\Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::class, 'server_json_cache_clear_categories'])) {
            $categories = ["meta"];
            if (
                $key === "auth_generation" ||
                str_starts_with($key, "auth_user_") ||
                str_starts_with($key, "mfa_user_")
            ) {
                $categories[] = "context";
            }
            if (str_starts_with($key, "global_admin_timezone_") || $key === "maintenance") {
                $categories[] = "context";
                $categories[] = "clinic";
            }
            \Prontoo\Infrastructure\ServerJsonCache\ServerJsonCacheInfrastructureOperations01::server_json_cache_clear_categories($categories);
        }
    
    }

    public static function clinic_signup_blocked(): bool
    
    {
    
        if (!is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'has_cfg']) || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            return false;
        }
        return (string) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get("signup_clinics_blocked", "0") === "1";
    
    }

    public static function log_runtime_error(Throwable $e, int $status = 500): void
    
    {
    
        $message = \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($e, 900);
        $file = \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::privacy_log_file_label($e->getFile());
        $dir = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("logs");
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
        $line =
            "[" .
            date("c") .
            "] " .
            \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route() .
            " " .
            $status .
            " " .
            $message .
            " @ " .
            $file .
            ":" .
            $e->getLine() .
            "
    ";
        @file_put_contents($dir . "/runtime.log", $line, FILE_APPEND | LOCK_EX);
        @chmod($dir . "/runtime.log", 0640);
        if (!\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg() || \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::db_temp_space_error($e)) {
            return;
        }
        try {
            $hash = hash(
                "sha256",
                \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route() .
                    "|" .
                    $status .
                    "|" .
                    $message .
                    "|" .
                    $file .
                    "|" .
                    $e->getLine(),
            );
            $throttle = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cache_get("err_" . $hash, 60);
            if ($throttle) {
                return;
            }
            \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cache_set("err_" . $hash, 1);
            \Prontoo\Runtime\Operational\OperationalComposition::platform()->result('operational.support_foundation.02.log_runtime_error.01', [
                    \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::route(),
                    (string) ($_SERVER["REQUEST_METHOD"] ?? "GET"),
                    $status,
                    $message,
                    mb_substr($file, 0, 255),
                    $e->getLine(),
                    $_SESSION["uid"] ?? null,
                    \Prontoo\Runtime\Tenant\SessionTenantAccess::clinicId() ?: null,
                ], []);
        } catch (Throwable $ignored) {
            error_log(
                "[Prontoo error_event] " .
                    \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($ignored, 240),
            );
        }
    
    }

    public static function person_common_profile_schema_ready(): void
    
    {
    
        \Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::ensure_runtime_schema_minimum();
    }

    public static function person_common_profile_from_array(
        array $data,
        string $prefix = "",
    ): array 
    {
    
        $k = function (string $name) use ($data, $prefix) {
    
            return mb_trim((string) ($data[$prefix . $name] ?? ""));
        };
        $doc = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($k("legal_document"));
        $type = $k("legal_type");
        if ($type === "" && $doc !== "") {
            $type = strlen($doc) === 14 ? "cnpj" : "cpf";
        }
        if (!in_array($type, ["cpf", "cnpj"], true)) {
            $type = null;
        }
        $email = $k("email");
        $phone = \Prontoo\Runtime\AuthOnboarding\AuthOnboardingRuntimeOperations05::phone_br($k("phone"));
        $uf = strtoupper($k("address_state"));
        $city = $k("address_city");
        $cityIbge = (int) ($data[$prefix . "address_city_ibge"] ?? 0);
        return [
            "legal_type" => $type,
            "legal_document" => $doc !== "" ? $doc : null,
            "phone" => $phone !== "" ? $phone : null,
            "email" => $email !== "" ? $email : null,
            "address_zip" => \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::only_digits($k("address_zip")) ?: null,
            "address" => $k("address") ?: null,
            "address_number" => $k("address_number") ?: null,
            "address_neighborhood" => $k("address_neighborhood") ?: null,
            "address_complement" => $k("address_complement") ?: null,
            "address_state" => $uf ?: null,
            "address_city" => $city ?: null,
            "address_city_ibge" => $cityIbge > 0 ? $cityIbge : null,
        ];
    }

    public static function person_common_profile_update(int $personId, array $profile): void
    
    {
    
        if ($personId <= 0) {
            return;
        }
        \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::person_common_profile_schema_ready();
        $allowed = [
            "legal_type",
            "legal_document",
            "phone",
            "email",
            "address_zip",
            "address",
            "address_number",
            "address_neighborhood",
            "address_complement",
            "address_state",
            "address_city",
            "address_city_ibge",
        ];
        $sets = [];
        $vals = [];
        foreach ($allowed as $col) {
            if (!array_key_exists($col, $profile)) {
                continue;
            }
            $val = $profile[$col];
            if (
                $col === "email" &&
                $val !== null &&
                $val !== "" &&
                !filter_var((string) $val, FILTER_VALIDATE_EMAIL)
            ) {
                $val = null;
            }
            $sets[] = $col . "=?";
            $vals[] = $val !== "" ? $val : null;
        }
        if (!$sets) {
            return;
        }
        $sets[] = "updated_at=NOW()";
        $vals[] = $personId;
        try {
            \Prontoo\Runtime\Operational\OperationalComposition::platform()->result('operational.support_foundation.02.person_common_profile_update.01', $vals, ['sets' => $sets]);
        } catch (Throwable $e) {
            error_log("[Prontoo pessoas perfil comum update] " . $e->getMessage());
        }
    }
}
