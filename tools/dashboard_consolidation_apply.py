from pathlib import Path
import json
import re
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.9"
PREVIOUS = "1.7.30.8"
BUILD = "1.7.30.9-developer-dashboard-consolidation"
NOW = datetime.now(timezone.utc).replace(microsecond=0).isoformat()
NOW_UNIX = int(time.time())


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    target = ROOT / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding="utf-8")


def replace_once(path: str, old: str, new: str) -> None:
    content = read(path)
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{path}: expected one occurrence, found {count}: {old[:120]}")
    write(path, content.replace(old, new, 1))


replace_once(
    "app/prontoo.php",
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.8";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.9";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-speed-label-no-dual-legend-one-row";',
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-speed-and-volume-no-dual-legend-one-row";',
)
replace_once(
    "app/prontoo.php",
    '    "admin_telemetry" => ["label" => "Telemetria", "icon" => "monitoring"],\n',
    "",
)
replace_once(
    "app/Runtime/Runner.php",
    '        "admin_telemetry",\n',
    "",
)
replace_once(
    "app/Support/ModuleLoader.php",
    "        'admin_health' => $admin, 'admin_performance' => $admin, 'admin_telemetry' => $admin,\n",
    "        'admin_health' => $admin, 'admin_performance' => $admin,\n",
)

admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)

telemetry_start = admin.find("function admin_telemetry_window_hours(): int\n")
telemetry_end = admin.find("function page_admin_performance(): void\n", telemetry_start)
if telemetry_start < 0 or telemetry_end < 0:
    raise RuntimeError("developer telemetry function boundary not found")
admin = admin[:telemetry_start] + admin[telemetry_end:]

