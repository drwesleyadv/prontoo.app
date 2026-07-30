from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OLD_VERSION = "1.7.30.9"
NEW_VERSION = "1.7.30.10"


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected 1 occurrence, found {count}")
    return content.replace(old, new, 1)


def load_json(path: str) -> dict:
    value = json.loads(read(path))
    if not isinstance(value, dict):
        raise RuntimeError(f"{path}: invalid JSON object")
    return value


def write_json(path: str, value: dict) -> None:
    write(path, json.dumps(value, ensure_ascii=False, indent=4) + "\n")


admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)
start = admin.find("    $lastRequest = end($requestPoints);\n")
end = admin.find("    $summary =\n", start)
if start < 0 or end < 0:
    raise RuntimeError("dual count visible endpoint boundary not found")
admin = admin[:start] + admin[end:]
admin = replace_once(
    admin,
    '''        ($requestFill !== ""
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
''',
    '''        ($recordFill !== ""
            ? '<path d="' .
                e($recordFill) .
                '" class="metric-chart-fill-records"/>'
            : "") .
        ($requestFill !== ""
            ? '<path d="' .
                e($requestFill) .
                '" class="metric-chart-fill-requests"/>'
            : "") .
''',
    "dual count SVG layers",
)
admin = replace_once(
    admin,
    '''    $requests24h = max(0, (int) ($performance24h["total"] ?? 0));
    $landingAverageMs = 0.0;
''',
    '''    $requests24h = max(0, (int) ($performance24h["total"] ?? 0));
    $averageResponseMs = max(0.0, (float) ($performance24h["avg_ms"] ?? 0));
    $landingAverageMs = 0.0;
''',
    "average response metric",
)
admin = replace_once(
    admin,
    '''        stat_card(
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
''',
    '''        stat_card(
            "Requisições",
            $requests24h,
            "route",
            "últimas 24 horas",
        ) .
        stat_link_card(
            "Tempo Médio",
            $averageResponseMs > 0
                ? admin_performance_format_ms($averageResponseMs)
                : "—",
            "speed",
            "resposta nas últimas 24 horas",
            "admin_performance",
        ) .
        stat_link_card(
            "Landing Page",
            $landingAverageMs > 0
                ? admin_performance_format_ms($landingAverageMs)
                : "—",
            "web",
            "média nas últimas 24 horas",
            "admin_performance",
        ) .
        stat_card(
            "Usuários Ativos",
            $activeUsers24h,
            "person_check",
            "últimas 24 horas",
        ) .
''',
    "developer dashboard cards",
)
for token in [
    "metric-chart-line-requests",
    "metric-chart-line-records",
    "metric-chart-dot-requests",
    "metric-chart-dot-records",
    "$requestCircle",
    "$recordCircle",
]:
    if token in admin:
        raise RuntimeError(f"visible outline token remains in admin: {token}")
if admin.index('class="metric-chart-fill-records"') > admin.index('class="metric-chart-fill-requests"'):
    raise RuntimeError("area order must be light records before dark requests")
write(admin_path, admin)

