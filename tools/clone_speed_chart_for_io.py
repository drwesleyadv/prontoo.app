from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

VERSION = "1.7.30.18"
PREVIOUS_VERSION = "1.7.30.17"
BUILD = "1.7.30.18-velocity-clone-request-record-chart"
POLICY = "dual-area-single-renderer-bit-identical-formatting-data-specific-labels-one-row"


def read(path: str) -> str:
    return Path(path).read_text(encoding="utf-8")


def write(path: str, content: str) -> None:
    Path(path).write_text(content, encoding="utf-8")


def replace_once(content: str, old: str, new: str, label: str) -> str:
    count = content.count(old)
    if count != 1:
        raise RuntimeError(f"{label}: expected one occurrence, found {count}")
    return content.replace(old, new, 1)


def remove_between(content: str, start: str, end: str, label: str) -> str:
    start_index = content.find(start)
    if start_index < 0:
        raise RuntimeError(f"{label}: start marker not found")
    end_index = content.find(end, start_index)
    if end_index < 0:
        raise RuntimeError(f"{label}: end marker not found")
    return content[:start_index] + content[end_index:]


admin_path = "app/Admin/AdminPages.php"
admin = read(admin_path)
admin = remove_between(
    admin,
    "    $segmentedCountAreas = static function (\n",
    "    $loadPoints = $makePoints($loadSeries);\n",
    "remove segmented count renderer",
)
admin = remove_between(
    admin,
    '    if ($valueType === "count") {\n        $segmentedAreas = $segmentedCountAreas(\n',
    '    return \'<article class="metric-line-chart metric-area-chart metric-dual-time-chart"',
    "replace count-specific fill branch",
)
generic_fill = '''    $loadArea =
        $loadFill !== ""
            ? '<path d="' . e($loadFill) . '" class="metric-chart-fill-load"/>'
            : "";
    $responseArea =
        $responseFill !== ""
            ? '<path d="' .
                e($responseFill) .
                '" class="metric-chart-fill-response"/>'
            : "";
    $fillAreas = $loadArea . $responseArea;
'''
return_marker = '    return \'<article class="metric-line-chart metric-area-chart metric-dual-time-chart"'
admin = replace_once(
    admin,
    return_marker,
    generic_fill + return_marker,
    "insert shared fill renderer",
)
admin = replace_once(
    admin,
    '''    return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-metric-value-type="' .
        e($valueType) .
        '" data-ds-card="admin-dual-area-chart" aria-label="' .''',
    '''    return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-ds-card="admin-dual-area-chart" aria-label="' .''',
    "remove visual mode attribute",
)
admin = replace_once(
    admin,
    '''            "Leitura e gravação",
            $requests,
            $records,
            "monitoring",''',
    '''            "Leitura e gravação",
            $requests,
            $records,
            "speed",''',
    "clone velocity icon",
)
for obsolete in [
    "$segmentedCountAreas",
    "metric-chart-fill-load-exclusive",
    "metric-chart-fill-response-exclusive",
    "metric-chart-fill-intersection",
    'data-metric-value-type="',
]:
    if obsolete in admin:
        raise RuntimeError(f"admin obsolete token remains: {obsolete}")
if admin.count('$fillAreas = $loadArea . $responseArea;') != 1:
    raise RuntimeError("shared fill renderer count mismatch")
write(admin_path, admin)

css_path = "public/assets/design-system.css"
css = read(css_path)
for rule in [
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load-exclusive{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}\n',
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response-exclusive{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}\n',
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-intersection{fill:color-mix(in srgb,var(--md-sys-color-outline-variant) 42%,var(--md-sys-color-surface-container-lowest) 58%);opacity:1}\n',
]:
    css = replace_once(css, rule, "", "remove count-specific css")
if 'data-metric-value-type="count"' in css:
    raise RuntimeError("count-specific css selector remains")
write(css_path, css)