dual_count_function = r'''function admin_metric_dual_count_chart(
    string $title,
    array $requestSeries,
    array $recordSeries,
    string $iconName = "monitoring",
): string {
    $requestValues = array_map(
        static fn($row) => max(0.0, (float) ($row["value"] ?? 0)),
        $requestSeries,
    );
    $recordValues = array_map(
        static fn($row) => max(0.0, (float) ($row["value"] ?? 0)),
        $recordSeries,
    );
    if (!$requestValues) {
        $requestValues = [0.0];
    }
    if (!$recordValues) {
        $recordValues = [0.0];
    }
    $max = max(max($requestValues), max($recordValues));
    if ($max <= 0) {
        $max = 1.0;
    }
    $w = 720;
    $h = 190;
    $padL = 34;
    $padR = 14;
    $padT = 18;
    $padB = 32;
    $plotW = $w - $padL - $padR;
    $plotH = $h - $padT - $padB;
    $baseline = $padT + $plotH;
    $makePoints = static function (array $series) use (
        $padL,
        $plotW,
        $padT,
        $plotH,
        $max,
    ): array {
        $n = count($series);
        $points = [];
        foreach ($series as $idx => $row) {
            $value = max(0.0, (float) ($row["value"] ?? 0));
            $x = $padL + ($n <= 1 ? 0 : $idx * ($plotW / ($n - 1)));
            $y = $padT + $plotH - ($value / $max) * $plotH;
            $points[] = [
                round($x, 2),
                round($y, 2),
                $value,
                (string) ($row["label"] ?? ""),
                (string) ($row["tooltip"] ?? ($row["label"] ?? "")),
            ];
        }
        return $points;
    };
    $path = static function (array $points): string {
        $value = "";
        foreach ($points as $index => $point) {
            $value .=
                ($index === 0 ? "M" : "L") .
                $point[0] .
                " " .
                $point[1] .
                " ";
        }
        return trim($value);
    };
    $fill = static function (
        string $pathValue,
        array $points,
        float $baselineValue,
    ): string {
        if (!$points || $pathValue === "") {
            return "";
        }
        $first = $points[0];
        $last = $points[count($points) - 1];
        return $pathValue .
            " L " .
            $last[0] .
            " " .
            round($baselineValue, 2) .
            " L " .
            $first[0] .
            " " .
            round($baselineValue, 2) .
            " Z";
    };
    $requestPoints = $makePoints($requestSeries);
    $recordPoints = $makePoints($recordSeries);
    $requestPath = $path($requestPoints);
    $recordPath = $path($recordPoints);
    $requestFill = $fill($requestPath, $requestPoints, $baseline);
    $recordFill = $fill($recordPath, $recordPoints, $baseline);
    $grid = "";
    for ($i = 0; $i <= 3; $i++) {
        $gridY = $padT + $i * ($plotH / 3);
        $gridValue = $max - ($max / 3) * $i;
        $grid .=
            '<line x1="' .
            $padL .
            '" y1="' .
            round($gridY, 2) .
            '" x2="' .
            ($w - $padR) .
            '" y2="' .
            round($gridY, 2) .
            '" class="metric-grid-line"/><text x="6" y="' .
            round($gridY + 4, 2) .
            '" class="metric-axis-label">' .
            e(number_format($gridValue, 0, ",", ".")) .
            "</text>";
    }
    $ticks = "";
    $pointCount = count($requestPoints);
    $tickEvery = max(1, (int) ceil(max(1, $pointCount) / 8));
    foreach ($requestPoints as $index => $point) {
        if ($index % $tickEvery === 0 || $index === $pointCount - 1) {
            $ticks .=
                '<text x="' .
                $point[0] .
                '" y="' .
                ($h - 8) .
                '" class="metric-axis-label metric-x-label">' .
                e($point[3]) .
                "</text>";
        }
    }
    $hover = "";
    foreach ($requestPoints as $point) {
        $hover .=
            '<circle cx="' .
            $point[0] .
            '" cy="' .
            $point[1] .
            '" r="4.5" class="metric-chart-hit"><title>' .
            e(
                $point[4] .
                    " · Requisições " .
                    admin_metric_value_label($point[2], "count"),
            ) .
            "</title></circle>";
    }
    foreach ($recordPoints as $point) {
        $hover .=
            '<circle cx="' .
            $point[0] .
            '" cy="' .
            $point[1] .
            '" r="3.8" class="metric-chart-hit"><title>' .
            e(
                $point[4] .
                    " · Registros " .
                    admin_metric_value_label($point[2], "count"),
            ) .
            "</title></circle>";
    }
    $requestTotal = (int) round(array_sum($requestValues));
    $recordTotal = (int) round(array_sum($recordValues));
    $pills =
        '<span class="metric-count-pill is-requests" title="Requisições nos últimos 30 dias: ' .
        e(n($requestTotal)) .
        '"><i class="metric-series-key" aria-hidden="true"></i><small>Requisições</small><b>' .
        e(n($requestTotal)) .
        '</b></span><span class="metric-count-pill is-records" title="Registros nos últimos 30 dias: ' .
        e(n($recordTotal)) .
        '"><i class="metric-series-key" aria-hidden="true"></i><small>Registros</small><b>' .
        e(n($recordTotal)) .
        "</b></span>";
    $lastRequest = end($requestPoints);
    $lastRecord = end($recordPoints);
    $requestCircle = $lastRequest
        ? '<circle cx="' .
            $lastRequest[0] .
            '" cy="' .
            $lastRequest[1] .
            '" r="3.6" class="metric-chart-dot metric-chart-dot-requests"><title>' .
            e(
                $lastRequest[4] .
                    " · Requisições " .
                    admin_metric_value_label($lastRequest[2], "count"),
            ) .
            "</title></circle>"
        : "";
    $recordCircle = $lastRecord
        ? '<circle cx="' .
            $lastRecord[0] .
            '" cy="' .
            $lastRecord[1] .
            '" r="3.2" class="metric-chart-dot metric-chart-dot-records"><title>' .
            e(
                $lastRecord[4] .
                    " · Registros " .
                    admin_metric_value_label($lastRecord[2], "count"),
            ) .
            "</title></circle>"
        : "";
    $summary =
        $title .
        ": " .
        n($requestTotal) .
        " requisições e " .
        n($recordTotal) .
        " registros nos últimos 30 dias.";
    return '<article class="metric-line-chart metric-area-chart metric-dual-count-chart" data-ds-card="admin-dual-count-chart" aria-label="' .
        e($title) .
        '"><header><div class="metric-chart-title">' .
        icon($iconName) .
        "<div><h3>" .
        e($title) .
        '</h3></div></div><div class="metric-chart-value"><div class="metric-chart-pills">' .
        $pills .
        '</div></div></header><p class="sr-only">' .
        e($summary) .
        '</p><svg viewBox="0 0 ' .
        $w .
        " " .
        $h .
        '" aria-hidden="true" focusable="false"><g>' .
        $grid .
        "</g>" .
        ($requestFill !== ""
            ? '<path d="' .
                e($requestFill) .
                '" class="metric-chart-fill-requests"/>'
            : "") .
        ($recordFill !== ""
            ? '<path d="' .
                e($recordFill) .
                '" class="metric-chart-fill-records"/>'
            : "") .
        ($requestPath !== ""
            ? '<path d="' .
                e($requestPath) .
                '" class="metric-chart-line-requests"/>'
            : "") .
        ($recordPath !== ""
            ? '<path d="' .
                e($recordPath) .
                '" class="metric-chart-line-records"/>'
            : "") .
        $requestCircle .
        $recordCircle .
        $hover .
        $ticks .
        "</svg></article>";
}
'''
sequence_marker = "function admin_global_sequence_series_30d(): array\n"
if admin.count(sequence_marker) != 1:
    raise RuntimeError("sequence chart boundary not found")
