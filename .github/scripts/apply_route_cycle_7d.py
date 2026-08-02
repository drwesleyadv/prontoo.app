from pathlib import Path
import re

telemetry = Path('app/Support/Telemetry.php')
text = telemetry.read_text(encoding='utf-8')
pattern = re.compile(r'function telemetry_status_cards_snapshot\(\): array\n\{.*?\n\}\n\nfunction telemetry_route_perf_file', re.S)
replacement = '''function telemetry_period_variation_value(float $current, float $previous): ?float
{
    if ($previous <= 0.0) return null;
    return round((($current - $previous) / $previous) * 100, 1);
}
function telemetry_status_cards_snapshot(): array
{
    $tz = telemetry_cuiaba_tz();
    $today = new DateTimeImmutable("today", $tz);
    $currentStart = $today->modify("-6 days");
    $previousStart = $today->modify("-13 days");
    $previousEnd = $today->modify("-7 days");
    $state = [];
    $file = telemetry_route_cycle_file();
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $state = is_string($raw) && trim($raw) !== "" ? json_decode($raw, true) : [];
    }
    $days = is_array($state) && isset($state["days"]) && is_array($state["days"]) ? $state["days"] : [];
    $periods = [
        "current" => ["total" => 0, "total_ns" => 0, "landing" => 0],
        "previous" => ["total" => 0, "total_ns" => 0, "landing" => 0],
    ];
    for ($offset = 0; $offset < 14; $offset++) {
        $day = $previousStart->modify("+" . $offset . " days");
        $key = $day->format("Y-m-d");
        $period = $day >= $currentStart ? "current" : "previous";
        $row = isset($days[$key]) && is_array($days[$key]) ? $days[$key] : [];
        $periods[$period]["total"] += max(0, (int) ($row["completed"] ?? 0));
        $periods[$period]["total_ns"] += max(0, (int) ($row["total_ns"] ?? 0));
        $periods[$period]["landing"] += max(0, (int) ($row["landing"] ?? 0));
    }
    foreach (["current", "previous"] as $period) {
        $count = max(0, (int) $periods[$period]["total"]);
        $periods[$period]["avg_ms"] = $count > 0
            ? round(((float) $periods[$period]["total_ns"] / $count) / 1000000, 3)
            : 0.0;
    }
    return [
        "source" => "route_cycles_v2",
        "period" => "rolling_7_civil_days",
        "timezone" => $tz->getName(),
        "current_start" => $currentStart->format("Y-m-d"),
        "current_end" => $today->format("Y-m-d"),
        "previous_start" => $previousStart->format("Y-m-d"),
        "previous_end" => $previousEnd->format("Y-m-d"),
        "current" => $periods["current"],
        "previous" => $periods["previous"],
        "variation" => [
            "total" => telemetry_period_variation_value((float) $periods["current"]["total"], (float) $periods["previous"]["total"]),
            "avg_ms" => telemetry_period_variation_value((float) $periods["current"]["avg_ms"], (float) $periods["previous"]["avg_ms"]),
            "landing" => telemetry_period_variation_value((float) $periods["current"]["landing"], (float) $periods["previous"]["landing"]),
        ],
        "last_finished_us" => max(0, (int) ($state["updated_at_us"] ?? 0)),
    ];
}

function telemetry_route_perf_file'''
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise SystemExit('Snapshot atual não localizado.')
telemetry.write_text(text, encoding='utf-8')

admin = Path('app/Admin/AdminPages.php')
text = admin.read_text(encoding='utf-8')
pattern = re.compile(r'function admin_telemetry_today_cards_html\(array \$snapshot\): string\n\{.*?\n\}', re.S)
replacement = '''function admin_telemetry_variation_text(?float $variation): string
{
    if ($variation === null) return "sem base comparável no período anterior";
    $signal = $variation > 0 ? "+" : "";
    return $signal . number_format($variation, 1, ",", ".") . "% vs. 7 dias anteriores";
}
function admin_telemetry_today_cards_html(array $snapshot): string
{
    $current = isset($snapshot["current"]) && is_array($snapshot["current"]) ? $snapshot["current"] : [];
    $variation = isset($snapshot["variation"]) && is_array($snapshot["variation"]) ? $snapshot["variation"] : [];
    $detail = static function (string $key) use ($variation): string {
        $value = array_key_exists($key, $variation) && $variation[$key] !== null ? (float) $variation[$key] : null;
        return "últimos 7 dias · " . admin_telemetry_variation_text($value);
    };
    return stat_card("Requisições", max(0, (int) ($current["total"] ?? 0)), "sync_alt", $detail("total")) .
        stat_card("Tempo Médio", number_format(max(0.0, (float) ($current["avg_ms"] ?? 0.0)), 3, ",", ".") . " ms", "speed", $detail("avg_ms")) .
        stat_card("Carregamentos da landing page", max(0, (int) ($current["landing"] ?? 0)), "web", $detail("landing"));
}'''
text, count = pattern.subn(replacement, text, count=1)
if count != 1:
    raise SystemExit('Helper atual dos cards não localizado.')
text = text.replace('<div class="status-section-heading"><div><span>Hoje</span><h2 id="status-summary-title">Resumo operacional</h2></div><p>Medição exata dos ciclos concluídos no dia atual.</p></div>', '<div class="status-section-heading"><div><span>Últimos 7 dias</span><h2 id="status-summary-title">Resumo operacional</h2></div><p>Comparação automática com os 7 dias anteriores.</p></div>', 1)
admin.write_text(text, encoding='utf-8')
