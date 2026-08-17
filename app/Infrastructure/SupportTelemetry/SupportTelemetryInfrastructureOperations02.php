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

final class SupportTelemetryInfrastructureOperations02
{
    private function __construct()
    {
    }

    public static function telemetry_route_performance_summary(
        int $hours = 240,
        ?int $nowUnixUs = null,
    ): array
    {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        return self::telemetry_route_performance_summary_from_events(
            \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_read_events($nowUnixUs),
            $hours,
            $nowUnixUs,
        );
    }

    public static function telemetry_route_performance_summary_from_events(
        array $events,
        int $hours,
        int $nowUnixUs,
    ): array
    
    {
        $hours = max(1, min(480, $hours));
        $nowUnixUs = max(1, $nowUnixUs);
        $startUs = $nowUnixUs - $hours * 3600 * 1000000;
        $routes = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($finishedUs < $startUs || $finishedUs >= $nowUnixUs) {
                continue;
            }
            if (empty($event["speed_observed"])) {
                continue;
            }
            $route = (string) ($event["rota"] ?? "unknown");
            if (!isset($routes[$route])) {
                $routes[$route] = [
                    "route" => $route,
                    "count" => 0,
                    "duration_ns" => 0,
                    "max_ns" => 0,
                    "errors" => 0,
                ];
            }
            $durationNs = max(0, (int) ($event["duracao_ns"] ?? 0));
            $routes[$route]["count"]++;
            $routes[$route]["duration_ns"] += $durationNs;
            $routes[$route]["max_ns"] = max($routes[$route]["max_ns"], $durationNs);
            if (empty($event["sucesso"])) {
                $routes[$route]["errors"]++;
            }
        }
        $total = 0;
        $totalDurationNs = 0;
        foreach ($routes as &$row) {
            $total += $row["count"];
            $totalDurationNs += $row["duration_ns"];
            $row["avg_ms"] = $row["count"] > 0
                ? ($row["duration_ns"] / $row["count"]) / 1000000
                : 0.0;
            $row["max_ms"] = $row["max_ns"] / 1000000;
            unset($row["duration_ns"], $row["max_ns"]);
        }
        unset($row);
        usort(
            $routes,
            static fn(array $a, array $b): int =>
                ($b["count"] <=> $a["count"])
                    ?: ($a["avg_ms"] <=> $b["avg_ms"])
                    ?: strcmp($a["route"], $b["route"]),
        );
        return [
            "hours" => $hours,
            "total" => $total,
            "avg_ms" => $total > 0 ? ($totalDurationNs / $total) / 1000000 : 0.0,
            "routes" => array_values($routes),
            "updated_at" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_utc_from_unix_microseconds($nowUnixUs),
        ];
    
    }

    public static function telemetry_developer_metrics_summary(
        ?int $nowUnixUs = null,
    ): array {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        return self::telemetry_developer_metrics_summary_from_events(
            \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_read_events($nowUnixUs),
            $nowUnixUs,
        );
    }

    public static function telemetry_developer_metrics_summary_from_events(
        array $events,
        int $nowUnixUs,
    ): array {
        $nowUnixUs = max(1, $nowUnixUs);
        $periodUs = 10 * 86400 * 1000000;
        $currentStartUs = $nowUnixUs - $periodUs;
        $previousStartUs = $currentStartUs - $periodUs;
        $empty = [
            "pages" => 0,
            "database_operations" => 0,
            "landing" => 0,
        ];
        $current = $empty;
        $previous = $empty;
        $seen = [];
        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }
            $eventId = (string) ($event["evento_id"] ?? "");
            if ($eventId !== "") {
                if (isset($seen[$eventId])) {
                    continue;
                }
                $seen[$eventId] = true;
            }
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($finishedUs < $previousStartUs || $finishedUs >= $nowUnixUs) {
                continue;
            }
            $target = $finishedUs >= $currentStartUs ? "current" : "previous";
            if ($target === "current") {
                $current["pages"]++;
                $current["database_operations"] += max(0, (int) ($event["database_query_count"] ?? 0));
                if ((string) ($event["rota"] ?? "") === "landing") {
                    $current["landing"]++;
                }
                continue;
            }
            $previous["pages"]++;
            $previous["database_operations"] += max(0, (int) ($event["database_query_count"] ?? 0));
            if ((string) ($event["rota"] ?? "") === "landing") {
                $previous["landing"]++;
            }
        }
        return [
            "window_mode" => "rolling_20d_10x10",
            "current_start_unix_us" => $currentStartUs,
            "previous_start_unix_us" => $previousStartUs,
            "current_end_unix_us" => $nowUnixUs,
            "current" => $current,
            "previous" => $previous,
            "variations" => [
                "pages_pct" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_percentage_variation(
                    $current["pages"],
                    $previous["pages"],
                ),
                "database_operations_pct" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_percentage_variation(
                    $current["database_operations"],
                    $previous["database_operations"],
                ),
                "landing_pct" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_percentage_variation(
                    $current["landing"],
                    $previous["landing"],
                ),
            ],
        ];
    }

    public static function telemetry_route_requests_series_20d(?int $nowUnixUs = null): array
    {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        $nowUnixUs = max(1, $nowUnixUs);
        $bucketUs = 86400 * 1000000;
        $windowStartUs = $nowUnixUs - 30 * $bucketUs;
        $timezone = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
        $buckets = [];
        for ($index = 0; $index < 30; $index++) {
            $startUs = $windowStartUs + $index * $bucketUs;
            $endUs = $startUs + $bucketUs;
            $start = (new DateTimeImmutable("@" . intdiv($startUs, 1000000)))->setTimezone($timezone);
            $end = (new DateTimeImmutable("@" . intdiv($endUs, 1000000)))->setTimezone($timezone);
            $buckets[$index] = [
                "ts" => intdiv($startUs, 1000000),
                "label" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_day_axis_label($end),
                "tooltip" => $start->format("d/m H:i") . " → " . $end->format("d/m H:i"),
                "value" => 0,
                "period" => $index < 15 ? "previous" : "current",
            ];
        }
        $seen = [];
        foreach (\Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_read_events($nowUnixUs) as $event) {
            $eventId = (string) ($event["evento_id"] ?? "");
            if ($eventId !== "") {
                if (isset($seen[$eventId])) {
                    continue;
                }
                $seen[$eventId] = true;
            }
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($finishedUs < $windowStartUs || $finishedUs >= $nowUnixUs) {
                continue;
            }
            $index = intdiv($finishedUs - $windowStartUs, $bucketUs);
            if ($index >= 0 && $index < 30) {
                $buckets[$index]["value"]++;
            }
        }
        return array_values($buckets);
    }



    public static function telemetry_volume_series_30d(
        string $metric,
        ?int $nowUnixUs = null,
    ): array {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        return self::telemetry_volume_series_30d_from_events(
            $metric,
            \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_read_events(max(1, $nowUnixUs)),
            max(1, $nowUnixUs),
        );
    }

    public static function telemetry_volume_series_30d_from_events(
        string $metric,
        array $events,
        int $nowUnixUs,
    ): array {
        $metric = in_array($metric, ["page_load", "database_queries"], true)
            ? $metric
            : "page_load";
        $bucketUs = 86400 * 1000000;
        $bucketCount = 30;
        $windowEndUs = max(1, $nowUnixUs);
        $windowStartUs = $windowEndUs - $bucketCount * $bucketUs;
        $timezone = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
        $buckets = [];
        for ($index = 0; $index < $bucketCount; $index++) {
            $startUs = $windowStartUs + $index * $bucketUs;
            $endUs = $startUs + $bucketUs;
            $start = (new DateTimeImmutable("@" . intdiv($startUs, 1000000)))->setTimezone($timezone);
            $end = (new DateTimeImmutable("@" . intdiv($endUs, 1000000)))->setTimezone($timezone);
            $buckets[$index] = [
                "ts" => intdiv($startUs, 1000000),
                "label" => \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_day_axis_label($end),
                "tooltip" => $start->format("d/m H:i") . " → " . $end->format("d/m H:i"),
                "value" => 0,
                "observed" => true,
                "period" => $index < 15 ? "previous" : "current",
            ];
        }
        foreach ($events as $event) {
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($finishedUs < $windowStartUs || $finishedUs >= $windowEndUs) {
                continue;
            }
            $index = intdiv($finishedUs - $windowStartUs, $bucketUs);
            if ($index < 0 || $index >= $bucketCount) {
                continue;
            }
            if ($metric === "page_load") {
                $buckets[$index]["value"]++;
                continue;
            }
            if (empty($event["database_query_instrumented"])) {
                continue;
            }
            $buckets[$index]["value"] += max(0, (int) ($event["database_query_count"] ?? 0));
        }
        return array_values($buckets);
    }


    public static function telemetry_latency_series_24h(
        string $metric,
        ?int $nowUnixUs = null,
    ): array {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        return self::telemetry_latency_series_24h_from_events(
            $metric,
            \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_read_events(max(1, $nowUnixUs)),
            max(1, $nowUnixUs),
        );
    }

    public static function telemetry_latency_series_24h_from_events(
        string $metric,
        array $events,
        int $nowUnixUs,
    ): array {
        return self::telemetry_latency_series(
            $metric,
            max(1, $nowUnixUs),
            1440,
            60 * 1000000,
            false,
            $events,
        );
    }

    public static function telemetry_latency_series_30d(
        string $metric,
        ?int $nowUnixUs = null,
    ): array {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        return self::telemetry_latency_series(
            $metric,
            max(1, $nowUnixUs),
            30,
            86400 * 1000000,
            true,
            \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_read_events(max(1, $nowUnixUs)),
        );
    }

    private static function telemetry_latency_series(
        string $metric,
        int $nowUnixUs,
        int $bucketCount,
        int $bucketUs,
        bool $daily,
        array $events,
    ): array {
        $metric = in_array($metric, ["route", "database"], true)
            ? $metric
            : "route";
        $windowEndUs = $daily
            ? $nowUnixUs
            : intdiv($nowUnixUs, $bucketUs) * $bucketUs;
        $windowStartUs = $windowEndUs - $bucketCount * $bucketUs;
        $timezone = \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_cuiaba_tz();
        $buckets = [];
        for ($index = 0; $index < $bucketCount; $index++) {
            $startUs = $windowStartUs + $index * $bucketUs;
            $endUs = $startUs + $bucketUs;
            $start = (new DateTimeImmutable("@" . intdiv($startUs, 1000000)))->setTimezone($timezone);
            $end = (new DateTimeImmutable("@" . intdiv($endUs, 1000000)))->setTimezone($timezone);
            $row = [
                "ts" => intdiv($startUs, 1000000),
                "label" => $daily
                    ? \Prontoo\Infrastructure\SupportTelemetry\SupportTelemetryInfrastructureOperations01::telemetry_day_axis_label($end)
                    : $start->format("H:i"),
                "axis_label" => !$daily && $start->format("i") === "00"
                    ? $start->format("H\\h")
                    : "",
                "tooltip" => $daily
                    ? $start->format("d/m H:i") . " → " . $end->format("d/m H:i")
                    : $start->format("d/m H:i") . " → " . $end->format("H:i"),
                "value" => 0.0,
                "samples" => 0,
                "sum_ns" => 0,
                "observed" => !$daily,
            ];
            if ($daily) {
                $row["period"] = $index < 15 ? "previous" : "current";
            }
            $buckets[$index] = $row;
        }
        foreach ($events as $event) {
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($finishedUs < $windowStartUs || $finishedUs >= $windowEndUs) {
                continue;
            }
            $index = intdiv($finishedUs - $windowStartUs, $bucketUs);
            if ($index < 0 || $index >= $bucketCount) {
                continue;
            }
            if ($metric === "route") {
                if (empty($event["speed_observed"])) {
                    continue;
                }
                $samples = 1;
                $durationNs = max(0, (int) ($event["duracao_ns"] ?? 0));
            } else {
                if (empty($event["database_query_observed"])) {
                    continue;
                }
                $samples = max(0, (int) ($event["database_query_count"] ?? 0));
                $durationNs = max(0, (int) ($event["database_query_duration_ns"] ?? 0));
                if ($samples <= 0) {
                    continue;
                }
            }
            $buckets[$index]["samples"] += $samples;
            $buckets[$index]["sum_ns"] += $durationNs;
        }
        foreach ($buckets as &$row) {
            $samples = max(0, (int) $row["samples"]);
            $sumNs = max(0, (int) $row["sum_ns"]);
            $row["observed"] = !$daily || $samples > 0;
            $row["value"] = $samples > 0
                ? round(($sumNs / $samples) / 1000000, 6, \RoundingMode::HalfAwayFromZero)
                : 0.0;
        }
        unset($row);
        return array_values($buckets);
    }
}