admin = admin.replace(sequence_marker, dual_count_function + sequence_marker, 1)

obsolete_start = admin.find("function admin_sequence_average(array $series, int $days): float\n")
obsolete_end = admin.find("function admin_maestro_health_time_label(", obsolete_start)
if obsolete_start < 0 or obsolete_end < 0:
    raise RuntimeError("obsolete bar chart boundary not found")
admin = admin[:obsolete_start] + admin[obsolete_end:]

charts_start = admin.find("function admin_global_perf_charts_html(): string\n")
charts_end = admin.find("function page_admin_operations(): void\n", charts_start)
if charts_start < 0 or charts_end < 0:
    raise RuntimeError("global performance chart boundary not found")
charts_function = r'''function admin_global_perf_charts_html(): string
{
    $response = admin_global_metric_series_24h("query_ms");
    $load = admin_global_metric_series_24h("load");
    $records = admin_global_sequence_series_30d();
    $requests = function_exists("telemetry_route_requests_series_30d")
        ? telemetry_route_requests_series_30d()
        : [];
    return '<div class="global-performance-charts global-area-charts" data-admin-global-charts data-refresh-ms="900000" data-chart-window="5min">' .
        admin_metric_dual_area_chart("Velocidade", $load, $response, "speed") .
        admin_metric_dual_count_chart(
            "Requisições e registros",
            $requests,
            $records,
            "monitoring",
        ) .
        "</div>";
}
'''
admin = admin[:charts_start] + charts_function + admin[charts_end:]

old_metrics = r'''    $activeClinics7d = $qInt(
        "SELECT COUNT(DISTINCT clinic_id) FROM pi_audit WHERE clinic_id IS NOT NULL AND created_at>=DATE_SUB(NOW(), INTERVAL 7 DAY) $modelAuditWhere",
    );
    $landingLoads7d = function_exists("telemetry_route_count_last_days")
        ? telemetry_route_count_last_days("landing", 7)
        : 0;
    $performance24h = function_exists("telemetry_route_performance_summary")
        ? telemetry_route_performance_summary(24)
        : ["avg_ms" => 0.0];
    $averageResponseMs = max(0.0, (float) ($performance24h["avg_ms"] ?? 0));
'''
new_metrics = r'''    $performance24h = function_exists("telemetry_route_performance_summary")
        ? telemetry_route_performance_summary(24)
        : ["routes" => [], "total" => 0];
    $requests24h = max(0, (int) ($performance24h["total"] ?? 0));
    $landingAverageMs = 0.0;
    foreach ((array) ($performance24h["routes"] ?? []) as $routePerformance) {
        if ((string) ($routePerformance["route"] ?? "") !== "landing") {
            continue;
        }
        $landingAverageMs = max(
            0.0,
            (float) ($routePerformance["avg_ms"] ?? 0),
        );
        break;
    }
'''
if admin.count(old_metrics) != 1:
    raise RuntimeError("developer dashboard metric source boundary not found")
admin = admin.replace(old_metrics, new_metrics, 1)

