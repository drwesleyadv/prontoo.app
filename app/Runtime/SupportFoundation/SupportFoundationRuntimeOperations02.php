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
        }
        if (
            security_rate_limit(security_client_bucket("login_autotest"), 90, 300)
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
        $checks = prontoo_login_selftest_light();
        $auto = false;
        $redirect = "";
        security_clear_legacy_device_cookie();
        if (function_exists("platform_login_loaded_audit")) {
            platform_login_loaded_audit($checks, $auto);
        } elseif (function_exists("audit")) {
            try {
                audit("login_autoteste_leve", "login", null, [
                    "ok" => !empty($checks["ok"]) ? 1 : 0,
                    "maintenance_deferred" => 1,
                    "audit_body" =>
                        "Autoteste leve do login concluído; diagnósticos e manutenção pesada ficam para depois da senha correta.",
                ]);
            } catch (Throwable $e) {
                error_log("[Prontoo light login audit] " . $e->getMessage());
            }
        }
        echo json_encode(
            [
                "ok" => !empty($checks["ok"]),
                "auto_login" => $auto,
                "redirect" => $redirect,
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
            only_digits($identity) .
            "|" .
            mb_strtolower(trim($name), "UTF-8") .
            "|" .
            mb_trim((string) $date);
        return hash_hmac("sha256", $base, secret_key());
    
    }

    public static function person_signature_sync(int $personId, bool $verify = false): void
    
    {
    
        if ($personId <= 0) {
            return;
        }
        try {
            $person = one(
                "SELECT id,full_name,cpf,birth_date,assinatura FROM pi_persons WHERE id=?",
                [$personId],
            );
            if (!$person) {
                if ($verify) {
                    throw new RuntimeException("Pessoa não encontrada para validar a identidade.");
                }
                return;
            }
            $expected = person_signature_value(
                (string) ($person["cpf"] ?? ""),
                (string) ($person["full_name"] ?? ""),
                (string) ($person["birth_date"] ?? ""),
            );
            $current = mb_trim((string) ($person["assinatura"] ?? ""));
            if ($current === "" || !hash_equals($expected, $current)) {
                q(
                    "UPDATE pi_persons SET assinatura=?, updated_at=COALESCE(updated_at,NOW()) WHERE id=?",
                    [$expected, $personId],
                );
            }
            if ($verify) {
                $stored = mb_trim((string) val(
                    "SELECT assinatura FROM pi_persons WHERE id=?",
                    [$personId],
                ));
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
    
        person_signature_sync($personId, false);
    
    }

    public static function person_signature_refresh_verified(int $personId): void
    
    {
    
        person_signature_sync($personId, true);
    
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
                ? (one("SELECT cpf,birth_date FROM pi_persons WHERE id=?", [
                    $personId,
                ]) ?:
                [])
                : [];
        $currentCpf = only_digits((string) ($current["cpf"] ?? ""));
        $currentBirthRaw = mb_trim((string) ($current["birth_date"] ?? ""));
        $currentBirth = app_date_input_from_storage($currentBirthRaw);
        $newCpf = only_digits((string) ($cpfInput ?? ""));
        $newBirth = app_date_input_from_storage(mb_trim((string) ($birthInput ?? "")));
        if ($currentCpf !== "") {
            if ($newCpf !== "" && $newCpf !== $currentCpf) {
                throw new RuntimeException(
                    "CPF é dado de identidade imutável e não pode ser alterado.",
                );
            }
            $newCpf = $currentCpf;
        } else {
            if ($newCpf !== "" && !valid_cpf($newCpf)) {
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
            if ($newBirth !== "" && !valid_birth_date($newBirth)) {
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
        string $sql,
        array $p = [],
        int $ttl = PRONTOO_DASHBOARD_COUNTER_TTL,
    ): int 
    {
    
        try {
            return (int) cached_val("counter_" . $counter, max(0, $ttl), $sql, $p);
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
                $value = val("SELECT meta_value FROM pi_meta WHERE meta_key=?", [$key]);
                return $value === null ? $default : $value;
            } catch (Throwable $e) {
                error_log("[Prontoo meta_get] " . $e->getMessage());
                return $default;
            }
        };
        if (function_exists("server_json_cache_remember")) {
            return server_json_cache_remember(
                "meta",
                server_json_cache_safe_key("meta", [$key]),
                meta_cache_ttl($key),
                $loader,
                ["meta:" . $key],
            );
        }
        return $loader();
    
    }

    public static function meta_set(string $key, mixed $value): void
    
    {
    
        q(
            "INSERT INTO pi_meta (meta_key,meta_value) VALUES (?,?) ON DUPLICATE KEY UPDATE meta_value=VALUES(meta_value), updated_at=NOW()",
            [$key, (string) $value],
        );
        if (function_exists("server_json_cache_clear_categories")) {
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
            server_json_cache_clear_categories($categories);
        }
    
    }

    public static function clinic_signup_blocked(): bool
    
    {
    
        if (!function_exists("has_cfg") || !has_cfg()) {
            return false;
        }
        return (string) meta_get("signup_clinics_blocked", "0") === "1";
    
    }

    public static function log_runtime_error(Throwable $e, int $status = 500): void
    
    {
    
        $message = function_exists("privacy_sanitize_error_message")
            ? privacy_sanitize_error_message($e, 900)
            : mb_substr($e->getMessage(), 0, 900);
        $file = function_exists("privacy_log_file_label")
            ? privacy_log_file_label($e->getFile())
            : basename($e->getFile());
        $dir = storage_path("logs");
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (function_exists("security_storage_deny_file")) {
            security_storage_deny_file($dir);
        }
        $line =
            "[" .
            date("c") .
            "] " .
            route() .
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
        if (!has_cfg() || db_temp_space_error($e)) {
            return;
        }
        try {
            $hash = hash(
                "sha256",
                route() .
                    "|" .
                    $status .
                    "|" .
                    $message .
                    "|" .
                    $file .
                    "|" .
                    $e->getLine(),
            );
            $throttle = cache_get("err_" . $hash, 60);
            if ($throttle) {
                return;
            }
            cache_set("err_" . $hash, 1);
            q(
                "INSERT INTO pi_error_events (route,method,http_status,message,file,line,user_id,clinic_id,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())",
                [
                    route(),
                    (string) ($_SERVER["REQUEST_METHOD"] ?? "GET"),
                    $status,
                    $message,
                    mb_substr($file, 0, 255),
                    $e->getLine(),
                    $_SESSION["uid"] ?? null,
                    session_clinic_scope_id() ?: null,
                ],
            );
        } catch (Throwable $ignored) {
            error_log(
                "[Prontoo error_event] " .
                    (function_exists("privacy_sanitize_error_message")
                        ? privacy_sanitize_error_message($ignored, 240)
                        : $ignored->getMessage()),
            );
        }
    
    }

    public static function person_common_profile_schema_ready(): void
    
    {
    
        ensure_runtime_schema_minimum();
    
    }

    public static function person_common_profile_from_array(
        array $data,
        string $prefix = "",
    ): array 
    {
    
        $k = function (string $name) use ($data, $prefix) {
    
            return mb_trim((string) ($data[$prefix . $name] ?? ""));
        };
        $doc = only_digits($k("legal_document"));
        $type = $k("legal_type");
        if ($type === "" && $doc !== "") {
            $type = strlen($doc) === 14 ? "cnpj" : "cpf";
        }
        if (!in_array($type, ["cpf", "cnpj"], true)) {
            $type = null;
        }
        $email = $k("email");
        $phone = phone_br($k("phone"));
        $uf = strtoupper($k("address_state"));
        $city = $k("address_city");
        $cityIbge = (int) ($data[$prefix . "address_city_ibge"] ?? 0);
        return [
            "legal_type" => $type,
            "legal_document" => $doc !== "" ? $doc : null,
            "phone" => $phone !== "" ? $phone : null,
            "email" => $email !== "" ? $email : null,
            "address_zip" => only_digits($k("address_zip")) ?: null,
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
        person_common_profile_schema_ready();
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
            q(
                "UPDATE pi_persons SET " . implode(",", $sets) . " WHERE id=?",
                $vals,
            );
        } catch (Throwable $e) {
            error_log("[Prontoo pessoas perfil comum update] " . $e->getMessage());
        }
    
    }
}
