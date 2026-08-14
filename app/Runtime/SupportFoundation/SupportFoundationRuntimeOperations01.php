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

final class SupportFoundationRuntimeOperations01
{
    private function __construct()
    {
    }

    public static function app_global_admin_timezone(int $userId = 0): string
    
    {
    
        $userId = $userId > 0 ? $userId : (int) ($_SESSION["uid"] ?? 0);
        if ($userId > 0 && \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg() && is_callable([\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::class, 'val'])) {
            try {
                $tz =
                    (string) (\Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.01.app_global_admin_timezone.01', ["global_admin_timezone_user_" . $userId], []) ?:
                    "");
                if ($tz !== "") {
                    return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_safe($tz);
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo global admin timezone user] " . $e->getMessage(),
                );
            }
        }
        if (\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg() && is_callable([\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::class, 'val'])) {
            try {
                $tz =
                    (string) (\Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.01.app_global_admin_timezone.02', ["global_admin_timezone_default"], []) ?:
                    "");
                if ($tz !== "") {
                    return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_safe($tz);
                }
            } catch (Throwable $e) {
                error_log(
                    "[Prontoo global admin timezone default] " . $e->getMessage(),
                );
            }
        }
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_safe(
            (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? "America/Cuiaba"),
        );
    
    }

    public static function app_context_timezone(?array $context = null, int $clinicId = 0): string
    
    {
    
        if (
            is_array($context) &&
            mb_trim((string) ($context["timezone"] ?? "")) !== ""
        ) {
            return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_safe((string) $context["timezone"]);
        }
        if ($clinicId > 0 && is_callable([\Prontoo\Runtime\DatabaseSchema\DatabaseSchemaRuntimeOperations01::class, 'val']) && \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg()) {
            static $cache = [];
            if (isset($cache[$clinicId])) {
                return $cache[$clinicId];
            }
            try {
                $tz =
                    (string) (\Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.01.app_context_timezone.01', [$clinicId], []) ?:
                    "America/Cuiaba");
                return $cache[$clinicId] = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_safe($tz);
            } catch (Throwable $e) {
                error_log("[Prontoo clinic timezone] " . $e->getMessage());
            }
        }
        if (is_callable([\Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::class, 'ctx'])) {
            try {
                $c = \Prontoo\Runtime\SecurityAccess\SecurityAccessRuntimeOperations04::ctx();
                if (($c["scope"] ?? "") === "clinic") {
                    return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_safe(
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
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_timezone_safe(
            (string) ($GLOBALS["PRONTOO_DISPLAY_TIMEZONE"] ?? "America/Cuiaba"),
        );
    
    }

    public static function app_now_in_timezone(
        int $clinicId = 0,
        ?array $context = null,
    ): DateTimeImmutable 
    {
    
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_now_utc()->setTimezone(
            new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone($context, $clinicId)),
        );
    
    }

    public static function app_today_in_timezone(
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        return \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_now_in_timezone($clinicId, $context)->format("Y-m-d");
    
    }

    public static function app_month_in_timezone(
        int $clinicId = 0,
        ?array $context = null,
        ?DateTimeImmutable $nowUtc = null,
    ): string 
    {
    
        $nowUtc ??= \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_now_utc();
        return $nowUtc
            ->setTimezone(
                new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone($context, $clinicId)),
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
            $month = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_month_in_timezone($clinicId, $context);
        }
        $zone = new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone($context, $clinicId));
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
    
        $dt = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_parse_db_utc($value);
        if (!$dt) {
            return null;
        }
        return $dt->setTimezone(
            new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone($context, $clinicId)),
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
                new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone($context, $clinicId)),
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
    
        $dt = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($value, $clinicId, $context);
        return $dt ? $dt->format("Y-m-d\TH:i") : "";
    
    }

    public static function app_local_day_utc_range(
        string $day,
        int $clinicId = 0,
        ?array $context = null,
    ): array 
    {
    
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_today_in_timezone($clinicId, $context);
        }
        $zone = new DateTimeZone(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_context_timezone($context, $clinicId));
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
    
        $ymd = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_date_input_from_storage($value);
        if ($ymd === "" || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return 0;
        }
        if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return 0;
        }
        [, $end] = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_local_day_utc_range($ymd, $clinicId, $context);
        return max(0, (int) $end - 1);
    
    }

    public static function app_time_br(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        $dt = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($value, $clinicId, $context);
        return $dt ? $dt->format("H:i") : "--:--";
    
    }

    public static function app_date_br(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        $dt = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($value, $clinicId, $context);
        return $dt ? $dt->format("d/m/Y") : "—";
    
    }

    public static function app_datetime_br(
        null|string|int $value,
        int $clinicId = 0,
        ?array $context = null,
    ): string 
    {
    
        $dt = \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_db_utc_to_local($value, $clinicId, $context);
        if (!$dt) {
            return mb_trim((string) ($value ?? "")) ?: "—";
        }
        $m = \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::prontoo_months_br();
        $year = (int) $dt->format("Y");
        $current = (int) \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::app_now_in_timezone($clinicId, $context)->format("Y");
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
            if (is_callable([\Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::class, 'audit_patient_name_by_link'])) {
                $name = mb_trim((string) \Prontoo\Runtime\AuditActivity\AuditActivityRuntimeOperations03::audit_patient_name_by_link($patientLinkId, $cid));
                if ($name !== "") {
                    return $name;
                }
            }
            $params = [$patientLinkId];
            if ($cid !== null && $cid > 0) {
                $params[] = $cid;
            }
            $name = mb_trim((string) (\Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.01.patient_display_name.01', $params, ['tenantScoped' => count($params) === 2]) ?: ""));
            return $name !== "" ? $name : "paciente #" . $patientLinkId;
        } catch (Throwable $e) {
            error_log("[Prontoo patient display name] " . $e->getMessage());
            return "paciente #" . $patientLinkId;
        }
    
    }

    public static function maintenance_active(): bool
    
    {
    
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::has_cfg() && is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'meta_get']) && \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get("maintenance_active", "0") === "1";
    
    }

    public static function page_maintenance_notice(): void
    
    {
    
        $message = is_callable([\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::class, 'meta_get'])
            ? \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::meta_get(
                "maintenance_message",
                "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.",
            )
            : "Estamos fazendo uma manutenção rápida para melhorar o serviço. Tente novamente em instantes.";
        \Prontoo\Runtime\UiComponents\UiComponentsRuntimeOperations02::page(
            "Manutenção programada",
            '<section class="auth widebox"><h1>Prontoo em manutenção</h1><p>' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($message) .
                '</p><p><a class="ghost" href="' .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("login")) .
                '">Voltar ao início</a></p></section>',
            ["public" => true, "robots" => "noindex,nofollow"],
        );
    
    }

    public static function app_enforce_canonical_host(): void
    
    {
    
        if (PHP_SAPI === "cli" || !\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_is_production()) {
            return;
        }
        $canonical = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_canonical_host();
        if ($canonical === "") {
            return;
        }
        $raw = strtolower(preg_replace('/:\d+$/', "", \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::request_host_raw()) ?: "");
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
        return \Prontoo\Runtime\SecurityPrivacy\SecurityPrivacyRuntimeOperations01::security_https_active();
    }

    public static function request_host(): string
    
    {
    
        $canonical = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_is_production() ? \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_canonical_host() : "";
        if ($canonical !== "") {
            return $canonical;
        }
        return \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::request_host_raw();
    
    }

    public static function base_url(string $suffix = ""): string
    
    {
    
        return (\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::is_https() ? "https://" : "http://") .
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::request_host() .
            \Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::base_path() .
            "/" .
            ltrim($suffix, "/");
    
    }

    public static function href(string $route, array $params = []): string
    
    {
    
        return (\Prontoo\Presentation\SupportFoundation\SupportFoundationPresentationOperations01::base_path() ?: "") .
            "/?" .
            http_build_query(["r" => $route] + $params);
    
    }

    public static function redirect(string $route, array $params = []): void
    
    {
    
        if (!headers_sent()) {
            header("Location: " . \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href($route, $params));
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
                    \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::privacy_sanitize_error_message($e, 240) .
                    " in " .
                    \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::privacy_log_file_label($e->getFile()) .
                    ":" .
                    $e->getLine(),
            );
            \Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations02::log_runtime_error($e, $status);
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
        $detail = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::app_debug()
            ? "<pre>" .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($e->getMessage()) .
                "
    " .
                \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($e->getFile() . ":" . $e->getLine()) .
                "</pre>"
            : "";
        echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><link rel="canonical" href="https://prontoo.app/"><title>Prontoo — instabilidade temporária</title><meta name="theme-color" content="#334155"><meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><meta name="prontoo-version" content="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(PRONTOO_VERSION) .
            '"><meta name="csrf-token" content="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Presentation\SecurityAccess\SecurityAccessPresentationOperations01::csrf()) .
            '"><link rel="icon" href="/favicon.ico" sizes="any"><link rel="icon" href="/public/assets/favicon-' .
            rawurlencode(PRONTOO_ASSET_REV) .
            '.png" type="image/png"><link rel="stylesheet" href="/public/assets/presentation.css?v=' .
            rawurlencode(
                defined("PRONTOO_ASSET_REV") ? PRONTOO_ASSET_REV : PRONTOO_VERSION,
            ) .
            '"></head><body class="public"><main><section class="auth widebox"><h1>' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($title) .
            "</h1><p>" .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e($message) .
            "</p>" .
            $detail .
            '<p><a class="primary" href="' .
            \Prontoo\Presentation\UiComponents\UiComponentsPresentationOperations01::e(\Prontoo\Runtime\SupportFoundation\SupportFoundationRuntimeOperations01::href("login")) .
            '">Voltar ao início</a></p></section></main></body></html>';
        exit();
    
    }

    public static function cached_val(
        string $key,
        int $ttl,
        string $query,
        array $p = [],
        array $context = [],
    ): mixed
    
    {
    
        $ttl = max(0, $ttl);
        if ($ttl <= 0) {
            return \Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.01.cached_val.01', $p, ['query' => $query] + $context);
        }
        $ck =
            "sql_" .
            $key .
            "_" .
            hash(
                "sha256",
                $query .
                    "|" .
                    json_encode(
                        [$p, $context],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ),
            );
        if (
            is_callable([\Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::class, 'server_json_cache_remember']) &&
            \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_read_allowed()
        ) {
            return \Prontoo\Runtime\ServerJsonCache\ServerJsonCacheRuntimeOperations01::server_json_cache_remember(
                "warm",
                $ck,
                $ttl,
                 fn() => \Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.01.cached_val.02', $p, ['query' => $query] + $context),
                ["sql"],
            );
        }
        $cached = \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cache_get($ck, $ttl);
        if ($cached !== null) {
            return $cached;
        }
        return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::cache_set($ck, \Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.01.cached_val.03', $p, ['query' => $query] + $context));
    
    }

    public static function counter_inc(string $name, int $by = 1): void
    
    {
    
        try {
            \Prontoo\Runtime\Operational\OperationalComposition::platform()->result('operational.support_foundation.01.counter_inc.01', [\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::counter_key($name), $by], []);
        } catch (Throwable $e) {
            error_log("[Prontoo counter_inc] " . $e->getMessage());
        }
    
    }

    public static function counter_get(string $name, int $fallback = 0): int
    
    {
    
        try {
            return (int) (\Prontoo\Runtime\Operational\OperationalComposition::platform()->scalar('operational.support_foundation.01.counter_get.01', [\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::counter_key($name)], []) ?? $fallback);
        } catch (Throwable $e) {
            error_log("[Prontoo counter_get] " . $e->getMessage());
            return $fallback;
        }
    
    }
}