old_cards = r'''    $telemetry =
        '<div class="stats-grid admin-overview-kpis global-telemetry-grid">' .
        stat_card(
            "Usuários ativos 24h",
            $activeUsers24h,
            "person_check",
            "uso recente",
        ) .
        stat_card(
            "Consultórios ativos 7d",
            $activeClinics7d,
            "domain",
            "com atividade",
        ) .
        stat_link_card(
            "Tempo médio de resposta",
            admin_performance_format_ms($averageResponseMs),
            "speed",
            "média nas últimas 24 horas",
            "admin_performance",
        ) .
        stat_card(
            "Landing page",
            $landingLoads7d,
            "web",
            "carregamentos em 7 dias",
        ) .
        "</div>";
'''
new_cards = r'''    $telemetry =
        '<div class="stats-grid admin-overview-kpis global-telemetry-grid">' .
        stat_card(
            "Requisições 24h",
            $requests24h,
            "route",
            "amostras do runtime",
        ) .
        stat_link_card(
            "Tempo da Landing Page",
            $landingAverageMs > 0
                ? admin_performance_format_ms($landingAverageMs)
                : "—",
            "web",
            "média nas últimas 24 horas",
            "admin_performance",
        ) .
        stat_card(
            "Usuários ativos 24h",
            $activeUsers24h,
            "person_check",
            "uso recente",
        ) .
        "</div>";
'''
if admin.count(old_cards) != 1:
    raise RuntimeError("developer dashboard telemetry cards boundary not found")
admin = admin.replace(old_cards, new_cards, 1)
write(admin_path, admin)

css_path = "public/assets/design-system.css"
css = read(css_path)
old_grid = "  grid-template-columns:repeat(4,minmax(0,1fr))!important;\n"
if css.count(old_grid) != 1:
    raise RuntimeError("global telemetry grid boundary not found")
css = css.replace(
    old_grid,
    "  grid-template-columns:repeat(3,minmax(0,1fr))!important;\n",
    1,
)
css_marker = ".metric-area-chart .metric-axis-label{fill:var(--md-sys-color-on-surface-variant)!important;font-size:10px;opacity:.72;}\n"
if css.count(css_marker) != 1:
    raise RuntimeError("metric area chart css boundary not found")
count_css = r'''.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--pt-color-success) 24%,transparent)!important;stroke:none!important;opacity:.92;}
.metric-dual-count-chart .metric-chart-line-requests{fill:none!important;stroke:var(--pt-color-success)!important;stroke-width:2.8;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 1px 2px color-mix(in srgb,var(--pt-color-success) 20%,transparent));}
.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--pt-color-success) 10%,transparent)!important;stroke:none!important;opacity:.82;}
.metric-dual-count-chart .metric-chart-line-records{fill:none!important;stroke:color-mix(in srgb,var(--pt-color-success) 52%,#fff)!important;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 1px 2px color-mix(in srgb,var(--pt-color-success) 12%,transparent));}
.metric-dual-count-chart .metric-chart-dot-requests{fill:var(--pt-color-success)!important;stroke:#fff!important;stroke-width:2;}
.metric-dual-count-chart .metric-chart-dot-records{fill:color-mix(in srgb,var(--pt-color-success) 52%,#fff)!important;stroke:#fff!important;stroke-width:2;}
.metric-dual-count-chart .metric-count-pill{gap:5px!important;}
.metric-dual-count-chart .metric-count-pill small{font:inherit;color:inherit;}
.metric-dual-count-chart .metric-series-key{display:inline-block;width:9px;height:9px;border-radius:999px;flex:0 0 auto;}
.metric-dual-count-chart .is-requests .metric-series-key{background:var(--pt-color-success);}
.metric-dual-count-chart .is-records .metric-series-key{background:color-mix(in srgb,var(--pt-color-success) 52%,#fff);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--pt-color-success) 24%,transparent);}
'''
css = css.replace(css_marker, css_marker + count_css, 1)
bar_css = r'''.metric-bar-chart .metric-chart-bar{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#ffffff)!important;stroke:none!important;filter:drop-shadow(0 1px 2px color-mix(in srgb,var(--md-sys-color-primary) 14%,transparent));}
.metric-bar-chart .metric-chart-bar:hover{fill:var(--md-sys-color-primary)!important;}
.metric-bar-chart .metric-chart-line,.metric-bar-chart .metric-chart-fill,.metric-bar-chart .metric-chart-dot{display:none!important;}
.metric-area-chart .metric-chart-title p{display:none!important;}
body.scope-global .metric-bar-chart .metric-chart-pills span:first-child{background:color-mix(in srgb,var(--md-sys-color-primary) 10%,#fff);}

'''
if css.count(bar_css) != 1:
    raise RuntimeError("obsolete metric bar css boundary not found")
css = css.replace(
    bar_css,
    ".metric-area-chart .metric-chart-title p{display:none!important;}\n\n",
    1,
)
write(css_path, css)

docs_index = read("docs/index.md")
docs_line = "- [Painel de telemetria do Desenvolvedor](performance/developer-telemetry-dashboard.md)\n"
if docs_index.count(docs_line) != 1:
    raise RuntimeError("developer telemetry documentation index entry not found")
