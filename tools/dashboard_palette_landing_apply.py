from __future__ import annotations

import json
import time
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.11"
PREVIOUS = "1.7.30.10"
BUILD = "1.7.30.11-dashboard-palette-landing-count"
DEPLOYMENT_ID = "github-dashboard-palette-landing-count-1-7-30-11"
NOW = datetime.now(timezone.utc).replace(microsecond=0)
NOW_ISO = NOW.isoformat()
NOW_UNIX = int(NOW.timestamp())


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    (ROOT / path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one occurrence, found {count}")
    return content.replace(old, new, 1)


admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)
admin = replace_once(
    admin,
    '            "Requisições e registros",',
    '            "Leitura e gravação",',
    "chart title",
)
admin = replace_once(
    admin,
    '''    $landingAverageMs = 0.0;
    foreach ((array) ($performance24h["routes"] ?? []) as $routePerformance) {
        if ((string) ($routePerformance["route"] ?? "") !== "landing") {
            continue;
        }
        $landingAverageMs = max(
            0.0,
            (float) ($routePerformance["avg_ms"] ?? 0),
        );
        break;
    }''',
    '''    $landingRequests24h = 0;
    foreach ((array) ($performance24h["routes"] ?? []) as $routePerformance) {
        if ((string) ($routePerformance["route"] ?? "") !== "landing") {
            continue;
        }
        $landingRequests24h = max(
            0,
            (int) ($routePerformance["count"] ?? 0),
        );
        break;
    }''',
    "landing metric",
)
admin = replace_once(
    admin,
    '''        stat_link_card(
            "Landing Page",
            $landingAverageMs > 0
                ? admin_performance_format_ms($landingAverageMs)
                : "—",
            "web",
            "média nas últimas 24 horas",
            "admin_performance",
        ) .''',
    '''        stat_link_card(
            "Landing Page",
            $landingRequests24h,
            "web",
            "requisições nas últimas 24 horas",
            "admin_performance",
        ) .''',
    "landing card",
)
write(admin_path, admin)

css_path = "public/assets/design-system.css"
css = read(css_path)
css = replace_once(
    css,
    '.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--pt-color-success) 32%,transparent)!important;stroke:none!important;opacity:.88;}\n.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--pt-color-success) 16%,transparent)!important;stroke:none!important;opacity:.78;}',
    '.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%)!important;stroke:none!important;opacity:.38;}\n.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%)!important;stroke:none!important;opacity:.78;}',
    "filled area palette",
)
css = replace_once(
    css,
    '.metric-dual-count-chart .is-requests .metric-series-key{background:var(--pt-color-success);}\n.metric-dual-count-chart .is-records .metric-series-key{background:color-mix(in srgb,var(--pt-color-success) 52%,#fff);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--pt-color-success) 24%,transparent);}',
    '.metric-dual-count-chart .is-requests .metric-series-key{background:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);}\n.metric-dual-count-chart .is-records .metric-series-key{background:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--md-sys-color-primary) 18%,transparent);}',
    "legend palette",
)
write(css_path, css)

check_path = "tools/architecture-check.php"
check = read(check_path)
check = replace_once(
    check,
    '''        '"Requisições e registros"',
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
    ],''',
    '''        '"Leitura e gravação"',
        'metric-chart-fill-requests',
        'metric-chart-fill-records',
        '$averageResponseMs',
        '$landingRequests24h',
        '(int) ($routePerformance["count"] ?? 0)',
        '"requisições nas últimas 24 horas"',
        'telemetry_route_performance_summary(24)',
    ],
    'css' => [
        'grid-template-columns:repeat(4,minmax(0,1fr))!important',
        '.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%)!important;stroke:none!important;opacity:.38;}',
        '.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%)!important;stroke:none!important;opacity:.78;}',
        '.metric-dual-time-chart .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);opacity:.38}',
        '.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}',
        'stroke:none!important',
    ],''',
    "architecture required tokens",
)
check = replace_once(
    check,
    '''foreach ([
    'metric-chart-line-requests',
    'metric-chart-line-records',
    'metric-chart-dot-requests',
    'metric-chart-dot-records',
] as $outlineToken) {''',
    '''foreach ([
    'metric-chart-line-requests',
    'metric-chart-line-records',
    'metric-chart-dot-requests',
    'metric-chart-dot-records',
    '$landingAverageMs',
    '"Requisições e registros"',
    'color-mix(in srgb,var(--pt-color-success) 32%,transparent)',
    'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
] as $outlineToken) {''',
    "architecture forbidden tokens",
)
write(check_path, check)

