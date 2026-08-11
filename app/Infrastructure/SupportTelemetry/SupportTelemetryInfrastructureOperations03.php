<?php
declare(strict_types=1);

namespace Prontoo\Infrastructure\SupportTelemetry;

use \DateTimeImmutable;
use \RuntimeException;
use \Throwable;

final class SupportTelemetryInfrastructureOperations03
{
    private function __construct()
    {
    }

    public static function telemetry_database_records_file(): string
    {
        return \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_storage_dir() . "/database-record-counts.json";
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

    public static function telemetry_database_record_samples(): array
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
        if (
            !is_array($payload) ||
            (string) ($payload["schema"] ?? "") !== "prontoo.telemetria.registros.v1"
        ) {
            return [];
        }
        $samples = [];
        foreach ((array) ($payload["samples"] ?? []) as $sample) {
            if (!is_array($sample)) {
                continue;
            }
            $capturedAt = (int) ($sample["captured_at_unix"] ?? 0);
            $total = (int) ($sample["total_records"] ?? -1);
            if ($capturedAt <= 0 || $total < 0) {
                continue;
            }
            $samples[] = [
                "captured_at_unix" => $capturedAt,
                "captured_at_local" => (string) ($sample["captured_at_local"] ?? ""),
                "total_records" => $total,
                "delta_records" => (int) ($sample["delta_records"] ?? 0),
            ];
        }
        usort(
            $samples,
            static fn(array $a, array $b): int =>
                $a["captured_at_unix"] <=> $b["captured_at_unix"],
        );
        return $samples;
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
                $payload = ["samples" => []];
                if (is_string($raw) && trim($raw) !== "") {
                    $decoded = json_decode($raw, true);
                    if (
                        !is_array($decoded) ||
                        (string) ($decoded["schema"] ?? "") !== "prontoo.telemetria.registros.v1"
                    ) {
                        throw new RuntimeException("JSON de contagem de registros inválido.");
                    }
                    $payload = $decoded;
                }
                $samples = [];
                foreach ((array) ($payload["samples"] ?? []) as $row) {
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
                $previousTotal = $samples === []
                    ? null
                    : (int) $samples[array_key_last($samples)]["total_records"];
                $timezone = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
                $captured = (new DateTimeImmutable("@" . $nowUnix))->setTimezone($timezone);
                $sample = [
                    "captured_at_unix" => $nowUnix,
                    "captured_at_local" => $captured->format(DateTimeImmutable::ATOM),
                    "total_records" => (int) $count["total_records"],
                    "delta_records" => $previousTotal === null
                        ? 0
                        : (int) $count["total_records"] - $previousTotal,
                ];
                $samples[] = $sample;
                $cutoff = $nowUnix - 31 * 86400;
                $samples = array_values(
                    array_filter(
                        $samples,
                        static fn(array $row): bool =>
                            (int) $row["captured_at_unix"] >= $cutoff,
                    ),
                );
                $encoded = json_encode(
                    [
                        "schema" => "prontoo.telemetria.registros.v1",
                        "timezone" => "America/Cuiaba",
                        "retention_days" => 31,
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
                "total_records" => $sample["total_records"],
                "delta_records" => $sample["delta_records"],
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
        $timezone = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
        $today = (new DateTimeImmutable("@" . max(1, $nowUnix)))
            ->setTimezone($timezone)
            ->setTime(0, 0);
        $days = [];
        for ($offset = 30; $offset >= 1; $offset--) {
            $day = $today->modify("-" . $offset . " days");
            $key = $day->format("Y-m-d");
            $days[$key] = [
                "key" => $key,
                "ts" => $day->getTimestamp(),
                "label" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_day_axis_label($day),
                "tooltip" => $day->format("d/m/Y"),
                "value" => null,
                "observed" => false,
                "period" => $offset > 15 ? "previous" : "current",
            ];
        }
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                continue;
            }
            $capturedAt = (int) ($sample["captured_at_unix"] ?? 0);
            if ($capturedAt <= 0) {
                continue;
            }
            $key = (new DateTimeImmutable("@" . $capturedAt))
                ->setTimezone($timezone)
                ->format("Y-m-d");
            if (isset($days[$key])) {
                if (empty($days[$key]["observed"])) {
                    $days[$key]["value"] = 0;
                    $days[$key]["observed"] = true;
                }
                $days[$key]["value"] += (int) ($sample["delta_records"] ?? 0);
            }
        }
        return array_values($days);
    }

    public static function telemetry_database_record_series_30d(?int $nowUnix = null): array
    {
        return self::telemetry_database_record_series_from_samples(
            self::telemetry_database_record_samples(),
            $nowUnix,
        );
    }

    public static function telemetry_series_comparison_15d(array $series): array
    {
        $series = array_values($series);
        $previous = array_slice($series, 0, 15);
        $current = array_slice($series, 15, 15);
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
            "variation_pct" => $previousObservedDays === 15 && $currentObservedDays === 15
                ? \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_percentage_variation(
                    $currentTotal,
                    $previousTotal,
                )
                : null,
        ];
    }
}
