from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = "1.7.30.13"
PREVIOUS = "1.7.30.12"
BUILD = "1.7.30.13-shared-speed-chart-renderer"
DEPLOYMENT_ID = "github-shared-speed-chart-renderer-1-7-30-13"
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
area_start = admin.index("function admin_metric_dual_area_chart(")
count_start = admin.index("function admin_metric_dual_count_chart(", area_start)
area = admin[area_start:count_start]
area = replace_once(
    area,
    '''function admin_metric_dual_area_chart(
    string $title,
    array $loadSeries,
    array $responseSeries,
    string $iconName = "speed",
): string {

    $loadValues''',
    '''function admin_metric_dual_area_chart(
    string $title,
    array $loadSeries,
    array $responseSeries,
    string $iconName = "speed",
    array $presentation = [],
): string {

    $primaryLabel = trim((string) ($presentation["primary_label"] ?? "Carregamento"));
    $secondaryLabel = trim((string) ($presentation["secondary_label"] ?? "Resposta"));
    $valueType = (string) ($presentation["value_type"] ?? "ms");
    if ($primaryLabel === "") {
        $primaryLabel = "Carregamento";
    }
    if ($secondaryLabel === "") {
        $secondaryLabel = "Resposta";
    }
    if (!in_array($valueType, ["ms", "count"], true)) {
        $valueType = "ms";
    }
    $recentPoints = max(1, (int) ($presentation["recent_points"] ?? 5));
    $middlePoints = max(1, (int) ($presentation["middle_points"] ?? 31));
    $recentTitle = trim((string) ($presentation["recent_title"] ?? "Carregamento médio dos últimos 5 minutos"));
    $middleTitle = trim((string) ($presentation["middle_title"] ?? "Carregamento médio dos últimos 30 minutos"));
    $overallTitle = trim((string) ($presentation["overall_title"] ?? "Carregamento médio das últimas 24 horas"));
    $summaryLead = trim((string) ($presentation["summary_lead"] ?? "indicadores exibem somente o tempo de carregamento."));
    $loadValues''',
    "generic dual area renderer signature",
)
area = area.replace('" · Carregamento "', '" · " . $primaryLabel . " "')
area = area.replace('" · Resposta "', '" · " . $secondaryLabel . " "')
area = area.replace('admin_metric_value_label($pt[2], "ms")', 'admin_metric_value_label($pt[2], $valueType)')
area = area.replace('admin_metric_value_label($lastLoad[2], "ms")', 'admin_metric_value_label($lastLoad[2], $valueType)')
area = area.replace('admin_metric_value_label($lastResp[2], "ms")', 'admin_metric_value_label($lastResp[2], $valueType)')
metrics_start = area.index("    $nowAvg5 = admin_metric_recent_average")
metrics_end = area.index("    $lastLoad = end($loadPoints);", metrics_start)
metrics = '''    $recentValue = admin_metric_recent_average($loadSeries, $recentPoints);
    $loadNonZero = array_values(
        array_filter($loadValues, static fn($v) => (float) $v > 0),
    );
    $overallValue = count($loadNonZero)
        ? array_sum($loadNonZero) / count($loadNonZero)
        : 0.0;
    $middleValues = array_slice($loadValues, -$middlePoints);
    $middleNonZero = array_values(
        array_filter($middleValues, static fn($v) => (float) $v > 0),
    );
    $middleValue = count($middleNonZero)
        ? array_sum($middleNonZero) / count($middleNonZero)
        : 0.0;
    $recentCompact = admin_metric_value_compact($recentValue, $valueType);
    $middleCompact = admin_metric_value_compact($middleValue, $valueType);
    $overallCompact = admin_metric_value_compact($overallValue, $valueType);
    $pills =
        '<span title="' .
        e($recentTitle) .
        '" aria-label="' .
        e($recentTitle . ": " . $recentCompact) .
        '">' .
        icon("radio_button_checked") .
        "<b>" .
        e($recentCompact) .
        "</b></span>" .
        '<span title="' .
        e($middleTitle) .
        '" aria-label="' .
        e($middleTitle . ": " . $middleCompact) .
        '">' .
        icon("timer") .
        "<b>" .
        e($middleCompact) .
        "</b></span>" .
        '<span title="' .
        e($overallTitle) .
        '" aria-label="' .
        e($overallTitle . ": " . $overallCompact) .
        '">' .
        icon("calendar_today") .
        "<b>" .
        e($overallCompact) .
        "</b></span>";
    $legend = "";
'''
area = area[:metrics_start] + metrics + area[metrics_end:]
summary_start = area.index("    $summary =", area.index("    $lastLoad = end($loadPoints);"))
return_start = area.index("    return '<article", summary_start)
summary = '''    $summary =
        $title .
        ": " .
        $summaryLead .
        " " .
        $recentTitle .
        " " .
        $recentCompact .
        ", " .
        $middleTitle .
        " " .
        $middleCompact .
        ", " .
        $overallTitle .
        " " .
        $overallCompact .
        ".";
'''
area = area[:summary_start] + summary + area[return_start:]
admin = admin[:area_start] + area + admin[count_start:]
count_start = admin.index("function admin_metric_dual_count_chart(")
sequence_start = admin.index("function admin_global_sequence_series_30d(): array", count_start)
admin = admin[:count_start] + admin[sequence_start:]
admin = replace_once(
    admin,
    '''        admin_metric_dual_count_chart(
            "Leitura e gravação",
            $requests,
            $records,
            "monitoring",
        ) .''',
    '''        admin_metric_dual_area_chart(
            "Leitura e gravação",
            $requests,
            $records,
            "monitoring",
            [
                "primary_label" => "Requisições",
                "secondary_label" => "Registros",
                "value_type" => "count",
                "recent_points" => 1,
                "middle_points" => 7,
                "recent_title" => "Requisições de hoje",
                "middle_title" => "Média diária de requisições nos últimos 7 dias",
                "overall_title" => "Média diária de requisições nos últimos 30 dias",
                "summary_lead" => "dados de Requisições e Registros dos últimos 30 dias.",
            ],
        ) .''',
    "reuse speed renderer call",
)
write(admin_path, admin)

