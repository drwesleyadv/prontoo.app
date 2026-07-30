from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.12"
PREVIOUS = "1.7.30.11"
BUILD = "1.7.30.12-dashboard-identical-area-compositing"
DEPLOYMENT_ID = "github-dashboard-identical-area-compositing-1-7-30-12"
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
    '''        ($recordFill !== ""
            ? '<path d="' .
                e($recordFill) .
                '" class="metric-chart-fill-records"/>'
            : "") .
        ($requestFill !== ""
            ? '<path d="' .
                e($requestFill) .
                '" class="metric-chart-fill-requests"/>'
            : "") .''',
    '''        ($requestFill !== ""
            ? '<path d="' .
                e($requestFill) .
                '" class="metric-chart-fill-load metric-chart-fill-requests"/>'
            : "") .
        ($recordFill !== ""
            ? '<path d="' .
                e($recordFill) .
                '" class="metric-chart-fill-response metric-chart-fill-records"/>'
            : "") .''',
    "count chart paint order and shared classes",
)
write(admin_path, admin)

css_path = "public/assets/design-system.css"
css = read(css_path)
css = replace_once(
    css,
    '''.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%)!important;stroke:none!important;opacity:.38;}
.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%)!important;stroke:none!important;opacity:.78;}
''',
    "",
    "remove duplicated count palette",
)
css = replace_once(
    css,
    '''.metric-dual-time-chart .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);opacity:.38}
.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}''',
    '''.metric-dual-time-chart .metric-chart-fill-load,.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);stroke:none;opacity:.38}
.metric-dual-time-chart .metric-chart-fill-response,.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);stroke:none;opacity:.78}''',
    "shared chart palette",
)
write(css_path, css)

check_path = "tools/architecture-check.php"
check = read(check_path)
check = replace_once(
    check,
    '''        'metric-chart-fill-requests',
        'metric-chart-fill-records',
        '$averageResponseMs',''',
    '''        'metric-chart-fill-load metric-chart-fill-requests',
        'metric-chart-fill-response metric-chart-fill-records',
        '$averageResponseMs',''',
    "required shared classes",
)
check = replace_once(
    check,
    '''        '.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%)!important;stroke:none!important;opacity:.38;}',
        '.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%)!important;stroke:none!important;opacity:.78;}',
        '.metric-dual-time-chart .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);opacity:.38}',
        '.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}',
        'stroke:none!important',''',
    '''        '.metric-dual-time-chart .metric-chart-fill-load,.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);stroke:none;opacity:.38}',
        '.metric-dual-time-chart .metric-chart-fill-response,.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);stroke:none;opacity:.78}',
        'stroke:none',''',
    "architecture shared palette tokens",
)
anchor = '''foreach ([
    'admin_metric_bar_chart(',
    'metric-bar-chart',
    'developer-telemetry-dashboard.md',
] as $obsoleteToken) {'''
order_check = '''$requestAreaPosition = strpos(
    $developerDashboardSources['admin'],
    'class="metric-chart-fill-load metric-chart-fill-requests"',
);
$recordAreaPosition = strpos(
    $developerDashboardSources['admin'],
    'class="metric-chart-fill-response metric-chart-fill-records"',
);
if ($requestAreaPosition === false ||
    $recordAreaPosition === false ||
    $requestAreaPosition >= $recordAreaPosition) {
    $developerDashboardFailures[] = 'developer_dashboard_area_compositing_order';
}
foreach ([
    '.metric-dual-count-chart .metric-chart-fill-requests{',
    '.metric-dual-count-chart .metric-chart-fill-records{',
] as $duplicatedPaletteToken) {
    if (str_contains($developerDashboardSources['css'], $duplicatedPaletteToken)) {
        $developerDashboardFailures[] =
            'developer_dashboard_duplicated_palette:' . $duplicatedPaletteToken;
    }
}
''' + anchor
check = replace_once(check, anchor, order_check, "architecture compositing order")
write(check_path, check)

prontoo_path = "app/prontoo.php"
prontoo = read(prontoo_path)
prontoo = replace_once(
    prontoo,
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.11";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.12";',
    "runtime version fallback",
)
prontoo = replace_once(
    prontoo,
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-shared-palette-filled-volume-no-series-outline-one-row";',
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-shared-css-palette-identical-compositing-order-no-series-outline-one-row";',
    "chart policy",
)
write(prontoo_path, prontoo)

br_path = "br/index.php"
br = read(br_path)
br = replace_once(
    br,
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.11";',
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.12";',
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
        "functional_equivalence_policy": "preserves_dashboard_metrics_and_uses_identical_speed_chart_fill_classes_and_compositing_order",
        "notes": "Corrige a composição visual de Leitura e gravação reutilizando as classes e a ordem de pintura do gráfico Velocidade: Requisições no tom escuro e Registros no tom claro.",
        "previous_version": PREVIOUS,
        "documentation_changes": True,
        "deployment_sync_id": DEPLOYMENT_ID,
        "deployment_sync_requested_at": NOW_ISO,
        "baseline_source": PREVIOUS,
        "full_baseline_rewrite": False,
        "rewrite_scope": "developer_dashboard_identical_area_classes_and_compositing_order",
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
        "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_landing_request_count_and_dual_filled_areas_reusing_speed_classes_and_identical_compositing_order_without_series_outlines",
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
        "previous_version": PREVIOUS,
        "updated_at": NOW_ISO,
        "notes": "Leitura e gravação reutiliza as classes e a ordem de composição das áreas do gráfico Velocidade.",
        "deployment_sync_id": DEPLOYMENT_ID,
    }
)
update_path.write_text(
    json.dumps(update, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

changelog_path = "CHANGELOG.md"
changelog = read(changelog_path)
entry = '''## 1.7.30.12 — Composição visual idêntica entre gráficos

- reutiliza no gráfico Leitura e gravação as mesmas classes de preenchimento do gráfico Velocidade;
- alinha a ordem de pintura: Requisições no tom escuro primeiro e Registros no tom claro depois;
- elimina regras duplicadas de paleta que poderiam divergir por especificidade;
- mantém métricas, banco e schema inalterados.

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
text_entry = '''Prontoo 1.7.30.12 — Composição visual idêntica entre gráficos

- Reutiliza no gráfico Leitura e gravação as mesmas classes de preenchimento do gráfico Velocidade.
- Pinta Requisições com a área escura e Registros com a área clara na mesma ordem de composição.
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

print(json.dumps({"ok": True, "version": VERSION, "build": BUILD}, ensure_ascii=False))
