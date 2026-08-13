<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SupportTelemetry;

use \DateTimeImmutable;
use \RuntimeException;
use \Throwable;

final class TelemetryJsonStore
{
    private function __construct()
    {
    }

    public static function append(array $event): bool
    {
        $event = SupportTelemetryInfrastructureOperations01::telemetry_normalize_event($event);
        if ($event === null) {
            return false;
        }
        $lock = self::lockExclusive();
        if (!is_resource($lock)) {
            return false;
        }
        try {
            self::migrateLegacyLocked((int) ($event["fim_unix_us"] ?? 0));
            $views = self::payloadRead(
                SupportTelemetryInfrastructureOperations01::telemetry_views_file(),
                self::viewsSchema(),
            );
            $speed = self::payloadRead(
                SupportTelemetryInfrastructureOperations01::telemetry_speed_file(),
                self::speedSchema(),
            );
            $id = (string) ($event["evento_id"] ?? "");
            foreach ((array) $views["events"] as $row) {
                if ($id !== "" && (string) ($row["evento_id"] ?? "") === $id) {
                    return true;
                }
            }
            $views["events"][] = self::viewEvent($event);
            $speed["events"][] = self::speedEvent($event);
            $updated = (new DateTimeImmutable(
                "@" . max(1, intdiv((int) $event["fim_unix_us"], 1000000)),
            ))
                ->setTimezone(SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz())
                ->format(DateTimeImmutable::ATOM);
            $views["updated_at_local"] = $updated;
            $speed["updated_at_local"] = $updated;
            $viewsFile = SupportTelemetryInfrastructureOperations01::telemetry_views_file();
            $oldViews = is_file($viewsFile) ? @file_get_contents($viewsFile) : false;
            if (!self::payloadWrite($viewsFile, $views)) {
                return false;
            }
            if (!self::payloadWrite(
                SupportTelemetryInfrastructureOperations01::telemetry_speed_file(),
                $speed,
            )) {
                if (is_string($oldViews)) {
                    @file_put_contents($viewsFile, $oldViews, LOCK_EX);
                }
                return false;
            }
            return true;
        } catch (Throwable $error) {
            error_log("[Prontoo telemetria] " . $error->getMessage());
            return false;
        } finally {
            self::unlock($lock);
        }
    }