css_path = "public/assets/design-system.css"
css = read(css_path)
css = replace_once(
    css,
    '''.metric-dual-count-chart .metric-count-pill{gap:5px!important;}
.metric-dual-count-chart .metric-count-pill small{font:inherit;color:inherit;}
.metric-dual-count-chart .metric-series-key{display:inline-block;width:9px;height:9px;border-radius:999px;flex:0 0 auto;}
.metric-dual-count-chart .is-requests .metric-series-key{background:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);}
.metric-dual-count-chart .is-records .metric-series-key{background:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);box-shadow:inset 0 0 0 1px color-mix(in srgb,var(--md-sys-color-primary) 18%,transparent);}
''',
    "",
    "remove obsolete count renderer styles",
)
css = replace_once(
    css,
    '''.metric-dual-time-chart .metric-chart-fill-load,.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);stroke:none;opacity:.38}
.metric-dual-time-chart .metric-chart-fill-response,.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);stroke:none;opacity:.78}''',
    '''.metric-dual-time-chart .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);opacity:.38}
.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}''',
    "restore single renderer css",
)
write(css_path, css)

check_path = "tools/architecture-check.php"
check = read(check_path)
check = replace_once(
    check,
    '''        'function admin_metric_dual_count_chart(',
        '"Requisições"',
        '"Tempo Médio"',
        '"Landing Page"',
        '"Usuários Ativos"',
        '"Leitura e gravação"',
        'metric-chart-fill-load metric-chart-fill-requests',
        'metric-chart-fill-response metric-chart-fill-records',''',
    '''        'function admin_metric_dual_area_chart(',
        'array $presentation = []',
        '"Requisições"',
        '"Tempo Médio"',
        '"Landing Page"',
        '"Usuários Ativos"',
        '"Leitura e gravação"',
        '"primary_label" => "Requisições"',
        '"secondary_label" => "Registros"',
        '"value_type" => "count"',''',
    "architecture renderer requirements",
)
check = replace_once(
    check,
    '''        '.metric-dual-time-chart .metric-chart-fill-load,.metric-dual-count-chart .metric-chart-fill-requests{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);stroke:none;opacity:.38}',
        '.metric-dual-time-chart .metric-chart-fill-response,.metric-dual-count-chart .metric-chart-fill-records{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);stroke:none;opacity:.78}',
        'stroke:none',''',
    '''        '.metric-dual-time-chart .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);opacity:.38}',
        '.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}',
        '.metric-dual-time-chart .metric-chart-line-load',
        '.metric-dual-time-chart .metric-chart-line-response',''',
    "architecture exact speed css",
)
check = replace_once(
    check,
    "        'dual-area-shared-css-palette-identical-compositing-order-no-series-outline-one-row',",
    "        'dual-area-single-renderer-identical-visuals-data-specific-labels-one-row',",
    "architecture performance policy",
)
old_validation_start = check.index("$requestAreaPosition = strpos(")
old_validation_end = check.index("foreach ([\n    'admin_metric_bar_chart('", old_validation_start)
new_validation = '''if (substr_count(
    $developerDashboardSources['admin'],
    'admin_metric_dual_area_chart(',
) < 3) {
    $developerDashboardFailures[] = 'developer_dashboard_shared_renderer_call_count';
}
foreach ([
    'function admin_metric_dual_count_chart(',
    'metric-dual-count-chart',
    'metric-count-pill',
    'metric-series-key',
    'metric-chart-fill-requests',
    'metric-chart-fill-records',
] as $obsoleteCountRendererToken) {
    if (str_contains(
        $developerDashboardSources['admin'] .
            $developerDashboardSources['css'],
        $obsoleteCountRendererToken,
    )) {
        $developerDashboardFailures[] =
            'obsolete_count_renderer_token:' . $obsoleteCountRendererToken;
    }
}
'''
check = check[:old_validation_start] + new_validation + check[old_validation_end:]
write(check_path, check)

