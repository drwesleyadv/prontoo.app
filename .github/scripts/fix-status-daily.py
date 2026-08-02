from pathlib import Path
import re

path = Path('app/Support/Telemetry.php')
text = path.read_text(encoding='utf-8')
pattern = re.compile(r'function telemetry_seven_day_comparison\(\): array\n\{.*?\n\}\n\nfunction telemetry_route_count_last_days', re.S)
replacement = '''function telemetry_seven_day_comparison(): array
{
    $tz = telemetry_cuiaba_tz();
    $today = new DateTimeImmutable("today", $tz);
    $currentStart = $today->modify("-6 days");
    $previousStart = $today->modify("-13 days");
    $previousEnd = $today->modify("-7 days");
    $periods = [
        "current" => ["total" => 0, "total_ms" => 0.0, "landing" => 0],
        "previous" => ["total" => 0, "total_ms" => 0.0, "landing" => 0],
    ];
    $json = telemetry_route_perf_snapshot();
    $dailyRequests = isset($json["daily_requests"]) && is_array($json["daily_requests"]) ? $json["daily_requests"] : [];
    $dailyRoutes = isset($json["daily_routes"]) && is_array($json["daily_routes"]) ? $json["daily_routes"] : [];
    $bucketFallback = [];
    $buckets = isset($json["buckets"]) && is_array($json["buckets"]) ? $json["buckets"] : [];
    foreach ($buckets as $bucketTs => $routes) {
        $ts = (int) $bucketTs;
        if ($ts <= 0 || !is_array($routes)) continue;
        $key = (new DateTimeImmutable("@" . $ts))->setTimezone($tz)->format("Y-m-d");
        if (!isset($bucketFallback[$key])) $bucketFallback[$key] = ["total" => 0, "total_ms" => 0.0, "landing" => 0];
        foreach ($routes as $route => $row) {
            if (!is_array($row)) continue;
            $count = max(0, (int) ($row["count"] ?? 0));
            $bucketFallback[$key]["total"] += $count;
            $bucketFallback[$key]["total_ms"] += max(0.0, (float) ($row["total_ms"] ?? 0));
            if (telemetry_route_perf_safe_route((string) $route) === "landing") $bucketFallback[$key]["landing"] += $count;
        }
    }
    for ($offset = 0; $offset < 14; $offset++) {
        $day = $previousStart->modify("+" . $offset . " days");
        $key = $day->format("Y-m-d");
        $period = $day >= $currentStart ? "current" : "previous";
        $requestRow = isset($dailyRequests[$key]) && is_array($dailyRequests[$key]) ? $dailyRequests[$key] : null;
        if ($requestRow !== null) {
            $periods[$period]["total"] += max(0, (int) ($requestRow["count"] ?? 0));
            $periods[$period]["total_ms"] += max(0.0, (float) ($requestRow["total_ms"] ?? 0));
        } else {
            $periods[$period]["total"] += max(0, (int) ($bucketFallback[$key]["total"] ?? 0));
            $periods[$period]["total_ms"] += max(0.0, (float) ($bucketFallback[$key]["total_ms"] ?? 0));
        }
        $routeRow = isset($dailyRoutes[$key]) && is_array($dailyRoutes[$key]) ? $dailyRoutes[$key] : null;
        $periods[$period]["landing"] += $routeRow !== null ? max(0, (int) ($routeRow["landing"] ?? 0)) : max(0, (int) ($bucketFallback[$key]["landing"] ?? 0));
    }
    foreach (["current", "previous"] as $period) {
        $count = max(0, (int) $periods[$period]["total"]);
        $periods[$period]["avg_ms"] = $count > 0 ? round((float) $periods[$period]["total_ms"] / $count, 1) : 0.0;
    }
    return [
        "days" => 7,
        "retention_days" => 14,
        "period_mode" => "civil_days",
        "timezone" => $tz->getName(),
        "current_start" => $currentStart->format("Y-m-d"),
        "current_end" => $today->format("Y-m-d"),
        "previous_start" => $previousStart->format("Y-m-d"),
        "previous_end" => $previousEnd->format("Y-m-d"),
        "current" => $periods["current"],
        "previous" => $periods["previous"],
        "variation" => [
            "total" => telemetry_period_variation((float) $periods["current"]["total"], (float) $periods["previous"]["total"]),
            "avg_ms" => telemetry_period_variation((float) $periods["current"]["avg_ms"], (float) $periods["previous"]["avg_ms"]),
            "landing" => telemetry_period_variation((float) $periods["current"]["landing"], (float) $periods["previous"]["landing"]),
        ],
        "updated_at" => (string) ($json["updated_at"] ?? ""),
    ];
}

function telemetry_route_count_last_days'''
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise SystemExit('Função comparativa atual não localizada.')
path.write_text(text, encoding='utf-8')
