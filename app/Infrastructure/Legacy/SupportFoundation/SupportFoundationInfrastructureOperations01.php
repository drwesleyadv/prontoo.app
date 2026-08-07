<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\Legacy\SupportFoundation;

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

final class SupportFoundationInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function app_root(): string
    
    {
    
        return defined("PRONTOO_ROOT") ? PRONTOO_ROOT : dirname((dirname(__DIR__, 3) . '/Support'), 2);
    
    }

    public static function cfg_file(): string
    
    {
    
        $env = mb_trim((string) (getenv("PRONTOO_CONFIG_PATH") ?: ""));
        if ($env !== "" && str_starts_with($env, "/")) {
            return $env;
        }
        return app_root() . "/app/config.php";
    
    }

    public static function has_cfg(): bool
    
    {
    
        return is_file(cfg_file());
    
    }

    public static function now(): string
    
    {
    
        return (string) time();
    
    }

    public static function app_timezone_safe(string $tz): string
    
    {
    
        $tz = trim($tz);
        return in_array($tz, timezone_identifiers_list(), true)
            ? $tz
            : "America/Cuiaba";
    
    }

    public static function app_force_utc_runtime(): void
    
    {
    
        @date_default_timezone_set("UTC");
        if (function_exists("pdo") && has_cfg()) {
            try {
                pdo()->exec("SET time_zone='+00:00'");
            } catch (Throwable $e) {
                error_log("[Prontoo timezone UTC session] " . $e->getMessage());
            }
        }
    
    }

    public static function app_timezone_offset_string(string $tz, ?int $timestamp = null): string
    
    {
    
        $zone = new DateTimeZone(app_timezone_safe($tz));
        $dt = new DateTimeImmutable("@" . ($timestamp ?? time()));
        $offset = $zone->getOffset($dt);
        $sign = $offset < 0 ? "-" : "+";
        $offset = abs($offset);
        return sprintf(
            "%s%02d:%02d",
            $sign,
            intdiv($offset, 3600),
            intdiv($offset % 3600, 60),
        );
    
    }

    public static function app_timezone_offset_minutes(string $tz, ?int $timestamp = null): int
    
    {
    
        $zone = new DateTimeZone(app_timezone_safe($tz));
        $dt = new DateTimeImmutable("@" . ($timestamp ?? time()));
        return (int) floor($zone->getOffset($dt) / 60);
    
    }

    public static function app_apply_request_timezone(string $tz): void
    
    {
    
        $GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] = app_timezone_safe($tz);
        app_force_utc_runtime();
    
    }

    public static function app_now_utc(): DateTimeImmutable
    
    {
    
        return new DateTimeImmutable("now", new DateTimeZone("UTC"));
    
    }

    public static function app_parse_db_utc(null|string|int $value): ?DateTimeImmutable
    
    {
    
        $value = mb_trim((string) ($value ?? ""));
        if ($value === "") {
            return null;
        }
        try {
            if (preg_match('/^-?\d+$/', $value)) {
                return new DateTimeImmutable("@" . (int) $value);
            }
            return new DateTimeImmutable($value, new DateTimeZone("UTC"));
        } catch (Throwable $e) {
            return null;
        }
    
    }

    public static function app_storage_timestamp(
        null|string|int $value,
        bool $endOfDay = false,
    ): int 
    {
    
        $value = mb_trim((string) ($value ?? ""));
        if ($value === "") {
            return 0;
        }
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $value .= $endOfDay ? " 23:59:59" : " 00:00:00";
        }
        $ts = strtotime($value . " UTC");
        if (!$ts) {
            $ts = strtotime($value);
        }
        return $ts ? (int) $ts : 0;
    
    }

    public static function app_date_input_from_storage(null|string|int $value): string
    
    {
    
        $value = mb_trim((string) ($value ?? ""));
        if ($value === "") {
            return "";
        }
        if (preg_match('/^-?\d+$/', $value)) {
            return gmdate("Y-m-d", (int) $value);
        }
        return preg_match("/^\d{4}-\d{2}-\d{2}/", $value)
            ? substr($value, 0, 10)
            : "";
    
    }

    public static function only_digits(string $s): string
    
    {
    
        return preg_replace("/\D+/", "", $s) ?? "";
    
    }

    public static function cpf_br(?string $cpf): string
    
    {
    
        $d = only_digits((string) ($cpf ?? ""));
        return strlen($d) === 11
            ? substr($d, 0, 3) .
                    "." .
                    substr($d, 3, 3) .
                    "." .
                    substr($d, 6, 3) .
                    "-" .
                    substr($d, 9, 2)
            : (string) ($cpf ?? "");
    
    }

    public static function app_config_string(string $key, string $default = ""): string
    
    {
    
        try {
            if (function_exists("cfg") && has_cfg()) {
                $c = cfg();
                if (isset($c[$key]) && is_scalar($c[$key])) {
                    return mb_trim((string) $c[$key]);
                }
            }
        } catch (Throwable $e) {
            error_log("[Prontoo config string] " . $e->getMessage());
        }
        $env = getenv("PRONTOO_" . strtoupper($key));
        return is_string($env) && trim($env) !== "" ? trim($env) : $default;
    
    }

    public static function app_is_production(): bool
    
    {
    
        $defaultEnv = has_cfg() ? "production" : "development";
        $env = strtolower(
            app_config_string(
                "app_env",
                (string) (getenv("APP_ENV") ?:
                getenv("PRONTOO_ENV") ?:
                $defaultEnv),
            ),
        );
        return in_array($env, ["prod", "production", "prd"], true);
    
    }

    public static function app_canonical_host(): string
    
    {
    
        $host = app_config_string(
            "canonical_host",
            defined("PRONTOO_CANONICAL_HOST")
                ? (string) PRONTOO_CANONICAL_HOST
                : "",
        );
        $host = strtolower(
            preg_replace(
                "/[^a-zA-Z0-9.\-]/",
                "",
                preg_replace('/:\d+$/', "", $host),
            ) ?:
            "",
        );
        return $host;
    
    }

    public static function app_debug(): bool
    
    {
    
        if (
            app_is_production() &&
            (string) getenv("PRONTOO_ALLOW_PRODUCTION_DEBUG") !== "1"
        ) {
            return false;
        }
        try {
            $c = has_cfg() ? cfg() : [];
            return !empty($c["debug"]);
        } catch (Throwable $e) {
            error_log("[Prontoo debug cfg] " . $e->getMessage());
            return false;
        }
    
    }

    public static function app_public_error_message(
        Throwable $error,
        string $fallback = "Não foi possível concluir esta ação.",
    ): string 
    {
    
        $message = trim($error->getMessage());
        if ($message === "" || $error instanceof PDOException) {
            return $fallback;
        }
        if (
            preg_match(
                "/(?:SQLSTATE|\b(?:SELECT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\b|\b(?:table|column|constraint|driver|database)\b|\.php\b|stack trace|\/app\/|\\app\\)/i",
                $message,
            )
        ) {
            return $fallback;
        }
        return mb_substr($message, 0, 240);
    
    }

    public static function runtime_self_check(): void
    
    {
    
        \Prontoo\Core\Install\RuntimeContract::assert(app_root(), PRONTOO_VERSION);
    
    }

    public static function cfg(): array
    
    {
    
        static $c = null;
        if ($c !== null) {
            return $c;
        }
        $c = has_cfg() ? require cfg_file() : [];
        return is_array($c) ? $c : [];
    
    }

    public static function storage_path(string $path = ""): string
    
    {
    
        return app_root() . "/ssd" . ($path ? "/" . ltrim($path, "/") : "");
    
    }

    public static function cache_path(string $key): string
    
    {
    
        $dir = storage_path("cache");
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir .
            "/" .
            preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $key) .
            ".json";
    
    }

    public static function cache_get(string $key, int $ttl): mixed
    
    {
    
        $f = cache_path($key);
        if (!is_file($f) || time() - filemtime($f) > $ttl) {
            return null;
        }
        $raw = @file_get_contents($f);
        if ($raw === false) {
            return null;
        }
        $j = json_decode($raw, true);
        return is_array($j) && array_key_exists("v", $j) ? $j["v"] : null;
    
    }

    public static function cache_set(string $key, mixed $value): mixed
    
    {
    
        @file_put_contents(
            cache_path($key),
            json_encode(["t" => time(), "v" => $value], JSON_UNESCAPED_UNICODE),
            LOCK_EX,
        );
        return $value;
    
    }

    public static function cache_remember(string $key, int $ttl, callable $fn): mixed
    
    {
    
        if ($ttl <= 0) {
            try {
                return $fn();
            } catch (Throwable $e) {
                error_log("[Prontoo cache_remember realtime] " . $e->getMessage());
                return null;
            }
        }
        $v = cache_get($key, $ttl);
        if ($v !== null) {
            return $v;
        }
        try {
            return cache_set($key, $fn());
        } catch (Throwable $e) {
            error_log("[Prontoo cache_remember] " . $e->getMessage());
            return null;
        }
    
    }

    public static function bounded_limit(int $n, int $max = PRONTOO_HOT_LIST_LIMIT): int
    
    {
    
        return max(1, min($max, $n));
    
    }

    public static function counter_key(string $name): string
    
    {
    
        return preg_replace("/[^a-z0-9_\-\.]/i", "_", $name) ?: "counter";
    
    }

    public static function prontoo_login_selftest_light(): array
    
    {
    
        $ok = true;
        $checks = ["mode" => "login_light", "version" => PRONTOO_VERSION];
        try {
            $dbOk = (int) pdo()->query("SELECT 1")->fetchColumn() === 1;
            $checks["db"] = $dbOk;
            $checks["database"] = $dbOk;
            $ok = $ok && $dbOk;
        } catch (Throwable $e) {
            $checks["db"] = false;
            $checks["database"] = false;
            $checks["db_error_hash"] = hash("sha256", $e->getMessage());
            $ok = false;
        }
        try {
            $runtimeOk =
                db_table_exists("pi_meta");
            $checks["pi_runtime"] = $runtimeOk;
        } catch (Throwable $e) {
            $checks["pi_runtime"] = false;
        }
        try {
            $storageDir = function_exists("storage_path")
                ? storage_path()
                : app_root();
            $free = @disk_free_space($storageDir);
            $writable = is_dir($storageDir) && is_writable($storageDir);
            $freeOk = $free === false ? true : $free > 20 * 1024 * 1024;
            $checks["storage"] = $writable && $freeOk;
            $checks["storage_free_bytes"] = $free === false ? null : (float) $free;
            $checks["storage_advisory"] = true;
        } catch (Throwable $e) {
            $checks["storage"] = false;
            $ok = false;
        }
        $checks["maintenance_deferred"] = true;
        $checks["ok"] = $ok;
        return $checks;
    
    }

    public static function meta_cache_ttl(string $key): int
    
    {
    
        if (
            $key === "auth_generation" ||
            str_starts_with($key, "auth_user_") ||
            str_starts_with($key, "mfa_user_")
        ) {
            return 15;
        }
        if (str_starts_with($key, "global_admin_timezone_")) {
            return 300;
        }
        if (in_array($key, ["signup_clinics_blocked", "maintenance"], true)) {
            return 30;
        }
        return 60;
    
    }
}