prontoo_path = "app/prontoo.php"
prontoo = read(prontoo_path)
prontoo = replace_once(prontoo, 'const PRONTOO_VERSION_FALLBACK = "1.7.30.12";', 'const PRONTOO_VERSION_FALLBACK = "1.7.30.13";', "runtime version")
prontoo = replace_once(
    prontoo,
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-shared-css-palette-identical-compositing-order-no-series-outline-one-row";',
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-single-renderer-identical-visuals-data-specific-labels-one-row";',
    "performance chart policy",
)
write(prontoo_path, prontoo)

br_path = "br/index.php"
br = read(br_path)
br = replace_once(br, 'const BR_LANDING_VERSION_FALLBACK = "1.7.30.12";', 'const BR_LANDING_VERSION_FALLBACK = "1.7.30.13";', "landing version")
write(br_path, br)

version_path = ROOT / "version.json"
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
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
    "functional_equivalence_policy": "preserves_metrics_and_renders_speed_and_read_write_charts_through_the_same_function_markup_and_css",
    "notes": "Leitura e gravação passa a ser renderizado literalmente pela mesma função do gráfico Velocidade, alterando somente dados, rótulos e unidade.",
    "previous_version": PREVIOUS,
    "documentation_changes": True,
    "deployment_sync_id": DEPLOYMENT_ID,
    "deployment_sync_requested_at": NOW_ISO,
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "rewrite_scope": "developer_dashboard_single_shared_dual_area_renderer",
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

architecture_path = ROOT / "app/architecture.manifest.json"
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
    "version": VERSION,
    "generated_at": NOW_ISO,
    "updated_at": NOW_ISO,
    "baseline_source": PREVIOUS,
    "full_baseline_rewrite": False,
    "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_landing_request_count_and_two_charts_rendered_by_one_shared_dual_area_function_with_data_specific_labels",
})
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

update_path = ROOT / "app/update.manifest.json"
update = json.loads(update_path.read_text(encoding="utf-8"))
update.update({
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
    "notes": "Velocidade e Leitura e gravação utilizam o mesmo renderer dual-area; somente dados, rótulos e unidade diferem.",
    "deployment_sync_id": DEPLOYMENT_ID,
})
update_path.write_text(json.dumps(update, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

changelog_path = "CHANGELOG.md"
changelog = read(changelog_path)
entry = '''## 1.7.30.13 — Renderer único para os gráficos do Painel

- elimina o renderer específico de Leitura e gravação;
- renderiza Velocidade e Leitura e gravação pela mesma função, com HTML, SVG, áreas, linhas, pontos, eixos e tooltips idênticos;
- altera somente dados, rótulos e unidade para Requisições e Registros;
- remove estilos exclusivos e divergentes do gráfico anterior;
- mantém banco e schema inalterados.

'''
changelog = replace_once(changelog, "# Histórico de versões\n\n", "# Histórico de versões\n\n" + entry, "markdown changelog")
write(changelog_path, changelog)

text_changelog_path = "ChangeLog.txt"
text_changelog = read(text_changelog_path)
text_entry = '''Prontoo 1.7.30.13 — Renderer único para os gráficos do Painel

- Leitura e gravação passa a usar literalmente o mesmo renderer do gráfico Velocidade.
- Somente dados, rótulos e unidade são específicos para Requisições e Registros.
- Remove o renderer e os estilos exclusivos anteriores.
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
