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

    public static function telemetry_route_performance_summary(int $hours = 240): array
    
    {
        $hours = max(1, min(480, $hours));
        $nowUs = (int) floor(microtime(true) * 1000000);
        $startUs = $nowUs - $hours * 3600 * 1000000;
        $routes = [];
        foreach (telemetry_read_events($nowUs) as $event) {
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($finishedUs < $startUs || $finishedUs >= $nowUs) {
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
                ($b["avg_ms"] <=> $a["avg_ms"]) ?: strcmp($a["route"], $b["route"]),
        );
        return [
            "hours" => $hours,
            "total" => $total,
            "avg_ms" => $total > 0 ? ($totalDurationNs / $total) / 1000000 : 0.0,
            "routes" => array_values($routes),
            "updated_at" => telemetry_utc_from_unix_microseconds($nowUs),
        ];
    
    }

    public static function telemetry_route_requests_series_20d(?int $nowUnixUs = null): array
    
    {
        $nowUnixUs ??= (int) floor(microtime(true) * 1000000);
        $timezone = telemetry_cuiaba_tz();
        $today = (new DateTimeImmutable("@" . intdiv($nowUnixUs, 1000000)))
            ->setTimezone($timezone)
            ->setTime(0, 0);
        $days = [];
        for ($offset = 29; $offset >= 0; $offset--) {
            $day = $today->modify("-" . $offset . " days");
            $key = $day->format("Y-m-d");
            $days[$key] = [
                "ts" => $day->getTimestamp(),
                "label" => telemetry_day_axis_label($day),
                "tooltip" => $day->format("d/m/Y"),
                "value" => 0,
            ];
        }
        foreach (telemetry_read_events($nowUnixUs) as $event) {
            $finishedUs = (int) ($event["fim_unix_us"] ?? 0);
            if ($finishedUs >= $nowUnixUs) {
                continue;
            }
            $key = (new DateTimeImmutable("@" . intdiv($finishedUs, 1000000)))
                ->setTimezone($timezone)
                ->format("Y-m-d");
            if (isset($days[$key])) {
                $days[$key]["value"]++;
            }
        }
        return array_values($days);
    
    }
}