write("docs/index.md", docs_index.replace(docs_line, "", 1))
telemetry_doc = ROOT / "docs/performance/developer-telemetry-dashboard.md"
if not telemetry_doc.is_file():
    raise RuntimeError("developer telemetry documentation file not found")
telemetry_doc.unlink()

architecture_check_path = "tools/architecture-check.php"
architecture_check = read(architecture_check_path)
characterization_start = architecture_check.find("$developerTelemetryFailures = [];\n")
result_start = architecture_check.find("$result = [\n", characterization_start)
if characterization_start < 0 or result_start < 0:
    raise RuntimeError("developer telemetry characterization boundary not found")
characterization = r'''$developerDashboardFailures = [];
$developerDashboardSources = [
    'admin' => (string) file_get_contents($root . '/app/Admin/AdminPages.php'),
    'runner' => (string) file_get_contents($root . '/app/Runtime/Runner.php'),
    'loader' => (string) file_get_contents($root . '/app/Support/ModuleLoader.php'),
    'bootstrap' => (string) file_get_contents($root . '/app/prontoo.php'),
    'css' => (string) file_get_contents($root . '/public/assets/design-system.css'),
    'docs' => (string) file_get_contents($root . '/docs/index.md'),
];
foreach (['admin', 'runner', 'loader', 'bootstrap'] as $sourceKey) {
    foreach (['admin_telemetry', 'page_admin_telemetry', 'function admin_telemetry_'] as $token) {
        if (str_contains($developerDashboardSources[$sourceKey], $token)) {
            $developerDashboardFailures[] =
                $sourceKey . ':obsolete_telemetry_token:' . $token;
        }
    }
}
foreach ([
    'admin' => [
        'function admin_metric_dual_count_chart(',
        '"Requisições 24h"',
        '"Tempo da Landing Page"',
        '"Usuários ativos 24h"',
        '"Requisições e registros"',
        'metric-chart-line-requests',
        'metric-chart-line-records',
        'telemetry_route_performance_summary(24)',
    ],
    'css' => [
        'grid-template-columns:repeat(3,minmax(0,1fr))!important',
        '.metric-dual-count-chart .metric-chart-line-requests',
        'stroke:var(--pt-color-success)!important',
        '.metric-dual-count-chart .metric-chart-line-records',
        'color-mix(in srgb,var(--pt-color-success) 52%,#fff)',
    ],
    'bootstrap' => [
        'dual-area-speed-and-volume-no-dual-legend-one-row',
    ],
] as $sourceKey => $tokens) {
    foreach ($tokens as $token) {
        if (!str_contains($developerDashboardSources[$sourceKey], $token)) {
            $developerDashboardFailures[] =
                $sourceKey . ':missing:' . $token;
        }
    }
}
$cardPositions = [];
foreach ([
    'requests' => '"Requisições 24h"',
    'landing' => '"Tempo da Landing Page"',
    'users' => '"Usuários ativos 24h"',
] as $key => $token) {
    $cardPositions[$key] = strpos($developerDashboardSources['admin'], $token);
}
if ($cardPositions['requests'] === false ||
    $cardPositions['landing'] === false ||
    $cardPositions['users'] === false ||
    !($cardPositions['requests'] < $cardPositions['landing'] &&
        $cardPositions['landing'] < $cardPositions['users'])) {
    $developerDashboardFailures[] = 'developer_dashboard_card_order';
}
foreach ([
    'Consultórios ativos 7d',
    'Tempo médio de resposta',
    'admin_metric_bar_chart(',
    'metric-bar-chart',
    'developer-telemetry-dashboard.md',
] as $obsoleteToken) {
    if (str_contains(
        $developerDashboardSources['admin'] .
            $developerDashboardSources['css'] .
            $developerDashboardSources['docs'],
        $obsoleteToken,
    )) {
        $developerDashboardFailures[] =
            'obsolete_dashboard_token:' . $obsoleteToken;
    }
}
if (is_file($root . '/docs/performance/developer-telemetry-dashboard.md')) {
    $developerDashboardFailures[] = 'obsolete_telemetry_document';
}
$developerDashboardCharacterization = [
    'ok' => $developerDashboardFailures === [],
    'failed' => $developerDashboardFailures,
];
'''
architecture_check = (
    architecture_check[:characterization_start]
    + characterization
    + architecture_check[result_start:]
)
old_ok = (
    "        !empty($serverPhaseSixCharacterization['ok']) &&\n"
    "        !empty($developerTelemetryCharacterization['ok']),\n"
)
new_ok = (
    "        !empty($serverPhaseSixCharacterization['ok']) &&\n"
    "        !empty($developerDashboardCharacterization['ok']),\n"
)
if architecture_check.count(old_ok) != 1:
    raise RuntimeError("architecture result telemetry condition not found")
