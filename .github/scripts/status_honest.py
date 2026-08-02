from pathlib import Path
import re

telemetry = Path('app/Support/Telemetry.php')
text = telemetry.read_text(encoding='utf-8')
payload_anchor = '        $payload = [\n            "timezone" => "America/Cuiaba",'
source_block = '''        $statusSource = isset($json["status_card_source_v1"]) && is_array($json["status_card_source_v1"])
            ? $json["status_card_source_v1"]
            : [];
        if ((int) ($statusSource["version"] ?? 0) !== 1) {
            $statusSource = ["version" => 1, "started_at" => $nowTs, "timezone" => "America/Cuiaba", "days" => []];
        }
        $statusDays = isset($statusSource["days"]) && is_array($statusSource["days"]) ? $statusSource["days"] : [];
        foreach (array_keys($statusDays) as $key) {
            if ((string) $key < $dailyCut) unset($statusDays[$key]);
        }
        $statusRow = isset($statusDays[$dayKey]) && is_array($statusDays[$dayKey])
            ? $statusDays[$dayKey]
            : ["count" => 0, "total_ms" => 0.0, "landing" => 0, "first_ts" => $nowTs, "last_ts" => $nowTs];
        $statusRow["count"] = max(0, (int) ($statusRow["count"] ?? 0)) + 1;
        $statusRow["total_ms"] = max(0.0, (float) ($statusRow["total_ms"] ?? 0.0)) + $elapsed;
        $statusRow["landing"] = max(0, (int) ($statusRow["landing"] ?? 0)) + ($route === "landing" ? 1 : 0);
        $statusRow["first_ts"] = min(max(1, (int) ($statusRow["first_ts"] ?? $nowTs)), $nowTs);
        $statusRow["last_ts"] = max((int) ($statusRow["last_ts"] ?? 0), $nowTs);
        $statusDays[$dayKey] = $statusRow;
        ksort($statusDays, SORT_STRING);
        $statusSource["days"] = $statusDays;
        $statusSource["last_event_at"] = $nowTs;

'''
if '"status_card_source_v1" => $statusSource,' not in text:
    if payload_anchor not in text:
        raise SystemExit('Âncora do payload não localizada.')
    text = text.replace(payload_anchor, source_block + payload_anchor, 1)
    text = text.replace('            "daily_routes" => $dailyRoutes,', '            "daily_routes" => $dailyRoutes,\n            "status_card_source_v1" => $statusSource,', 1)