css_path = "public/assets/design-system.css"
css = read(css_path)
css = replace_once(
    css,
    '''.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--pt-color-success) 24%,transparent)!important;stroke:none!important;opacity:.92;}
.metric-dual-count-chart .metric-chart-line-requests{fill:none!important;stroke:var(--pt-color-success)!important;stroke-width:2.8;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 1px 2px color-mix(in srgb,var(--pt-color-success) 20%,transparent));}
.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--pt-color-success) 10%,transparent)!important;stroke:none!important;opacity:.82;}
.metric-dual-count-chart .metric-chart-line-records{fill:none!important;stroke:color-mix(in srgb,var(--pt-color-success) 52%,#fff)!important;stroke-width:2.4;stroke-linecap:round;stroke-linejoin:round;filter:drop-shadow(0 1px 2px color-mix(in srgb,var(--pt-color-success) 12%,transparent));}
.metric-dual-count-chart .metric-chart-dot-requests{fill:var(--pt-color-success)!important;stroke:#fff!important;stroke-width:2;}
.metric-dual-count-chart .metric-chart-dot-records{fill:color-mix(in srgb,var(--pt-color-success) 52%,#fff)!important;stroke:#fff!important;stroke-width:2;}
''',
    '''.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--pt-color-success) 32%,transparent)!important;stroke:none!important;opacity:.88;}
.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--pt-color-success) 16%,transparent)!important;stroke:none!important;opacity:.78;}
''',
    "dual count area CSS",
)
css = replace_once(
    css,
    '''body.scope-global .global-telemetry-grid{
  display:grid!important;
  grid-template-columns:repeat(3,minmax(0,1fr))!important;
  gap:10px!important;
}
''',
    '''body.scope-global .global-telemetry-grid{
  display:grid!important;
  grid-template-columns:repeat(4,minmax(0,1fr))!important;
  gap:10px!important;
}
''',
    "developer KPI grid",
)
for token in [
    ".metric-dual-count-chart .metric-chart-line-requests",
    ".metric-dual-count-chart .metric-chart-line-records",
    ".metric-dual-count-chart .metric-chart-dot-requests",
    ".metric-dual-count-chart .metric-chart-dot-records",
]:
    if token in css:
        raise RuntimeError(f"visible outline token remains in CSS: {token}")
write(css_path, css)

check_path = "tools/architecture-check.php"
check = read(check_path)
check = replace_once(
    check,
    '''    'admin' => [
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
''',
    '''    'admin' => [
        'function admin_metric_dual_count_chart(',
        '"Requisições"',
        '"Tempo Médio"',
        '"Landing Page"',
        '"Usuários Ativos"',
        '"Requisições e registros"',
        'metric-chart-fill-requests',
        'metric-chart-fill-records',
        '$averageResponseMs',
        'telemetry_route_performance_summary(24)',
    ],
    'css' => [
        'grid-template-columns:repeat(4,minmax(0,1fr))!important',
        '.metric-dual-count-chart .metric-chart-fill-requests',
        'color-mix(in srgb,var(--pt-color-success) 32%,transparent)',
        '.metric-dual-count-chart .metric-chart-fill-records',
        'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
        'stroke:none!important',
    ],
    'bootstrap' => [
        'dual-area-speed-and-filled-volume-no-series-outline-one-row',
    ],
''',
    "dashboard required characterization",
)
check = replace_once(
    check,
    '''foreach ([
    'requests' => '"Requisições 24h"',
    'landing' => '"Tempo da Landing Page"',
    'users' => '"Usuários ativos 24h"',
] as $key => $token) {
''',
    '''foreach ([
    'requests' => '"Requisições"',
    'average' => '"Tempo Médio"',
    'landing' => '"Landing Page"',
    'users' => '"Usuários Ativos"',
] as $key => $token) {
''',
    "dashboard card positions",
)
check = replace_once(
    check,
    '''if ($cardPositions['requests'] === false ||
    $cardPositions['landing'] === false ||
    $cardPositions['users'] === false ||
    !($cardPositions['requests'] < $cardPositions['landing'] &&
        $cardPositions['landing'] < $cardPositions['users'])) {
''',
    '''if ($cardPositions['requests'] === false ||
    $cardPositions['average'] === false ||
    $cardPositions['landing'] === false ||
    $cardPositions['users'] === false ||
    !($cardPositions['requests'] < $cardPositions['average'] &&
        $cardPositions['average'] < $cardPositions['landing'] &&
        $cardPositions['landing'] < $cardPositions['users'])) {
''',
    "dashboard card order",
)
outline_guard = '''foreach ([
    'metric-chart-line-requests',
    'metric-chart-line-records',
    'metric-chart-dot-requests',
    'metric-chart-dot-records',
] as $outlineToken) {
    if (str_contains(
        $developerDashboardSources['admin'] .
            $developerDashboardSources['css'],
        $outlineToken,
    )) {
        $developerDashboardFailures[] =
            'developer_dashboard_outline_token:' . $outlineToken;
    }
}
'''
anchor = '''foreach ([
    'admin_metric_bar_chart(',
    'metric-bar-chart',
    'developer-telemetry-dashboard.md',
] as $obsoleteToken) {
'''
if outline_guard not in check:
    check = replace_once(check, anchor, outline_guard + anchor, "outline regression guard")