architecture_check = architecture_check.replace(old_ok, new_ok, 1)
old_result_key = (
    "    'developer_telemetry_characterization' => "
    "$developerTelemetryCharacterization,\n"
)
new_result_key = (
    "    'developer_dashboard_characterization' => "
    "$developerDashboardCharacterization,\n"
)
if architecture_check.count(old_result_key) != 1:
    raise RuntimeError("architecture telemetry result key not found")
architecture_check = architecture_check.replace(
    old_result_key,
    new_result_key,
    1,
)
write(architecture_check_path, architecture_check)

version = json.loads(read("version.json"))
version.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "package_type": "incremental_refinement",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "functional_equivalence_policy": "removes_duplicate_telemetry_route_and_consolidates_observability_in_developer_dashboard",
    "notes": "Remove a tela Telemetria duplicada, reorganiza os indicadores do Painel do Desenvolvedor e unifica Requisições e Registros em gráfico de duas séries.",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "deployment_sync_id": "github-developer-dashboard-consolidation-1-7-30-9",
    "deployment_sync_requested_at": NOW,
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "developer_dashboard_observability_consolidation",
})
write("version.json", json.dumps(version, ensure_ascii=False, indent=4) + "\n")

architecture = json.loads(read("app/architecture.manifest.json"))
for key in [
    "developer_telemetry_dashboard_policy",
    "developer_telemetry_dashboard_components",
    "developer_telemetry_dashboard_check",
]:
    architecture.pop(key, None)
architecture.update({
    "version": VERSION,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "generated_at": NOW,
    "updated_at": NOW,
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "developer_dashboard_observability_policy": "single_global_dashboard_three_kpis_and_dual_request_record_chart_without_separate_telemetry_route",
    "developer_dashboard_observability_components": [
        "app/Admin/AdminPages.php",
        "public/assets/design-system.css",
        "app/Support/Telemetry.php",
    ],
    "developer_dashboard_observability_check": "tools/architecture-check.php",
})
write(
    "app/architecture.manifest.json",
    json.dumps(architecture, ensure_ascii=False, indent=4) + "\n",
)

manifest = json.loads(read("app/update.manifest.json"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "package_type": "incremental_refinement",
    "generated_at": NOW,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
})
write(
    "app/update.manifest.json",
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
)

landing_path = "br/index.php"
landing = read(landing_path)
landing, count = re.subn(
    r'(BR_LANDING_VERSION_FALLBACK\s*=\s*["\'])1\.7\.30\.8(["\'])',
    r'\g<1>1.7.30.9\g<2>',
    landing,
    count=1,
)
if count != 1:
    raise RuntimeError("landing version fallback not found")
write(landing_path, landing)

changelog = read("CHANGELOG.md")
entry = '''## 1.7.30.9 — Consolidação do Painel do Desenvolvedor

- remove a rota, o item de navegação e as funções da tela Telemetria duplicada;
- reorganiza os cards para Requisições 24h, Tempo da Landing Page e Usuários ativos 24h;
- unifica Requisições e Registros em gráfico de duas séries no modelo visual de Velocidade;
- aplica verde escuro às Requisições e verde claro aos Registros;
- mantém banco e schema inalterados.

'''
if not changelog.startswith("# Histórico de versões\n\n"):
    raise RuntimeError("changelog header not found")
write(
    "CHANGELOG.md",
    "# Histórico de versões\n\n"
    + entry
    + changelog[len("# Histórico de versões\n\n"):],
)

legacy = read("ChangeLog.txt")
legacy_entry = '''Prontoo 1.7.30.9 — Consolidação do Painel do Desenvolvedor

- Remove a tela Telemetria duplicada e concentra seus indicadores úteis no painel principal.
- Reorganiza os três cards e unifica Requisições e Registros em um gráfico de duas séries.
- Mantém banco e schema inalterados.
'''
write("ChangeLog.txt", legacy_entry + legacy)

print(VERSION)
