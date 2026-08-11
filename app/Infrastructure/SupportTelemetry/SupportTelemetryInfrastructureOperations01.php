<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SupportTelemetry;

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

final class SupportTelemetryInfrastructureOperations01
{
    private function __construct()
    {
    }

    public static function telemetry_storage_dir(): string
    
    {
        if (is_callable([\Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::class, 'storage_path'])) {
            return \Prontoo\Infrastructure\SupportFoundation\SupportFoundationInfrastructureOperations01::storage_path("telemetry");
        }
        return dirname((dirname(__DIR__, 2) . '/Support'), 2) . "/ssd/telemetry";
    
    }

    public static function telemetry_views_file(): string
    {
        return self::telemetry_storage_dir() . "/views.json";
    }

    public static function telemetry_speed_file(): string
    {
        return self::telemetry_storage_dir() . "/speed.json";
    }

    public static function telemetry_legacy_file(): string
    {
        return self::telemetry_storage_dir() . "/page-loads.jsonl";
    }

    public static function telemetry_file(): string
    {
        return self::telemetry_views_file();
    }

    private static function telemetry_views_schema(): string
    {
        return "prontoo.telemetria.visualizacoes.v1";
    }

    private static function telemetry_speed_schema(): string
    {
        return "prontoo.telemetria.velocidade.v1";
    }

    private static function telemetry_lock_file(): string
    {
        return self::telemetry_storage_dir() . "/.canonical.lock";
    }

    private static function telemetry_payload_read(string $file, string $schema): array
    {
        if (!is_file($file)) {
            return ["schema" => $schema, "timezone" => "America/Cuiaba", "retention_days" => 31, "events" => []];
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || mb_trim($raw) === "") {
            return ["schema" => $schema, "timezone" => "America/Cuiaba", "retention_days" => 31, "events" => []];
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || (string) ($payload["schema"] ?? "") !== $schema) {
            throw new RuntimeException("JSON canônico de telemetria inválido.");
        }
        $payload["events"] = is_array($payload["events"] ?? null) ? array_values($payload["events"]) : [];
        return $payload;
    }