write(check_path, check)

prontoo_path = "app/prontoo.php"
prontoo = read(prontoo_path)
prontoo = replace_once(prontoo, 'const PRONTOO_VERSION_FALLBACK = "1.7.30.9";', 'const PRONTOO_VERSION_FALLBACK = "1.7.30.10";', "runtime version")
prontoo = replace_once(prontoo, 'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-speed-and-volume-no-dual-legend-one-row";', 'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-speed-and-filled-volume-no-series-outline-one-row";', "chart policy")
write(prontoo_path, prontoo)

landing_path = "br/index.php"
landing = read(landing_path)
landing = replace_once(landing, 'const BR_LANDING_VERSION_FALLBACK = "1.7.30.9";', 'const BR_LANDING_VERSION_FALLBACK = "1.7.30.10";', "landing version")
write(landing_path, landing)

now = datetime.now(timezone.utc)
now_iso = now.isoformat(timespec="seconds")
now_unix = int(now.timestamp())
common = {
    "version": NEW_VERSION,
    "release": NEW_VERSION,
    "generated_at_unix": now_unix,
    "generated_at": now_iso,
    "updated_at": now_iso,
    "build": "1.7.30.10-request-record-filled-areas",
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "functional_equivalence_policy": "preserves_metrics_and_replaces_request_record_outlines_with_filled_areas",
    "notes": "Exibe quatro cards na ordem Requisições, Tempo Médio, Landing Page e Usuários Ativos; remove linhas e marcadores visíveis do gráfico de volume, mantendo apenas áreas preenchidas.",
    "previous_version": OLD_VERSION,
    "documentation_changes": True,
    "baseline_source": OLD_VERSION,
    "rewrite_scope": "developer_dashboard_cards_and_filled_areas_without_outlines",
    "deployment_sync_id": "github-request-record-filled-areas-1-7-30-10",
    "deployment_sync_requested_at": now_iso,
}
version = load_json("version.json")
version.update(common)
write_json("version.json", version)
update_manifest = load_json("app/update.manifest.json")
update_manifest.update(common)
write_json("app/update.manifest.json", update_manifest)
architecture = load_json("app/architecture.manifest.json")
architecture.update({
    "version": NEW_VERSION,
    "database_changes": False,
    "schema_changes": False,
    "visual_changes": True,
    "logic_changes": True,
    "generated_at": now_iso,
    "updated_at": now_iso,
    "baseline_source": OLD_VERSION,
    "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_and_dual_filled_request_record_areas_without_series_outlines",
})
write_json("app/architecture.manifest.json", architecture)

changelog = read("CHANGELOG.md")
entry = '''## 1.7.30.10 — Áreas do gráfico sem contornos

- organiza os cards em Requisições, Tempo Médio, Landing Page e Usuários Ativos;
- remove linhas de contorno e marcadores finais das séries Requisições e Registros;
- mantém somente as áreas preenchidas na base, com Registros em verde claro e Requisições em verde escuro;
- preserva período, valores, tooltips e demais gráficos;
- mantém banco e schema inalterados.

'''
changelog = replace_once(changelog, "# Histórico de versões\n\n", "# Histórico de versões\n\n" + entry, "markdown changelog")
write("CHANGELOG.md", changelog)
legacy = read("ChangeLog.txt")
legacy_entry = '''Prontoo 1.7.30.10 — Áreas do gráfico sem contornos

- Organiza os cards em Requisições, Tempo Médio, Landing Page e Usuários Ativos.
- Remove linhas e marcadores visíveis do gráfico Requisições e Registros.
- Mantém apenas as áreas preenchidas em verde escuro e verde claro, sem alterar banco ou schema.
'''
write("ChangeLog.txt", legacy_entry + legacy)