prontoo_path = "app/prontoo.php"
prontoo = read(prontoo_path)
prontoo = replace_once(
    prontoo,
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.10";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.11";',
    "runtime version fallback",
)
prontoo = replace_once(
    prontoo,
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-speed-and-filled-volume-no-series-outline-one-row";',
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-shared-palette-filled-volume-no-series-outline-one-row";',
    "chart policy",
)
write(prontoo_path, prontoo)

br_path = "br/index.php"
br = read(br_path)
br = replace_once(
    br,
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.10";',
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.11";',
    "landing version fallback",
)
write(br_path, br)

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update(
    {
        "version": VERSION,
        "release": VERSION,
        "generated_at_unix": NOW_UNIX,
        "generated_at": NOW_ISO,
        "updated_at": NOW_ISO,
        "build": BUILD,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "functional_equivalence_policy": "preserves_dashboard_metrics_uses_speed_chart_palette_and_counts_landing_requests",
        "notes": "Alinha as áreas de Leitura e gravação à paleta do gráfico Velocidade e exibe no card Landing Page o volume de requisições das últimas 24 horas.",
        "previous_version": PREVIOUS,
        "documentation_changes": True,
        "deployment_sync_id": DEPLOYMENT_ID,
        "deployment_sync_requested_at": NOW_ISO,
        "baseline_source": PREVIOUS,
        "full_baseline_rewrite": False,
        "rewrite_scope": "developer_dashboard_shared_chart_palette_landing_request_count_and_chart_label",
    }
)
version_path.write_text(
    json.dumps(version, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update(
    {
        "version": VERSION,
        "generated_at": NOW_ISO,
        "updated_at": NOW_ISO,
        "baseline_source": PREVIOUS,
        "full_baseline_rewrite": False,
        "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_landing_request_count_and_dual_filled_areas_matching_speed_palette_without_series_outlines",
    }
)
architecture_path.write_text(
    json.dumps(architecture, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

update_path = ROOT / "app/update.manifest.json"
update = json.loads(update_path.read_text(encoding="utf-8"))
update.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "generated_at": NOW_ISO,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "documentation_changes": True,
    }
)
update_path.write_text(
    json.dumps(update, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

changelog_path = "CHANGELOG.md"
changelog = read(changelog_path)
entry = '''## 1.7.30.11 — Paleta compartilhada e volume da Landing Page

- renomeia o gráfico Requisições e registros para Leitura e gravação;
- aplica às duas áreas exatamente os mesmos tons usados no gráfico Velocidade;
- mantém o gráfico sem linhas de contorno ou marcadores visíveis;
- altera o card Landing Page para mostrar requisições das últimas 24 horas;
- mantém banco e schema inalterados.

'''
changelog = replace_once(
    changelog,
    "# Histórico de versões\n\n",
    "# Histórico de versões\n\n" + entry,
    "markdown changelog",
)
write(changelog_path, changelog)

text_changelog_path = "ChangeLog.txt"
text_changelog = read(text_changelog_path)
text_entry = '''Prontoo 1.7.30.11 — Paleta compartilhada e volume da Landing Page

- Renomeia o gráfico para Leitura e gravação.
- Usa os mesmos tons do gráfico Velocidade nas áreas de leitura e gravação.
- Exibe no card Landing Page o número de requisições das últimas 24 horas.
- Mantém banco e schema inalterados.
'''
write(text_changelog_path, text_entry + text_changelog)

for relative in [
    admin_path,
    css_path,
    check_path,
    prontoo_path,
    br_path,
    "version.json",
    "app/architecture.manifest.json",
    "app/update.manifest.json",
    changelog_path,
    text_changelog_path,
]:
    path = ROOT / relative
    if not path.is_file() or path.stat().st_size == 0:
        raise RuntimeError(f"invalid generated file: {relative}")

print(
    json.dumps(
        {
            "ok": True,
            "version": VERSION,
            "build": BUILD,
            "generated_at": NOW_ISO,
        },
        ensure_ascii=False,
    )
)