pattern = re.compile(r'function telemetry_seven_day_comparison\(\): array\n\{.*?\n\}\n\nfunction telemetry_route_count_last_days', re.S)
replacement = '''function telemetry_seven_day_comparison(): array
{
    $tz = telemetry_cuiaba_tz();
    $today = new DateTimeImmutable("today", $tz);
    $currentStart = $today->modify("-7 days");
    $currentEnd = $today->modify("-1 day");
    $previousStart = $today->modify("-14 days");
    $previousEnd = $today->modify("-8 days");
    $json = telemetry_route_perf_snapshot();
    $source = isset($json["status_card_source_v1"]) && is_array($json["status_card_source_v1"]) ? $json["status_card_source_v1"] : [];
    $startedAt = max(0, (int) ($source["started_at"] ?? 0));
    $days = isset($source["days"]) && is_array($source["days"]) ? $source["days"] : [];
    $firstCompleteDay = $startedAt > 0
        ? (new DateTimeImmutable("@" . $startedAt))->setTimezone($tz)->modify("tomorrow")->setTime(0, 0)
        : null;
    $periods = [
        "current" => ["total" => 0, "total_ms" => 0.0, "landing" => 0, "coverage_days" => 0],
        "previous" => ["total" => 0, "total_ms" => 0.0, "landing" => 0, "coverage_days" => 0],
    ];
    foreach (["previous" => $previousStart, "current" => $currentStart] as $period => $start) {
        for ($offset = 0; $offset < 7; $offset++) {
            $day = $start->modify("+" . $offset . " days");
            if (!$firstCompleteDay instanceof DateTimeImmutable || $day < $firstCompleteDay || $day >= $today) continue;
            $periods[$period]["coverage_days"]++;
            $key = $day->format("Y-m-d");
            $row = isset($days[$key]) && is_array($days[$key]) ? $days[$key] : [];
            $periods[$period]["total"] += max(0, (int) ($row["count"] ?? 0));
            $periods[$period]["total_ms"] += max(0.0, (float) ($row["total_ms"] ?? 0.0));
            $periods[$period]["landing"] += max(0, (int) ($row["landing"] ?? 0));
        }
        $count = max(0, (int) $periods[$period]["total"]);
        $periods[$period]["avg_ms"] = $count > 0 ? round((float) $periods[$period]["total_ms"] / $count, 1) : 0.0;
    }
    $currentReady = (int) $periods["current"]["coverage_days"] === 7;
    $comparisonReady = $currentReady && (int) $periods["previous"]["coverage_days"] === 7;
    return [
        "days" => 7,
        "source" => "status_card_source_v1",
        "period_mode" => "completed_civil_days",
        "timezone" => $tz->getName(),
        "current_start" => $currentStart->format("Y-m-d"),
        "current_end" => $currentEnd->format("Y-m-d"),
        "previous_start" => $previousStart->format("Y-m-d"),
        "previous_end" => $previousEnd->format("Y-m-d"),
        "current_ready" => $currentReady,
        "comparison_ready" => $comparisonReady,
        "current" => $periods["current"],
        "previous" => $periods["previous"],
        "variation" => [
            "total" => $comparisonReady ? telemetry_period_variation((float) $periods["current"]["total"], (float) $periods["previous"]["total"]) : null,
            "avg_ms" => $comparisonReady ? telemetry_period_variation((float) $periods["current"]["avg_ms"], (float) $periods["previous"]["avg_ms"]) : null,
            "landing" => $comparisonReady ? telemetry_period_variation((float) $periods["current"]["landing"], (float) $periods["previous"]["landing"]) : null,
        ],
        "updated_at" => (string) ($json["updated_at"] ?? ""),
    ];
}

function telemetry_route_count_last_days'''
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise SystemExit('Função comparativa não localizada.')
telemetry.write_text(text, encoding='utf-8')

admin = Path('app/Admin/AdminPages.php')
text = admin.read_text(encoding='utf-8')
pattern = re.compile(r'function admin_telemetry_variation_text\(\?float \$variation\): string\n\{.*?\n\}\nfunction admin_telemetry_seven_day_cards_html\(array \$comparison\): string\n\{.*?\n\}', re.S)
replacement = '''function admin_telemetry_variation_text(?float $variation, bool $comparisonReady): string
{
    if (!$comparisonReady) return "comparação indisponível até haver 14 dias completos";
    if ($variation === null) return "sem base matemática no período anterior";
    $signal = $variation > 0 ? "+" : "";
    return $signal . number_format($variation, 1, ",", ".") . "% vs. 7 dias completos anteriores";
}
function admin_telemetry_seven_day_cards_html(array $comparison): string
{
    $current = isset($comparison["current"]) && is_array($comparison["current"]) ? $comparison["current"] : [];
    $variation = isset($comparison["variation"]) && is_array($comparison["variation"]) ? $comparison["variation"] : [];
    $coverage = max(0, min(7, (int) ($current["coverage_days"] ?? 0)));
    $currentReady = !empty($comparison["current_ready"]);
    $comparisonReady = !empty($comparison["comparison_ready"]);
    $coverageText = $currentReady ? "7 dias civis completos" : $coverage . " de 7 dias completos coletados";
    $detail = static function (?float $value) use ($coverageText, $comparisonReady): string {
        return $coverageText . " · " . admin_telemetry_variation_text($value, $comparisonReady);
    };
    return stat_card("Requisições", max(0, (int) ($current["total"] ?? 0)), "sync_alt", $detail(array_key_exists("total", $variation) && $variation["total"] !== null ? (float) $variation["total"] : null)) .
        stat_card("Tempo Médio", number_format(max(0.0, (float) ($current["avg_ms"] ?? 0.0)), 1, ",", ".") . " ms", "speed", $detail(array_key_exists("avg_ms", $variation) && $variation["avg_ms"] !== null ? (float) $variation["avg_ms"] : null)) .
        stat_card("Carregamentos da landing page", max(0, (int) ($current["landing"] ?? 0)), "web", $detail(array_key_exists("landing", $variation) && $variation["landing"] !== null ? (float) $variation["landing"] : null));
}'''
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise SystemExit('Helpers dos cards não localizados.')
admin.write_text(text, encoding='utf-8')
