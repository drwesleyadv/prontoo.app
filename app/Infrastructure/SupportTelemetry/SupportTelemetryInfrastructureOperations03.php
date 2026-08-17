<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SupportTelemetry;

use \DateTimeImmutable;
use \RuntimeException;
use \Throwable;

final class SupportTelemetryInfrastructureOperations03
{
    private const DATABASE_SCHEMA = "prontoo.telemetria.registros.v1";
    private const RETENTION_DAYS = 20;
    private const COMPARISON_DAYS = 10;

    private function __construct()
    {
    }

    public static function telemetry_database_records_file(): string
    {
        return \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_storage_dir() . "/database.json";
    }

    public static function telemetry_database_total_records(): array
    {
        $started = microtime(true);
        $pdo = \Prontoo\Infrastructure\DatabaseSchema\DatabaseSchemaInfrastructureOperations01::pdo();
        $statement = $pdo->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
        );
        $tables = [];
        while (($table = $statement->fetchColumn()) !== false) {
            $table = (string) $table;
            if ($table === "" || preg_match('/^[a-zA-Z0-9_]+$/', $table) !== 1) {
                throw new RuntimeException("Tabela inválida durante contagem de registros.");
            }
            $tables[] = $table;
        }
        $total = 0;
        foreach ($tables as $table) {
            $count = $pdo->query("SELECT COUNT(*) FROM `" . $table . "`")->fetchColumn();
            if ($count === false || !is_numeric($count)) {
                throw new RuntimeException("Contagem indisponível para tabela " . $table . ".");
            }
            $total += max(0, (int) $count);
        }
        return [
            "total_records" => $total,
            "table_count" => count($tables),
            "duration_ms" => (int) round(
                (microtime(true) - $started) * 1000,
                0,
                \RoundingMode::HalfAwayFromZero,
            ),
        ];
    }

    private static function telemetry_database_record_payload_schema_valid(array $payload): bool
    {
        return (string) ($payload["schema"] ?? "") === self::DATABASE_SCHEMA;
    }

    private static function telemetry_database_record_normalize_samples(array $rows): array
    {
        $samples = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $capturedAt = (int) ($row["captured_at_unix"] ?? 0);
            $total = (int) ($row["total_records"] ?? -1);
            if ($capturedAt <= 0 || $total < 0) {
                continue;
            }
            $samples[] = [
                "captured_at_unix" => $capturedAt,
                "captured_at_local" => (string) ($row["captured_at_local"] ?? ""),
                "total_records" => $total,
                "delta_records" => (int) ($row["delta_records"] ?? 0),
            ];
        }
        usort(
            $samples,
            static fn(array $a, array $b): int =>
                $a["captured_at_unix"] <=> $b["captured_at_unix"],
        );
        return $samples;
    }

    private static function telemetry_database_record_payload(): array
    {
        $file = self::telemetry_database_records_file();
        if (!is_file($file)) {
            return [];
        }
        $handle = @fopen($file, "rb");
        if (!is_resource($handle)) {
            return [];
        }
        try {
            if (!@flock($handle, LOCK_SH)) {
                return [];
            }
            $raw = stream_get_contents($handle);
        } finally {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
        if (!is_string($raw) || trim($raw) === "") {
            return [];
        }
        $payload = json_decode($raw, true);
        return is_array($payload) && self::telemetry_database_record_payload_schema_valid($payload)
            ? $payload
            : [];
    }

    public static function telemetry_database_record_samples(): array
    {
        $payload = self::telemetry_database_record_payload();
        return self::telemetry_database_record_normalize_samples(
            (array) ($payload["samples"] ?? []),
        );
    }

    public static function telemetry_database_record_snapshot_capture(?int $nowUnix = null): array
    {
        $nowUnix ??= time();
        $nowUnix = max(1, $nowUnix);
        $started = microtime(true);
        try {
            $count = self::telemetry_database_total_records();
            if (
                !\Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_prepare_storage()
            ) {
                throw new RuntimeException("Diretório de telemetria indisponível.");
            }
            $file = self::telemetry_database_records_file();
            $handle = @fopen($file, "c+");
            if (!is_resource($handle)) {
                throw new RuntimeException("Arquivo de contagem de registros indisponível.");
            }
            try {
                if (!@flock($handle, LOCK_EX)) {
                    throw new RuntimeException("Lock da contagem de registros indisponível.");
                }
                rewind($handle);
                $raw = stream_get_contents($handle);
                $payload = [];
                if (is_string($raw) && trim($raw) !== "") {
                    $decoded = json_decode($raw, true);
                    if (!is_array($decoded) || !self::telemetry_database_record_payload_schema_valid($decoded)) {
                        throw new RuntimeException("JSON de contagem de registros inválido.");
                    }
                    $payload = $decoded;
                }
                $samples = self::telemetry_database_record_normalize_samples(
                    (array) ($payload["samples"] ?? []),
                );
                $lastSample = $samples === [] ? null : $samples[array_key_last($samples)];
                $lastTotal = array_key_exists("last_total_records", $payload)
                    ? max(0, (int) $payload["last_total_records"])
                    : (is_array($lastSample) ? (int) $lastSample["total_records"] : null);
                $initialBalance = array_key_exists("initial_balance_records", $payload)
                    ? max(0, (int) $payload["initial_balance_records"])
                    : ($samples === []
                        ? (int) $count["total_records"]
                        : (int) $samples[0]["total_records"]);
                $timezone = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
                $captured = (new DateTimeImmutable("@" . $nowUnix))->setTimezone($timezone);
                $bootstrap = $payload === [] || $lastTotal === null;
                $delta = 0;
                if (!$bootstrap) {
                    $delta = (int) $count["total_records"] - $lastTotal;
                    $samples[] = [
                        "captured_at_unix" => $nowUnix,
                        "captured_at_local" => $captured->format(DateTimeImmutable::ATOM),
                        "total_records" => (int) $count["total_records"],
                        "delta_records" => $delta,
                    ];
                } else {
                    $initialBalance = (int) $count["total_records"];
                }
                $cutoff = $nowUnix - self::RETENTION_DAYS * 86400;
                $samples = array_values(
                    array_filter(
                        $samples,
                        static fn(array $row): bool =>
                            (int) $row["captured_at_unix"] >= $cutoff,
                    ),
                );
                $series = self::telemetry_database_record_series_from_samples($samples, $nowUnix);
                $comparison = self::telemetry_database_record_comparison_10d_from_series($series);
                $encoded = json_encode(
                    [
                        "schema" => self::DATABASE_SCHEMA,
                        "timezone" => "America/Cuiaba",
                        "capture_interval_minutes" => 10,
                        "retention_days" => self::RETENTION_DAYS,
                        "initial_balance_records" => $initialBalance,
                        "last_total_records" => (int) $count["total_records"],
                        "records_10d" => (int) $comparison["current_total"],
                        "previous_records_10d" => (int) $comparison["previous_total"],
                        "variation_pct" => $comparison["variation_pct"],
                        "updated_at_unix" => $nowUnix,
                        "updated_at_local" => $captured->format(DateTimeImmutable::ATOM),
                        "samples" => $samples,
                    ],
                    JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES |
                        JSON_THROW_ON_ERROR,
                ) . PHP_EOL;
                rewind($handle);
                if (
                    !@ftruncate($handle, 0) ||
                    @fwrite($handle, $encoded) !== strlen($encoded) ||
                    !@fflush($handle)
                ) {
                    throw new RuntimeException("Falha ao persistir contagem de registros.");
                }
                @chmod($file, 0640);
            } finally {
                @flock($handle, LOCK_UN);
                @fclose($handle);
            }
            return [
                "ok" => true,
                "bootstrap" => $bootstrap,
                "initial_balance_records" => $initialBalance,
                "total_records" => (int) $count["total_records"],
                "delta_records" => $delta,
                "records_10d" => (int) $comparison["current_total"],
                "previous_records_10d" => (int) $comparison["previous_total"],
                "variation_pct" => $comparison["variation_pct"],
                "table_count" => (int) $count["table_count"],
                "samples_retained" => count($samples),
                "duration_ms" => (int) round(
                    (microtime(true) - $started) * 1000,
                    0,
                    \RoundingMode::HalfAwayFromZero,
                ),
            ];
        } catch (Throwable $error) {
            error_log("[Prontoo telemetry database records] " . $error->getMessage());
            return [
                "ok" => false,
                "note" => mb_substr($error->getMessage(), 0, 220),
                "duration_ms" => (int) round(
                    (microtime(true) - $started) * 1000,
                    0,
                    \RoundingMode::HalfAwayFromZero,
                ),
            ];
        }
    }

    public static function telemetry_database_record_series_from_samples(
        array $samples,
        ?int $nowUnix = null,
    ): array {
        $nowUnix ??= time();
        $nowUnix = max(1, $nowUnix);
        $bucketSeconds = 86400;
        $windowStart = $nowUnix - self::RETENTION_DAYS * $bucketSeconds;
        $timezone = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
        $buckets = [];
        for ($index = 0; $index < self::RETENTION_DAYS; $index++) {
            $startUnix = $windowStart + $index * $bucketSeconds;
            $endUnix = $startUnix + $bucketSeconds;
            $start = (new DateTimeImmutable("@" . $startUnix))->setTimezone($timezone);
            $end = (new DateTimeImmutable("@" . $endUnix))->setTimezone($timezone);
            $buckets[$index] = [
                "key" => (string) $startUnix,
                "ts" => $startUnix,
                "label" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_day_axis_label($end),
                "tooltip" => $start->format("d/m H:i") . " → " . $end->format("d/m H:i"),
                "value" => null,
                "observed" => false,
                "period" => $index < self::COMPARISON_DAYS ? "previous" : "current",
            ];
        }
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                continue;
            }
            $capturedAt = (int) ($sample["captured_at_unix"] ?? 0);
            if ($capturedAt < $windowStart || $capturedAt >= $nowUnix) {
                continue;
            }
            $index = intdiv($capturedAt - $windowStart, $bucketSeconds);
            if ($index < 0 || $index >= self::RETENTION_DAYS) {
                continue;
            }
            if (empty($buckets[$index]["observed"])) {
                $buckets[$index]["value"] = 0;
                $buckets[$index]["observed"] = true;
            }
            $buckets[$index]["value"] += (int) ($sample["delta_records"] ?? 0);
        }
        return array_values($buckets);
    }

    public static function telemetry_database_record_series_20d(?int $nowUnix = null): array
    {
        return self::telemetry_database_record_series_from_samples(
            self::telemetry_database_record_samples(),
            $nowUnix,
        );
    }

    public static function telemetry_database_record_comparison_10d_from_series(array $series): array
    {
        $series = array_values($series);
        $previous = array_slice($series, 0, self::COMPARISON_DAYS);
        $current = array_slice($series, self::COMPARISON_DAYS, self::COMPARISON_DAYS);
        $previousTotal = array_sum(
            array_map(
                static fn(array $row): int => (int) ($row["value"] ?? 0),
                $previous,
            ),
        );
        $currentTotal = array_sum(
            array_map(
                static fn(array $row): int => (int) ($row["value"] ?? 0),
                $current,
            ),
        );
        $previousObservedDays = count(
            array_filter(
                $previous,
                static fn(array $row): bool => !empty($row["observed"]),
            ),
        );
        $currentObservedDays = count(
            array_filter(
                $current,
                static fn(array $row): bool => !empty($row["observed"]),
            ),
        );
        return [
            "previous_total" => $previousTotal,
            "current_total" => $currentTotal,
            "previous_observed_days" => $previousObservedDays,
            "current_observed_days" => $currentObservedDays,
            "variation_pct" =>
                $previousObservedDays === self::COMPARISON_DAYS &&
                $currentObservedDays === self::COMPARISON_DAYS
                    ? \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_percentage_variation(
                        $currentTotal,
                        $previousTotal,
                    )
                    : null,
        ];
    }

    public static function telemetry_database_record_comparison_10d(?int $nowUnix = null): array
    {
        return self::telemetry_database_record_comparison_10d_from_series(
            self::telemetry_database_record_series_20d($nowUnix),
        );
    }
}