architecture_check_path = "tools/architecture-check.php"
architecture_check = read(architecture_check_path)
architecture_check = replace_once(
    architecture_check,
    '''        '"value_type" => "count"',
        '$segmentedCountAreas = static function (',
        '"intersection" => trim($intersectionPath)',
        'class="metric-chart-fill-load-exclusive"',
        'class="metric-chart-fill-response-exclusive"',
        'class="metric-chart-fill-intersection"',
        '$intersectionArea . $loadExclusiveArea . $responseExclusiveArea',
        '$recentValue = $valueType === "count"',''',
    '''        '"value_type" => "count"',
        '$fillAreas = $loadArea . $responseArea;',
        'admin_metric_dual_area_chart("Velocidade", $load, $response, "speed")',
        '"Leitura e gravação",',
        '$requests,',
        '$records,',
        '$recentValue = $valueType === "count"',''',
    "architecture admin shared renderer tokens",
)
architecture_check = replace_once(
    architecture_check,
    '''        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load-exclusive{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response-exclusive{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-intersection{fill:color-mix(in srgb,var(--md-sys-color-outline-variant) 42%,var(--md-sys-color-surface-container-lowest) 58%);opacity:1}',
''',
    "",
    "architecture remove count css requirements",
)
architecture_check = replace_once(
    architecture_check,
    '''    '? $responseArea . $loadArea',
] as $outlineToken) {''',
    '''    '? $responseArea . $loadArea',
    '$segmentedCountAreas = static function (',
    'metric-chart-fill-load-exclusive',
    'metric-chart-fill-response-exclusive',
    'metric-chart-fill-intersection',
    'data-metric-value-type="',
] as $outlineToken) {''',
    "architecture obsolete count renderer tokens",
)
architecture_check = replace_once(
    architecture_check,
    "'dual-area-single-renderer-identical-visuals-data-specific-labels-one-row',",
    f"'{POLICY}',",
    "architecture renderer policy",
)
write(architecture_check_path, architecture_check)

prontoo_path = "app/prontoo.php"
prontoo = read(prontoo_path)
prontoo = replace_once(prontoo, PREVIOUS_VERSION, VERSION, "app version")
prontoo = replace_once(
    prontoo,
    "dual-area-single-renderer-identical-visuals-data-specific-labels-one-row",
    POLICY,
    "app renderer policy",
)
write(prontoo_path, prontoo)

br_path = "br/index.php"
br = read(br_path)
br = replace_once(br, PREVIOUS_VERSION, VERSION, "br version")
write(br_path, br)

now = datetime.now(timezone.utc)
timestamp = now.isoformat()
unix = int(now.timestamp())

version_path = Path("version.json")
version_data = json.loads(version_path.read_text(encoding="utf-8"))
version_data.update(
    {
        "version": VERSION,
        "release": VERSION,
        "generated_at_unix": unix,
        "generated_at": timestamp,
        "updated_at": timestamp,
        "build": BUILD,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "notes": "O gráfico Leitura e gravação descarta toda formatação própria e reutiliza exatamente o renderer, as classes, a ordem de pintura e o ícone do gráfico Velocidade, alterando somente dados, rótulos e valores.",
        "previous_version": PREVIOUS_VERSION,
        "deployment_sync_id": "github-velocity-clone-request-record-chart-1-7-30-18",
        "deployment_sync_requested_at": timestamp,
        "rewrite_scope": "developer_dashboard_velocity_clone_request_record_chart",
    }
)
version_path.write_text(
    json.dumps(version_data, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

architecture_path = Path("app/architecture.manifest.json")
architecture_data = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture_data.update(
    {
        "version": VERSION,
        "release": VERSION,
        "build": BUILD,
        "generated_at": timestamp,
        "updated_at": timestamp,
        "database_changes": False,
        "schema_changes": False,
        "logic_changes": True,
        "visual_changes": True,
        "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_two_charts_one_shared_renderer_bit_identical_chart_formatting_data_specific_labels_count_averages_include_zero_days_and_public_read_only_status_card_without_authenticated_context",
    }
)
architecture_path.write_text(
    json.dumps(architecture_data, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

changelog_path = "CHANGELOG.md"
changelog = read(changelog_path)
entry = f'''## {VERSION} — Leitura e gravação como cópia visual de Velocidade

- descarta integralmente a geometria segmentada, a interseção e as classes exclusivas do gráfico Leitura e gravação;
- reutiliza o mesmo renderer, SVG, classes, ordem de pintura, espessuras, preenchimentos, marcadores e ícone do gráfico Velocidade;
- altera somente as séries, os rótulos e a formatação numérica para Requisições e Registros;
- preserva a página pública /status, as métricas, o banco e o schema.

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
text_entry = f'''Prontoo {VERSION} — Leitura e gravação como cópia visual de Velocidade

- Remove integralmente a apresentação específica e segmentada de Leitura e gravação.
- O segundo gráfico passa a usar o mesmo renderer, classes, SVG, ordem de pintura e ícone de Velocidade.
- Somente dados, rótulos e valores mudam para Requisições e Registros.
- Banco e schema permanecem inalterados.

'''
write(text_changelog_path, text_entry + text_changelog)
