<?php
declare(strict_types=1);

namespace Prontoo\Runtime\Legacy\SupportFoundation;

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

final class SupportFoundationRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function app_global_admin_timezone(int $userId = 0): string
    
    {
    
        $userId = $userId > 0 ? $userId : (int) ($_SESSION["uid"] ?? 0);
        if ($userId > 0 && has_cfg() && function_exists("val")) {
            try {
                $tz =
                    (string) (val(
                        "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
                        ["global_admin_timezone_user_" . $userId],
                    ) ?:
                    "");
                if ($tz !== "") {
                    return app_timezone_safe($tz);
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo global admin timezone user] " . $e->getMessage(),
                );
            }
        }
        if (has_cfg() && function_exists("val")) {
            try {
                $tz =
                    (string) (val(
                        "SELECT meta_value FROM pi_meta WHERE meta_key=? LIMIT 1",
                        ["global_admin_timezone_default"],
                    ) ?:
                    "");
                if ($tz !== "") {
                    return app_timezone_safe($tz);
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo global admin timezone default] " . $e->getMessage(),
                );
            }
        }
        return app_timezone_safe(
            (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? "America/Cuiaba"),
        );
    
    }

    public static function app_context_timezone(?array $context = null, int $clinicId = 0): string
    
    {
    
        if (
            is_array($context) &&
            mb_trim((string) ($context["timezone"] ?? "")) !== ""
        ) {
            return app_timezone_safe((string) $context["timezone"]);
        }
        if ($clinicId > 0 && function_exists("val") && has_cfg()) {
            static $cache = [];
            if (isset($cache[$clinicId])) {
                return $cache[$clinicId];
            }
            try {
                $tz =
                    (string) (val(
                        "SELECT timezone FROM pi_clinics WHERE id=? LIMIT 1",
                        [$clinicId],
                    ) ?:
                    "America/Cuiaba");
                return $cache[$clinicId] = app_timezone_safe($tz);
            } catch (Throwable $e) {
                error_log("[Prontoo clinic timezone] " . $e->getMessage());
            }
        }
        if (function_exists("ctx")) {
            try {
                $c = ctx();
                if (($c["scope"] ?? "") === "clinic") {
                    return app_timezone_safe(
                        (string) ($c["timezone"] ?? "America/Cuiaba"),
                    );
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo recoverable " .
                        __FUNCTION__ .
                        "] " .
                        $e->getMessage(),
                );
            }
        }
        return app_timezone_safe(
            (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? "America/Cuiaba"),
        );
    
    }

    public static function app_now_in_timezone(
        int $clinicId = 0,
        ?array $context = null,
    ): DateTimeImmutable 
    {
    
        return app_now_utc()->setTimezone(
            new DateTimeZone(app_context_timezone($context, $clinicId)),
        );
    
    }

    public static function app_today_in_timezone(
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        return app_now_in_timezone($clinicId, $context)->format("Y-m-d");
    
    }

    public static function app_month_in_timezone(
        int $clinicId = 0,
        ?array $context = null,
        ?DateTimeImmutable $nowUtc = null,
    ): string 
    {
    
        $nowUtc ??= app_now_utc();
        return $nowUtc
            ->setTimezone(
                new DateTimeZone(app_context_timezone($context, $clinicId)),
            )
            ->format("Y-m");
    
    }

    public static function app_local_month_utc_range(
        string $month,
        int $clinicId = 0,
        ?array $context = null,
    ): array 
    {
    
        if (
            preg_match('/^(\d{4})-(\d{2})$/', $month, $parts) !== 1 ||
            !checkdate((int) $parts[2], 1, (int) $parts[1])
        ) {
            $month = app_month_in_timezone($clinicId, $context);
        }
        $zone = new DateTimeZone(app_context_timezone($context, $clinicId));
        $start = new DateTimeImmutable($month . "-01 00:00:00", $zone);
        $end = $start->modify("+1 month");
        $utc = new DateTimeZone("UTC");
        return [
            (string) $start->setTimezone($utc)->getTimestamp(),
            (string) $end->setTimezone($utc)->getTimestamp(),
        ];
    
    }

    public static function app_db_utc_to_local(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): ?DateTimeImmutable 
    {
    
        $dt = app_parse_db_utc($value);
        if (!$dt) {
            return null;
        }
        return $dt->setTimezone(
            new DateTimeZone(app_context_timezone($context, $clinicId)),
        );
    
    }

    public static function app_local_to_db_utc(
        ?string $value,
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        $value = mb_trim((string) ($value ?? ""));
        if ($value === "") {
            return "";
        }
        if (class_exists("\\Prontoo\\Core\\Temporal\\PiTime")) {
            return \Prontoo\Core\Temporal\PiTime::fromLocalToUtcTimestamp(
                $value,
                preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            );
        }
        try {
            $dt = new DateTimeImmutable(
                $value,
                new DateTimeZone(app_context_timezone($context, $clinicId)),
            );
            return (string) $dt
                ->setTimezone(new DateTimeZone("UTC"))
                ->getTimestamp();
        } catch (Throwable $e) {
            return $value;
        }
    
    }

    public static function app_db_utc_to_local_input(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        $dt = app_db_utc_to_local($value, $clinicId, $context);
        return $dt ? $dt->format("Y-m-d\TH:i") : "";
    
    }

    public static function app_local_day_utc_range(
        string $day,
        int $clinicId = 0,
        ?array $context = null,
    ): array 
    {
    
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = app_today_in_timezone($clinicId, $context);
        }
        $zone = new DateTimeZone(app_context_timezone($context, $clinicId));
        $start = new DateTimeImmutable($day . " 00:00:00", $zone);
        $end = $start->modify("+1 day");
        return [
            (string) $start->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
            (string) $end->setTimezone(new DateTimeZone("UTC"))->getTimestamp(),
        ];
    
    }

    public static function app_date_only_end_timestamp(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): int 
    {
    
        $ymd = app_date_input_from_storage($value);
        if ($ymd === "" || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return 0;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return 0;
        }
        [, $end] = app_local_day_utc_range($ymd, $clinicId, $context);
        return max(0, (int) $end - 1);
    
    }

    public static function app_time_br(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        $dt = app_db_utc_to_local($value, $clinicId, $context);
        return $dt ? $dt->format("H:i") : "--:--";
    
    }

    public static function app_date_br(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        $dt = app_db_utc_to_local($value, $clinicId, $context);
        return $dt ? $dt->format("d/m/Y") : "—";
    
    }

    public static function app_datetime_br(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        $dt = app_db_utc_to_local($value, $clinicId, $context);
        if (!$dt) {
            return mb_trim((string) ($value ?? "")) ?: "—";
        }
        $m = prontoo_months_br();
        $year = (int) $dt->format("Y");
        $current = (int) app_now_in_timezone($clinicId, $context)->format("Y");
        $date =
            $dt->format("d") .
            " de " .
            $m[(int) $dt->format("n")] .
            ($year === $current ? "" : " de " . $dt->format("Y"));
        return $date . ", às " . $dt->format("H\hi");
    
    }

    public static function patient_display_name(int $patientLinkId, ?int $cid = null): string
    
    {
    
        try {
            if (function_exists("audit_patient_name_by_link")) {
                $name = mb_trim((string) audit_patient_name_by_link($patientLinkId, $cid));
                if ($name !== "") {
                    return $name;
                }
            }
            $params = [$patientLinkId];
            $where = "pp.id=?";
            if ($cid !== null && $cid > 0) {
                $where .= " AND pp.clinic_id=?";
                $params[] = $cid;
            }
            $name = mb_trim((string) (val(
                "SELECT p.full_name FROM pi_patients pp JOIN pi_persons p ON p.id=pp.person_id WHERE $where LIMIT 1",
                $params,
            ) ?: ""));
            return $name !== "" ? $name : "paciente #" . $patientLinkId;
        } catch (Throwable $e) {
            error_log("[Prontoo patient display name] " . $e->getMessage());
            return "paciente #" . $patientLinkId;
        }
    
    }

    public static function maintenance_active(): bool
    
    {
    
        return has_cfg() && function_exists("meta_get") && meta_get("maintenance_active", "0") === "1";
    
    }

    public static function page_maintenance_notice(): void
    
    {
    
        $message = function_exists("meta_get")
            ? meta_get(
                "maintenance_message",
                "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.",
            )
            : "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.";
        page(
            "Manutenção programada",
            '<section class="auth widebox"><h1>Prontoo em manutenção</h1><p>' .
                e($message) .
                '</p><p><a class="ghost" href="' .
                e(href("login")) .
                '">Voltar ao início</a></p></section>',
            ["public" => true, "robots" => "noindex,nofollow"],
        );
    
    }

    public static function app_enforce_canonical_host(): void
    
    {
    
        if (PHP_SAPI === "cli" || !app_is_production()) {
            return;
        }
        $canonical = app_canonical_host();
        if ($canonical === "") {
            return;
        }
        $raw = strtolower(preg_replace('/:\d+$/', "", request_host_raw()) ?: "");
        if (
            $raw === "" ||
            in_array($raw, ["localhost", "127.0.0.1", "::1"], true)
        ) {
            return;
        }
        if ($raw !== $canonical) {
            throw new ProntooHttpError(
                421,
                "Host não reconhecido para esta instalação.",
            );
        }
    
    }

    public static function is_https(): bool
    
    {
    
        if (function_exists("security_https_active")) {
            return security_https_active();
        }
        return (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ||
            (string) ($_SERVER["SERVER_PORT"] ?? "") === "443";
    
    }

    public static function request_host(): string
    
    {
    
        $canonical = app_is_production() ? app_canonical_host() : "";
        if ($canonical !== "") {
            return $canonical;
        }
        return request_host_raw();
    
    }

    public static function base_url(string $suffix = ""): string
    
    {
    
        return (is_https() ? "https://" : "http://") .
            request_host() .
            base_path() .
            "/" .
            ltrim($suffix, "/");
    
    }

    public static function href(string $route, array $params = []): string
    
    {
    
        return (base_path() ?: "") .
            "/?" .
            http_build_query(["r" => $route] + $params);
    
    }

    public static function redirect(string $route, array $params = []): void
    
    {
    
        if (!headers_sent()) {
            header("Location: " . href($route, $params));
        }
        exit();
    
    }

    public static function app_fail(Throwable $e, int $status = 500): void
    
    {
    
        $status = $e instanceof ProntooHttpError ? $e->status : $status;
        $http = $e instanceof ProntooHttpError;
        if (!$http || $status >= 500) {
            error_log(
                "[Prontoo fatal] " .
                    (function_exists("privacy_sanitize_error_message")
                        ? privacy_sanitize_error_message($e, 240)
                        : $e->getMessage()) .
                    " in " .
                    (function_exists("privacy_log_file_label")
                        ? privacy_log_file_label($e->getFile())
                        : basename($e->getFile())) .
                    ":" .
                    $e->getLine(),
            );
            log_runtime_error($e, $status);
        }
        if (!headers_sent()) {
            http_response_code($status);
            header("Content-Type: text/html; charset=utf-8");
            header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        }
        $title = $http
            ? "Não foi possível continuar com esta sessão."
            : "Não foi possível concluir esta ação.";
        $message = $http
            ? $e->getMessage()
            : "O Prontoo registrou uma instabilidade e evitou continuar em estado inconsistente. Tente novamente em alguns instantes.";
        $detail = app_debug()
            ? "<pre>" .
                e($e->getMessage()) .
                "
    " .
                e($e->getFile() . ":" . $e->getLine()) .
                "</pre>"
            : "";
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="canonical" href="https://prontoo.app/"><title>Prontoo — instabilidade temporária</title><meta name="theme-color" content="#334155"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="prontoo-version" content="' .
            e(PRONTOO_VERSION) .
            '"><meta name="csrf-token" content="' .
            e(csrf()) .
            '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" href="/public/assets/favicon-' .
            rawurlencode(PRONTOO_ASSET_REV) .
            '.png" type="image/png"><link rel="stylesheet" href="/public/assets/design-system.css?v=' .
            rawurlencode(
                defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
            ) .
            '"></head><body class="public"><main><section class="auth widebox"><h1>' .
            e($title) .
            "</h1><p>" .
            e($message) .
            "</p>" .
            $detail .
            '<p><a class="primary" href="' .
            e(href("login")) .
            '">Voltar ao início</a></p></section></main></body></html>';
        exit();
    
    }

    public static function cached_val(string $key, int $ttl, string $sql, array $p = []): mixed
    
    {
    
        $ttl = max(0, $ttl);
        if ($ttl <= 0) {
            return val($sql, $p);
        }
        $ck =
            "sql_" .
            $key .
            "_" .
            hash(
                "sha256",
                $sql .
                    "|" .
                    json_encode(
                        $p,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ),
            );
        if (
            function_exists("server_json_cache_remember") &&
            server_json_cache_read_allowed()
        ) {
            return server_json_cache_remember(
                "warm",
                $ck,
                $ttl,
                 fn() => val($sql, $p),
                ["sql"],
            );
        }
        $cached = cache_get($ck, $ttl);
        if ($cached !== null) {
            return $cached;
        }
        return cache_set($ck, val($sql, $p));
    
    }

    public static function counter_inc(string $name, int $by = 1): void
    
    {
    
        try {
            q(
                "INSERT INTO pi_platform_counters (counter_key,counter_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE counter_value=counter_value+VALUES(counter_value), updated_at=NOW()",
                [counter_key($name), $by],
            );
        } catch (Throwable $e) {
            error_log("[Prontoo counter_inc] " . $e->getMessage());
        }
    
    }

    public static function counter_get(string $name, int $fallback = 0): int
    
    {
    
        try {
            return (int) (val(
                "SELECT counter_value FROM pi_platform_counters WHERE counter_key=?",
                [counter_key($name)],
            ) ?? $fallback);
        } catch (Throwable $e) {
            error_log("[Prontoo counter_get] " . $e->getMessage());
            return $fallback;
        }
    
    }
}
