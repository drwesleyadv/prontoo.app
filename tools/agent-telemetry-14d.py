from pathlib import Path

telemetry = Path('app/Support/Telemetry.php')
text = telemetry.read_text(encoding='utf-8')
text = text.replace('$cut = $nowTs - 25 * 3600;', '$cut = $nowTs - 14 * 86400;', 1)
text = text.replace('"retention_hours" => 25,', '"retention_hours" => 336,', 1)
marker = 'function telemetry_route_count_last_days(string $route, int $days = 7): int\n'
helper = '''function telemetry_period_variation(float $current, float $previous): ?float
{
    if ($previous <= 0.0) {
        return $current <= 0.0 ? 0.0 : null;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}
function telemetry_seven_day_comparison(): array
{
    $nowTs = time();
    $currentCut = $nowTs - 7 * 86400;
    $previousCut = $nowTs - 14 * 86400;
    $periods = [
        "current" => ["total" => 0, "total_ms" => 0.0, "landing" => 0],
        "previous" => ["total" => 0, "total_ms" => 0.0, "landing" => 0],
    ];
    $json = telemetry_route_perf_snapshot();
    $buckets = isset($json["buckets"]) && is_array($json["buckets"]) ? $json["buckets"] : [];
    foreach ($buckets as $bucketTs => $routes) {
        $ts = (int) $bucketTs;
        if ($ts < $previousCut || $ts > $nowTs || !is_array($routes)) continue;
        $period = $ts >= $currentCut ? "current" : "previous";
        foreach ($routes as $route => $row) {
            if (!is_array($row)) continue;
            $count = max(0, (int) ($row["count"] ?? 0));
            $periods[$period]["total"] += $count;
            $periods[$period]["total_ms"] += max(0.0, (float) ($row["total_ms"] ?? 0));
            if (telemetry_route_perf_safe_route((string) $route) === "landing") {
                $periods[$period]["landing"] += $count;
            }
        }
    }
    foreach (["current", "previous"] as $period) {
        $count = max(0, (int) $periods[$period]["total"]);
        $periods[$period]["avg_ms"] = $count > 0
            ? round((float) $periods[$period]["total_ms"] / $count, 1)
            : 0.0;
    }
    return [
        "days" => 7,
        "retention_days" => 14,
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

'''
if 'function telemetry_seven_day_comparison()' not in text:
    if marker not in text:
        raise SystemExit('Marcador de telemetria ausente')
    text = text.replace(marker, helper + marker, 1)
telemetry.write_text(text, encoding='utf-8')

admin = Path('app/Admin/AdminPages.php')
text = admin.read_text(encoding='utf-8')
marker = 'function page_status(): void\n'
helper = '''function admin_telemetry_variation_text(?float $variation): string
{
    if ($variation === null) return "sem base comparável nos 7 dias anteriores";
    $signal = $variation > 0 ? "+" : "";
    return $signal . number_format($variation, 1, ",", ".") . "% vs. 7 dias anteriores";
}
function admin_telemetry_seven_day_cards_html(array $comparison): string
{
    $current = isset($comparison["current"]) && is_array($comparison["current"]) ? $comparison["current"] : [];
    $variation = isset($comparison["variation"]) && is_array($comparison["variation"]) ? $comparison["variation"] : [];
    $v = static fn(string $key): ?float => !array_key_exists($key, $variation) || $variation[$key] === null ? null : (float) $variation[$key];
    return stat_card("Requisições", max(0, (int) ($current["total"] ?? 0)), "sync_alt", admin_telemetry_variation_text($v("total"))) .
        stat_card("Tempo Médio", number_format(max(0.0, (float) ($current["avg_ms"] ?? 0.0)), 1, ",", ".") . " ms", "speed", admin_telemetry_variation_text($v("avg_ms"))) .
        stat_card("Carregamentos da landing page", max(0, (int) ($current["landing"] ?? 0)), "web", admin_telemetry_variation_text($v("landing")));
}
'''
if 'function admin_telemetry_seven_day_cards_html' not in text:
    text = text.replace(marker, helper + marker, 1)
start = text.index('function page_status(): void')
end = text.index('function page_admin_operations(): void', start)
section = text[start:end]
a = section.index('    $summary = function_exists("telemetry_route_performance_summary")')
b = section.index('$statusHeader =', a)
section = section[:a] + '    $comparison = function_exists("telemetry_seven_day_comparison")\n        ? telemetry_seven_day_comparison()\n        : ["current" => [], "variation" => []];\n    $overviewCards = admin_telemetry_seven_day_cards_html($comparison);\n' + section[b:]
text = text[:start] + section + text[end:]
old = '''    $performance24h = function_exists("telemetry_route_performance_summary")
        ? telemetry_route_performance_summary(24)
        : ["routes" => [], "total" => 0];
    $requests24h = max(0, (int) ($performance24h["total"] ?? 0));
    $averageResponseMs = max(0.0, (float) ($performance24h["avg_ms"] ?? 0));
    $landingRequests24h = 0;
    foreach ((array) ($performance24h["routes"] ?? []) as $routePerformance) {
        if ((string) ($routePerformance["route"] ?? "") !== "landing") {
            continue;
        }
        $landingRequests24h = max(
            0,
            (int) ($routePerformance["count"] ?? 0),
        );
        break;
    }
'''
new = '    $telemetryComparison = function_exists("telemetry_seven_day_comparison")\n        ? telemetry_seven_day_comparison()\n        : ["current" => [], "variation" => []];\n'
if old not in text:
    raise SystemExit('Bloco do painel ausente')
text = text.replace(old, new, 1)
a = text.index('    $telemetry =\n        \'<div class="stats-grid admin-overview-kpis global-telemetry-grid">\' .')
b = text.index('    $actionsCard =', a)
block = '''    $telemetry =
        '<div class="stats-grid admin-overview-kpis global-telemetry-grid telemetry-comparison-cards">' .
        admin_telemetry_seven_day_cards_html($telemetryComparison) .
        stat_card("Usuários Ativos", $activeUsers24h, "person_check", "últimas 24 horas") .
        "</div>";
'''
text = text[:a] + block + text[b:]
admin.write_text(text, encoding='utf-8')

css = Path('public/assets/design-system.css')
c = css.read_text(encoding='utf-8')
old = 'body.status-public #conteudo>.stat-card .stat-subtitle,body.status-public #conteudo>.stat-card small{display:none!important;}'
new = 'body.status-public #conteudo>.stat-card .stat-subtitle,body.status-public #conteudo>.stat-card small{display:block!important;grid-column:2/4!important;margin:4px 0 0!important;color:var(--md-sys-color-on-surface-variant)!important;font:var(--md-sys-typescale-label-medium)!important;}'
if old in c:
    c = c.replace(old, new, 1)
extra = '\nbody.status-public #conteudo>.stat-card{grid-template-rows:auto auto!important;}\nbody.status-public #conteudo>.stat-card .stat-icon{grid-row:1/3!important;}\nbody.status-public #conteudo>.stat-card .stat-title,body.status-public #conteudo>.stat-card .stat-value{grid-row:1!important;}\nbody.status-public #conteudo>.stat-card .stat-subtitle{grid-row:2!important;}\n.telemetry-comparison-cards>.stat-card .stat-subtitle{font-variant-numeric:tabular-nums;}\n'
if 'telemetry-comparison-cards>.stat-card .stat-subtitle' not in c:
    c += extra
css.write_text(c, encoding='utf-8')