    public static function read(?int $nowUnixUs = null): array
    {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        $minimumUnixUs = $nowUnixUs -
            SupportTelemetryInfrastructureOperations01::telemetry_retention_microseconds();
        $lock = self::lockExclusive();
        if (!is_resource($lock)) {
            return [];
        }
        try {
            self::migrateLegacyLocked($nowUnixUs);
            $views = self::payloadRead(
                SupportTelemetryInfrastructureOperations01::telemetry_views_file(),
                self::viewsSchema(),
            );
            $speed = self::payloadRead(
                SupportTelemetryInfrastructureOperations01::telemetry_speed_file(),
                self::speedSchema(),
            );
        } catch (Throwable $error) {
            error_log("[Prontoo telemetria] " . $error->getMessage());
            return [];
        } finally {
            self::unlock($lock);
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
                "schema" => SupportTelemetryInfrastructureOperations01::telemetry_schema(),
                "tipo" => "page_load",
                "evento_id" => $id,
                "rota" => SupportTelemetryInfrastructureOperations01::telemetry_route_safe(
                    (string) ($view["rota"] ?? "unknown"),
                ),
                "fim_unix_us" => $finishedUs,
                "duracao_ns" => is_array($speedRow)
                    ? max(0, (int) ($speedRow["duracao_ns"] ?? 0))
                    : 0,
                "database_query_count" => is_array($speedRow)
                    ? max(0, (int) ($speedRow["database_query_count"] ?? 0))
                    : 0,
                "database_query_duration_ns" => is_array($speedRow)
                    ? max(0, (int) ($speedRow["database_query_duration_ns"] ?? 0))
                    : 0,
                "database_query_observed" => is_array($speedRow) &&
                    array_key_exists("database_query_count", $speedRow) &&
                    (int) ($speedRow["database_query_count"] ?? 0) > 0,
                "status_http" => max(
                    100,
                    min(599, (int) ($view["status_http"] ?? 200)),
                ),
                "sucesso" => (bool) ($view["sucesso"] ?? false),
                "speed_observed" => is_array($speedRow),
            ];
        }
        usort(
            $events,
            static fn(array $a, array $b): int =>
                (int) $a["fim_unix_us"] <=> (int) $b["fim_unix_us"],
        );
        return $events;
    }

    public static function prune(?int $nowUnixUs = null): int
    {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        $minimumUnixUs = $nowUnixUs -
            SupportTelemetryInfrastructureOperations01::telemetry_retention_microseconds();
        $lock = self::lockExclusive();
        if (!is_resource($lock)) {
            return 0;
        }
        try {
            self::migrateLegacyLocked($nowUnixUs);
            $views = self::payloadRead(
                SupportTelemetryInfrastructureOperations01::telemetry_views_file(),
                self::viewsSchema(),
            );
            $speed = self::payloadRead(
                SupportTelemetryInfrastructureOperations01::telemetry_speed_file(),
                self::speedSchema(),
            );
            $before = count((array) ($views["events"] ?? []));
            $filter = static fn(array $row): bool =>
                (int) ($row["fim_unix_us"] ?? 0) >= $minimumUnixUs &&
                (int) ($row["fim_unix_us"] ?? 0) <= $nowUnixUs;
            $views["events"] = array_values(
                array_filter(
                    (array) ($views["events"] ?? []),
                    static fn($row): bool => is_array($row) && $filter($row),
                ),
            );
            $speed["events"] = array_values(
                array_filter(
                    (array) ($speed["events"] ?? []),
                    static fn($row): bool => is_array($row) && $filter($row),
                ),
            );
            $updated = (new DateTimeImmutable(
                "@" . max(1, intdiv($nowUnixUs, 1000000)),
            ))
                ->setTimezone(SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz())
                ->format(DateTimeImmutable::ATOM);
            $views["updated_at_local"] = $updated;
            $speed["updated_at_local"] = $updated;
            if (!self::payloadWrite(
                SupportTelemetryInfrastructureOperations01::telemetry_views_file(),
                $views,
            ) || !self::payloadWrite(
                SupportTelemetryInfrastructureOperations01::telemetry_speed_file(),
                $speed,
            )) {
                return 0;
            }
            return max(0, $before - count($views["events"]));
        } catch (Throwable $error) {
            error_log("[Prontoo telemetria] " . $error->getMessage());
            return 0;
        } finally {
            self::unlock($lock);
        }
    }

    private static function viewsSchema(): string
    {
        return "prontoo.telemetria.visualizacoes.v1";
    }

    private static function speedSchema(): string
    {
        return "prontoo.telemetria.velocidade.v1";
    }

    private static function lockFile(): string
    {
        return SupportTelemetryInfrastructureOperations01::telemetry_storage_dir() .
            "/.canonical.lock";
    }

    private static function payloadRead(string $file, string $schema): array
    {
        if (!is_file($file)) {
            return self::emptyPayload($schema);
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || mb_trim($raw) === "") {
            return self::emptyPayload($schema);
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload) || (string) ($payload["schema"] ?? "") !== $schema) {
            throw new RuntimeException("JSON canônico de telemetria inválido.");
        }
        $payload["events"] = is_array($payload["events"] ?? null)
            ? array_values($payload["events"])
            : [];
        return $payload;
    }

    private static function emptyPayload(string $schema): array
    {
        return [
            "schema" => $schema,
            "timezone" => "America/Cuiaba",
            "retention_days" => 31,
            "events" => [],
        ];
    }

    private static function payloadWrite(string $file, array $payload): bool
    {
        $encoded = json_encode(
            $payload,
            JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR,
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

    private static function viewEvent(array $event): array
    {
        return [
            "evento_id" => (string) ($event["evento_id"] ?? ""),
            "rota" => (string) ($event["rota"] ?? "unknown"),
            "fim_unix_us" => (int) ($event["fim_unix_us"] ?? 0),
            "status_http" => (int) ($event["status_http"] ?? 200),
            "sucesso" => (bool) ($event["sucesso"] ?? false),
        ];
    }

    private static function speedEvent(array $event): array
    {
        return [
            "evento_id" => (string) ($event["evento_id"] ?? ""),
            "rota" => (string) ($event["rota"] ?? "unknown"),
            "fim_unix_us" => (int) ($event["fim_unix_us"] ?? 0),
            "duracao_ns" => max(0, (int) ($event["duracao_ns"] ?? 0)),
            "database_query_count" => max(0, (int) ($event["database_query_count"] ?? 0)),
            "database_query_duration_ns" => max(0, (int) ($event["database_query_duration_ns"] ?? 0)),
            "sucesso" => (bool) ($event["sucesso"] ?? false),
        ];
    }

    private static function legacyEvents(?int $nowUnixUs = null): array
    {
        $file = SupportTelemetryInfrastructureOperations01::telemetry_legacy_file();
        if (!is_file($file)) {
            return [];
        }
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        $minimumUnixUs = $nowUnixUs -
            SupportTelemetryInfrastructureOperations01::telemetry_retention_microseconds();
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
                $event = is_array($decoded)
                    ? SupportTelemetryInfrastructureOperations01::telemetry_normalize_event($decoded)
                    : null;
                if ($event === null ||
                    (int) ($event["fim_unix_us"] ?? 0) < $minimumUnixUs) {
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

    private static function migrateLegacyLocked(?int $nowUnixUs = null): void
    {
        $viewsFile = SupportTelemetryInfrastructureOperations01::telemetry_views_file();
        $speedFile = SupportTelemetryInfrastructureOperations01::telemetry_speed_file();
        if (is_file($viewsFile) && is_file($speedFile)) {
            return;
        }
        $legacy = self::legacyEvents($nowUnixUs);
        $views = [];
        $speed = [];
        foreach ($legacy as $event) {
            $views[] = self::viewEvent($event);
            $speed[] = self::speedEvent($event);
        }
        $updated = (new DateTimeImmutable(
            "now",
            SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz(),
        ))->format(DateTimeImmutable::ATOM);
        if (!is_file($viewsFile)) {
            if (!self::payloadWrite($viewsFile, [
                "schema" => self::viewsSchema(),
                "timezone" => "America/Cuiaba",
                "retention_days" => 31,
                "updated_at_local" => $updated,
                "events" => $views,
            ])) {
                throw new RuntimeException("Falha ao migrar Visualizações para JSON canônico.");
            }
        }
        if (!is_file($speedFile)) {
            if (!self::payloadWrite($speedFile, [
                "schema" => self::speedSchema(),
                "timezone" => "America/Cuiaba",
                "retention_days" => 31,
                "updated_at_local" => $updated,
                "events" => $speed,
            ])) {
                throw new RuntimeException("Falha ao migrar Velocidade para JSON canônico.");
            }
        }
    }

    private static function lockExclusive()
    {
        if (!SupportTelemetryInfrastructureOperations01::telemetry_prepare_storage()) {
            return false;
        }
        $lock = @fopen(self::lockFile(), "c+");
        if (!is_resource($lock) || !@flock($lock, LOCK_EX)) {
            if (is_resource($lock)) {
                @fclose($lock);
            }
            return false;
        }
        return $lock;
    }

    private static function unlock($lock): void
    {
        if (is_resource($lock)) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }
}