    private static function telemetry_payload_write(string $file, array $payload): bool
    {
        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . PHP_EOL;
        $tmp = $file . ".tmp." . getmypid() . "." . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $encoded, LOCK_EX) !== strlen($encoded)) {
            @unlink($tmp);
            return false;
        }
        @chmod($tmp, 0640);
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            return false;
        }
        @chmod($file, 0640);
        return true;
    }

    private static function telemetry_view_event(array $event): array
    {
        return [
            "evento_id" => (string) ($event["evento_id"] ?? ""),
            "rota" => (string) ($event["rota"] ?? "unknown"),
            "fim_unix_us" => (int) ($event["fim_unix_us"] ?? 0),
            "status_http" => (int) ($event["status_http"] ?? 200),
            "sucesso" => (bool) ($event["sucesso"] ?? false),
        ];
    }

    private static function telemetry_speed_event(array $event): array
    {
        return [
            "evento_id" => (string) ($event["evento_id"] ?? ""),
            "rota" => (string) ($event["rota"] ?? "unknown"),
            "fim_unix_us" => (int) ($event["fim_unix_us"] ?? 0),
            "duracao_ns" => max(0, (int) ($event["duracao_ns"] ?? 0)),
            "sucesso" => (bool) ($event["sucesso"] ?? false),
        ];
    }

    private static function telemetry_legacy_events(?int $nowUnixUs = null): array
    {
        $file = self::telemetry_legacy_file();
        if (!is_file($file)) {
            return [];
        }
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        $minimumUnixUs = $nowUnixUs - self::telemetry_retention_microseconds();
        $events = [];
        $seen = [];
        $handle = @fopen($file, "rb");
        if (!is_resource($handle)) {
            return [];
        }
        try {
            if (!@flock($handle, LOCK_SH)) {
                return [];
            }
            while (($line = fgets($handle)) !== false) {
                $decoded = json_decode(mb_trim($line), true);
                $event = is_array($decoded) ? self::telemetry_normalize_event($decoded) : null;
                if ($event === null || (int) ($event["fim_unix_us"] ?? 0) < $minimumUnixUs) {
                    continue;
                }
                $id = (string) ($event["evento_id"] ?? "");
                if ($id !== "" && isset($seen[$id])) {
                    continue;
                }
                if ($id !== "") {
                    $seen[$id] = true;
                }
                $events[] = $event;
            }
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
        return $events;
    }

    private static function telemetry_canonical_migrate_locked(?int $nowUnixUs = null): void
    {
        if (is_file(self::telemetry_views_file()) && is_file(self::telemetry_speed_file())) {
            return;
        }
        $legacy = self::telemetry_legacy_events($nowUnixUs);
        $views = [];
        $speed = [];
        foreach ($legacy as $event) {
            $views[] = self::telemetry_view_event($event);
            $speed[] = self::telemetry_speed_event($event);
        }
        $updated = (new DateTimeImmutable("now", self::telemetry_cuiaba_tz()))->format(DateTimeImmutable::ATOM);
        if (!is_file(self::telemetry_views_file())) {
            self::telemetry_payload_write(self::telemetry_views_file(), [
                "schema" => self::telemetry_views_schema(),
                "timezone" => "America/Cuiaba",
                "retention_days" => 31,
                "updated_at_local" => $updated,
                "events" => $views,
            ]);
        }
        if (!is_file(self::telemetry_speed_file())) {
            self::telemetry_payload_write(self::telemetry_speed_file(), [
                "schema" => self::telemetry_speed_schema(),
                "timezone" => "America/Cuiaba",
                "retention_days" => 31,
                "updated_at_local" => $updated,
                "events" => $speed,
            ]);
        }
    }

    private static function telemetry_lock_exclusive()
    {
        if (!self::telemetry_prepare_storage()) {
            return false;
        }
        $lock = @fopen(self::telemetry_lock_file(), "c+");
        if (!is_resource($lock) || !@flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                @fclose($lock);
            }
            return false;
        }
        return $lock;
    }

    private static function telemetry_unlock($lock): void
    {
        if (is_resource($lock)) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    public static function telemetry_schema(): string
    
    {
        return "prontoo.telemetria.pagina.v2";
    
    }

    public static function telemetry_page_internal_routes(): array
    
    {
        return [
            "login_telemetry_wave",
            "login_autotest",
            "goal_status",
            "patient_lookup",
            "patient_suggest",
            "person_lookup",
            "lead_lookup",
            "lead_patient_lookup",
            "counterparty_lookup",
            "counterparty_suggest",
        ];
    
    }

    public static function telemetry_page_response_candidate(int $statusCode): bool
    
    {
        if (
            $statusCode < 200 ||
            $statusCode === 204 ||
            $statusCode === 205 ||
            $statusCode === 304 ||
            ($statusCode >= 300 && $statusCode < 400)
        ) {
            return false;
        }
        $contentType = "";
        foreach (headers_list() as $header) {
            $normalized = strtolower(mb_trim($header));
            if (str_starts_with($normalized, "location:")) {
                return false;
            }
            if (
                str_starts_with($normalized, "content-disposition:") &&
                str_contains($normalized, "attachment")
            ) {
                return false;
            }
            if (str_starts_with($normalized, "content-type:")) {
                $contentType = mb_trim(substr($normalized, strlen("content-type:")));
            }
        }
        return $contentType === "" ||
            str_contains($contentType, "text/html") ||
            str_contains($contentType, "application/xhtml+xml");
    
    }

    public static function telemetry_retention_microseconds(): int
    
    {
        return 31 * 86400 * 1000000;
    
    }

    public static function telemetry_comparison_microseconds(): int
    
    {
        return 15 * 86400 * 1000000;
    
    }

    public static function telemetry_cuiaba_tz(): DateTimeZone
    
    {
        static $timezone = null;
        return $timezone ??= new DateTimeZone("America/Cuiaba");
    
    }

    public static function telemetry_day_axis_label(DateTimeImmutable $day): string
    
    {
        $weekdays = ["D", "S", "T", "Q", "Q", "S", "S"];
        return $weekdays[(int) $day->format("w")] . $day->format("d");
    
    }

    public static function telemetry_route_safe(string $route): string
    
    {
        $route = strtolower(trim($route));
        $route = preg_replace('/[^a-z0-9_\-]/', '_', $route) ?? "";
        $route = trim($route, "_-");
        return substr($route !== "" ? $route : "unknown", 0, 80);
    
    }

    public static function telemetry_prepare_storage(): bool
    
    {
        $dir = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_storage_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            return false;
        }
        @chmod($dir, 0750);
        if (function_exists("security_storage_deny_file")) {
            \Prontoo\Infrastructure\SecurityPrivacy\SecurityPrivacyInfrastructureOperations01::security_storage_deny_file($dir);
        } else {
            if (!is_file($dir . "/.htaccess")) {
                @file_put_contents($dir . "/.htaccess", "Require all denied\n", LOCK_EX);
            }
            if (!is_file($dir . "/index.html")) {
                @file_put_contents($dir . "/index.html", "", LOCK_EX);
            }
        }
        return is_writable($dir);
    
    }

    public static function telemetry_utc_from_unix_microseconds(int $unixMicroseconds): string
    
    {
        $seconds = intdiv(max(0, $unixMicroseconds), 1000000);
        $microseconds = max(0, $unixMicroseconds) % 1000000;
        return gmdate("Y-m-d\\TH:i:s", $seconds) .
            sprintf(".%06dZ", $microseconds);
    
    }

    public static function telemetry_route_identify(string $route): void
    
    {
        if (!empty($GLOBALS["PRONTOO_TELEMETRY_REGISTERED"])) {
            $GLOBALS["PRONTOO_ROUTE_NAME"] = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_route_safe($route);
        }
    
    }

    public static function telemetry_fatal_error(?array $error): ?string
    
    {
        if (!is_array($error)) {
            return null;
        }
        $type = (int) ($error["type"] ?? 0);
        return in_array(
            $type,
            [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR],
            true,
        )
            ? "php_" . $type
            : null;
    
    }

    public static function telemetry_build_event(
        string $route,
        int $startedMonotonicNs,
        int $finishedMonotonicNs,
        int $startedUnixUs,
        int $finishedUnixUs,
        int $statusCode,
        ?string $fatalError = null,
        ?string $release = null,
        ?string $method = null,
        ?string $path = null,
        string $finishMarker = "front_controller_last_useful_line",
    ): array 
    {
        $durationNs = max(0, $finishedMonotonicNs - $startedMonotonicNs);
        $statusCode = $statusCode >= 100 && $statusCode <= 599 ? $statusCode : 200;
        $route = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_route_safe($route);
        $method = strtoupper(mb_trim((string) ($method ?? "GET")));
        $method = in_array($method, ["GET", "POST"], true) ? $method : "GET";
        $path = mb_trim((string) ($path ?? "/"));
        $path = str_starts_with($path, "/") ? substr($path, 0, 240) : "/";
        $finishMarker = in_array(
            $finishMarker,
            ["front_controller_last_useful_line", "shutdown_fallback"],
            true,
        )
            ? $finishMarker
            : "front_controller_last_useful_line";
        return [
            "schema" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_schema(),
            "tipo" => "page_load",
            "evento_id" => substr(
                hash(
                    "sha256",
                    $route .
                        "|" .
                        $method .
                        "|" .
                        $path .
                        "|" .
                        $startedUnixUs .
                        "|" .
                        $finishedUnixUs .
                        "|" .
                        $durationNs,
                ),
                0,
                32,
            ),
            "rota" => $route,
            "metodo" => $method,
            "caminho" => $path,
            "marco_inicial" => "front_controller_first_executable_line",
            "marco_final" => $finishMarker,
            "inicio_utc" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_utc_from_unix_microseconds($startedUnixUs),
            "fim_utc" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_utc_from_unix_microseconds($finishedUnixUs),
            "inicio_unix_us" => $startedUnixUs,
            "fim_unix_us" => $finishedUnixUs,
            "inicio_monotonico_ns" => $startedMonotonicNs,
            "fim_monotonico_ns" => $finishedMonotonicNs,
            "duracao_ns" => $durationNs,
            "duracao_ms" => round(
                $durationNs / 1000000,
                6,
                \RoundingMode::HalfAwayFromZero,
            ),
            "status_http" => $statusCode,
            "sucesso" => $fatalError === null && $statusCode < 500,
            "erro_fatal" => $fatalError,
            "versao" => mb_trim(
                (string) ($release ??
                    (defined("PRONTOO_VERSION")
                        ? PRONTOO_VERSION
                        : (defined("BR_LANDING_VERSION")
                            ? BR_LANDING_VERSION
                            : "unknown"))),
            ),
        ];
    
    }

    public static function telemetry_normalize_event(array $event): ?array
    
    {
        if (
            (string) ($event["schema"] ?? "") !== \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_schema() ||
            (string) ($event["tipo"] ?? "") !== "page_load"
        ) {
            return null;
        }
        $route = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_route_safe((string) ($event["rota"] ?? ""));
        $startedNs = (int) ($event["inicio_monotonico_ns"] ?? -1);
        $finishedNs = (int) ($event["fim_monotonico_ns"] ?? -1);
        $startedUs = (int) ($event["inicio_unix_us"] ?? 0);
        $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
        $durationNs = (int) ($event["duracao_ns"] ?? -1);
        $method = strtoupper(mb_trim((string) ($event["metodo"] ?? "")));
        $path = mb_trim((string) ($event["caminho"] ?? ""));
        $finishMarker = (string) ($event["marco_final"] ?? "");
        if (
            $route === "unknown" ||
            $startedNs < 0 ||
            $finishedNs < $startedNs ||
            $startedUs <= 0 ||
            $finishedUs <= 0 ||
            $durationNs !== $finishedNs - $startedNs ||
            !in_array($method, ["GET", "POST"], true) ||
            !str_starts_with($path, "/") ||
            (string) ($event["marco_inicial"] ?? "") !==
                "front_controller_first_executable_line" ||
            !in_array(
                $finishMarker,
                ["front_controller_last_useful_line", "shutdown_fallback"],
                true,
            )
        ) {
            return null;
        }
        $event["rota"] = $route;
        $event["metodo"] = $method;
        $event["caminho"] = substr($path, 0, 240);
        $event["duracao_ms"] = round(
            $durationNs / 1000000,
            6,
            \RoundingMode::HalfAwayFromZero,
        );
        $event["status_http"] = max(
            100,
            min(599, (int) ($event["status_http"] ?? 200)),
        );
        $event["sucesso"] = (bool) ($event["sucesso"] ?? false);
        return $event;
    
    }

    public static function telemetry_json_line(array $event): ?string
    
    {
        $event = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_normalize_event($event);
        if ($event === null) {
            return null;
        }
        $json = json_encode(
            $event,
            JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_PRESERVE_ZERO_FRACTION,
        );
        return is_string($json) ? $json . "\n" : null;
    
    }

    public static function telemetry_append_event(array $event): bool
    {
        $event = self::telemetry_normalize_event($event);
        if ($event === null) {
            return false;
        }
        $lock = self::telemetry_lock_exclusive();
        if (!is_resource($lock)) {
            return false;
        }
        try {
            self::telemetry_canonical_migrate_locked((int) ($event["fim_unix_us"] ?? 0));
            $views = self::telemetry_payload_read(self::telemetry_views_file(), self::telemetry_views_schema());
            $speed = self::telemetry_payload_read(self::telemetry_speed_file(), self::telemetry_speed_schema());
            $id = (string) ($event["evento_id"] ?? "");
            foreach ((array) $views["events"] as $row) {
                if ($id !== "" && (string) ($row["evento_id"] ?? "") === $id) {
                    return true;
                }
            }
            $views["events"][] = self::telemetry_view_event($event);
            $speed["events"][] = self::telemetry_speed_event($event);
            $updated = (new DateTimeImmutable("@" . max(1, intdiv((int) $event["fim_unix_us"], 1000000))))
                ->setTimezone(self::telemetry_cuiaba_tz())
                ->format(DateTimeImmutable::ATOM);
            $views["updated_at_local"] = $updated;
            $speed["updated_at_local"] = $updated;
            $oldViews = is_file(self::telemetry_views_file()) ? @file_get_contents(self::telemetry_views_file()) : false;
            if (!self::telemetry_payload_write(self::telemetry_views_file(), $views)) {
                return false;
            }
            if (!self::telemetry_payload_write(self::telemetry_speed_file(), $speed)) {
                if (is_string($oldViews)) {
                    @file_put_contents(self::telemetry_views_file(), $oldViews, LOCK_EX);
                }
                return false;
            }
            return true;
        } finally {
            self::telemetry_unlock($lock);
        }
    }

    public static function telemetry_read_events(?int $nowUnixUs = null): array
    {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        $minimumUnixUs = $nowUnixUs - self::telemetry_retention_microseconds();
        $lock = self::telemetry_lock_exclusive();
        if (!is_resource($lock)) {
            return [];
        }
        try {
            self::telemetry_canonical_migrate_locked($nowUnixUs);
            $views = self::telemetry_payload_read(self::telemetry_views_file(), self::telemetry_views_schema());
            $speed = self::telemetry_payload_read(self::telemetry_speed_file(), self::telemetry_speed_schema());
        } catch (Throwable $error) {
            error_log("[Prontoo telemetria] " . $error->getMessage());
            return [];
        } finally {
            self::telemetry_unlock($lock);
        }
        $speedById = [];
        foreach ((array) ($speed["events"] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row["evento_id"] ?? "");
            if ($id !== "") {
                $speedById[$id] = $row;
            }
        }
        $events = [];
        $seen = [];
        foreach ((array) ($views["events"] ?? []) as $view) {
            if (!is_array($view)) {
                continue;
            }
            $finishedUs = (int) ($view["fim_unix_us"] ?? 0);
            if ($finishedUs < $minimumUnixUs || $finishedUs > $nowUnixUs) {
                continue;
            }
            $id = (string) ($view["evento_id"] ?? "");
            if ($id !== "" && isset($seen[$id])) {
                continue;
            }
            if ($id !== "") {
                $seen[$id] = true;
            }
            $speedRow = $speedById[$id] ?? null;
            $events[] = [
                "schema" => self::telemetry_schema(),
                "tipo" => "page_load",
                "evento_id" => $id,
                "rota" => self::telemetry_route_safe((string) ($view["rota"] ?? "unknown")),
                "fim_unix_us" => $finishedUs,
                "duracao_ns" => is_array($speedRow) ? max(0, (int) ($speedRow["duracao_ns"] ?? 0)) : 0,
                "status_http" => max(100, min(599, (int) ($view["status_http"] ?? 200))),
                "sucesso" => (bool) ($view["sucesso"] ?? false),
                "speed_observed" => is_array($speedRow),
            ];
        }
        usort($events, static fn(array $a, array $b): int => (int) $a["fim_unix_us"] <=> (int) $b["fim_unix_us"]);
        return $events;
    }

    public static function telemetry_prune(?int $nowUnixUs = null): int
    {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        $minimumUnixUs = $nowUnixUs - self::telemetry_retention_microseconds();
        $lock = self::telemetry_lock_exclusive();
        if (!is_resource($lock)) {
            return 0;
        }
        try {
            self::telemetry_canonical_migrate_locked($nowUnixUs);
            $views = self::telemetry_payload_read(self::telemetry_views_file(), self::telemetry_views_schema());
            $speed = self::telemetry_payload_read(self::telemetry_speed_file(), self::telemetry_speed_schema());
            $before = count((array) ($views["events"] ?? []));
            $filter = static fn(array $row): bool =>
                (int) ($row["fim_unix_us"] ?? 0) >= $minimumUnixUs &&
                (int) ($row["fim_unix_us"] ?? 0) <= $nowUnixUs;
            $views["events"] = array_values(array_filter((array) ($views["events"] ?? []), static fn($row): bool => is_array($row) && $filter($row)));
            $speed["events"] = array_values(array_filter((array) ($speed["events"] ?? []), static fn($row): bool => is_array($row) && $filter($row)));
            $updated = (new DateTimeImmutable("@" . max(1, intdiv($nowUnixUs, 1000000))))
                ->setTimezone(self::telemetry_cuiaba_tz())->format(DateTimeImmutable::ATOM);
            $views["updated_at_local"] = $updated;
            $speed["updated_at_local"] = $updated;
            if (!self::telemetry_payload_write(self::telemetry_views_file(), $views) ||
                !self::telemetry_payload_write(self::telemetry_speed_file(), $speed)) {
                return 0;
            }
            return max(0, $before - count($views["events"]));
        } catch (Throwable $error) {
            error_log("[Prontoo telemetria] " . $error->getMessage());
            return 0;
        } finally {
            self::telemetry_unlock($lock);
        }
    }

    public static function telemetry_percentage_variation(int|float $current, int|float $previous): ?float
    
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0.0 : null;
        }
        return round((((float) $current - (float) $previous) / (float) $previous) * 100, 6, \RoundingMode::HalfAwayFromZero);
    
    }

    public static function telemetry_nullable_percentage_variation(
        ?float $current,
        ?float $previous,
    ): ?float 
    {
        if ($current === null || $previous === null) {
            return null;
        }
        return \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_percentage_variation($current, $previous);
    
    }

    public static function telemetry_empty_period(): array
    
    {
        return [
            "requests" => 0,
            "duration_ns" => 0,
            "average_ms" => null,
            "landing_requests" => 0,
            "landing_duration_ns" => 0,
            "landing_average_ms" => null,
        ];
    
    }

    public static function telemetry_finalize_period(array $period): array
    
    {
        $requests = max(0, (int) ($period["requests"] ?? 0));
        $landingRequests = max(0, (int) ($period["landing_requests"] ?? 0));
        $period["average_ms"] = $requests > 0
            ? ((int) ($period["duration_ns"] ?? 0) / $requests) / 1000000
            : null;
        $period["landing_average_ms"] = $landingRequests > 0
            ? ((int) ($period["landing_duration_ns"] ?? 0) / $landingRequests) / 1000000
            : null;
        return $period;
    
    }

    public static function telemetry_comparative_summary(?int $nowUnixUs = null): array
    {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        $timezone = self::telemetry_cuiaba_tz();
        $today = (new DateTimeImmutable("@" . intdiv($nowUnixUs, 1000000)))
            ->setTimezone($timezone)
            ->setTime(0, 0);
        $currentEndUs = $today->getTimestamp() * 1000000;
        $currentStartUs = $today->modify("-15 days")->getTimestamp() * 1000000;
        $previousStartUs = $today->modify("-30 days")->getTimestamp() * 1000000;
        $current = self::telemetry_empty_period();
        $previous = self::telemetry_empty_period();
        $seen = [];
        foreach (self::telemetry_read_events($nowUnixUs) as $event) {
            $eventId = (string) ($event["evento_id"] ?? "");
            if ($eventId !== "") {
                if (isset($seen[$eventId])) {
                    continue;
                }
                $seen[$eventId] = true;
            }
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($finishedUs < $previousStartUs || $finishedUs >= $currentEndUs) {
                continue;
            }
            if ($finishedUs >= $currentStartUs) {
                $current["requests"]++;
                $current["duration_ns"] += (int) ($event["duracao_ns"] ?? 0);
                if ((string) ($event["rota"] ?? "") === "landing") {
                    $current["landing_requests"]++;
                    $current["landing_duration_ns"] += (int) ($event["duracao_ns"] ?? 0);
                }
            } else {
                $previous["requests"]++;
                $previous["duration_ns"] += (int) ($event["duracao_ns"] ?? 0);
                if ((string) ($event["rota"] ?? "") === "landing") {
                    $previous["landing_requests"]++;
                    $previous["landing_duration_ns"] += (int) ($event["duracao_ns"] ?? 0);
                }
            }
        }
        $current = self::telemetry_finalize_period($current);
        $previous = self::telemetry_finalize_period($previous);
        return [
            "generated_at_unix_us" => $nowUnixUs,
            "current_start_unix_us" => $currentStartUs,
            "previous_start_unix_us" => $previousStartUs,
            "current_end_unix_us" => $currentEndUs,
            "current" => $current,
            "previous" => $previous,
            "variations" => [
                "requests_pct" => self::telemetry_percentage_variation(
                    $current["requests"],
                    $previous["requests"],
                ),
                "average_ms_pct" => self::telemetry_nullable_percentage_variation(
                    isset($current["average_ms"]) ? (float) $current["average_ms"] : null,
                    isset($previous["average_ms"]) ? (float) $previous["average_ms"] : null,
                ),
                "landing_requests_pct" => self::telemetry_percentage_variation(
                    $current["landing_requests"],
                    $previous["landing_requests"],
                ),
                "landing_average_ms_pct" => self::telemetry_nullable_percentage_variation(
                    isset($current["landing_average_ms"]) ? (float) $current["landing_average_ms"] : null,
                    isset($previous["landing_average_ms"]) ? (float) $previous["landing_average_ms"] : null,
                ),
            ],
        ];
    }

}
