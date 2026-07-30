from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import subprocess
import time

BASE_COMMIT = "b3186fd5b75afc0f013dd516ee7ddf85a35cdf7d"
VERSION = "1.7.30.21"
PREVIOUS = "1.7.30.20"
BUILD = "1.7.30.21-read-write-opaque-mountain-fills"
DEPLOYMENT_SYNC_ID = "github-read-write-opaque-mountain-fills-1-7-30-21"
NOW = datetime.now(timezone.utc).isoformat()
NOW_UNIX = int(time.time())


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"Substituição não determinística em {path}: {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


replace_once(
    "app/Admin/AdminPages.php",
    '''    return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-ds-card="admin-dual-area-chart" aria-label="' .
''',
    '''    return '<article class="metric-line-chart metric-area-chart metric-dual-time-chart" data-metric-value-type="' .
        e($valueType) .
        '" data-ds-card="admin-dual-area-chart" aria-label="' .
''',
)

replace_once(
    "public/assets/design-system.css",
    '''.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}
''',
    '''.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}
''',
)

replace_once(
    "tools/architecture-check.php",
    '''        '"secondary_label" => "Registros"',
        '"value_type" => "count"',
        '$fillAreas = $loadArea . $responseArea;',
''',
    '''        '"secondary_label" => "Registros"',
        '"value_type" => "count"',
        'data-metric-value-type="',
        '$fillAreas = $loadArea . $responseArea;',
''',
)
replace_once(
    "tools/architecture-check.php",
    '''        '.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}',
''',
    '''        '.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}',
''',
)
replace_once(
    "tools/architecture-check.php",
    '''    'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load{',
    '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{',
    '? $responseArea . $loadArea',
''',
    '''    'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
    '? $responseArea . $loadArea',
''',
)
replace_once(
    "tools/architecture-check.php",
    '''    'metric-chart-fill-intersection',
    'data-metric-value-type="',
] as $outlineToken) {
''',
    '''    'metric-chart-fill-intersection',
] as $outlineToken) {
''',
)
replace_once(
    "tools/architecture-check.php",
    "dual-area-single-renderer-bit-identical-formatting-data-specific-labels-one-row",
    "dual-area-single-renderer-count-opaque-mountain-fills-data-specific-labels-one-row",
)

replace_once(
    "app/prontoo.php",
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.20";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.21";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PREVIOUS_VERSION = "1.7.30.19";',
    'const PRONTOO_PREVIOUS_VERSION = "1.7.30.20";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-single-renderer-bit-identical-formatting-data-specific-labels-one-row";',
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-single-renderer-count-opaque-mountain-fills-data-specific-labels-one-row";',
)
replace_once(
    "br/index.php",
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.20";',
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.21";',
)

version_path = Path("version.json")
metadata = json.loads(version_path.read_text(encoding="utf-8"))
metadata.update({
    "version": VERSION,
    "release": VERSION,
    "generated_at_unix": NOW_UNIX,
    "generated_at": NOW,
    "updated_at": NOW,
    "build": BUILD,
    "previous_version": PREVIOUS,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
    "functional_equivalence_policy": "preserves_metrics_lines_labels_and_shared_renderer_while_read_write_count_areas_use_exact_opaque_line_colors",
    "notes": "Leitura e gravação mantém as cores das linhas e passa a preencher integralmente cada área inferior com a mesma cor, sem transparência, criando duas montanhas opacas.",
    "deployment_sync_id": DEPLOYMENT_SYNC_ID,
    "deployment_sync_requested_at": NOW,
    "rewrite_scope": "developer_dashboard_read_write_chart_opaque_count_fills",
})
version_path.write_text(
    json.dumps(metadata, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

architecture_path = Path("app/architecture.manifest.json")
architecture = json.loads(architecture_path.read_text(encoding="utf-8"))
architecture.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "generated_at": NOW,
    "updated_at": NOW,
    "database_changes": False,
    "schema_changes": False,
    "visual_changes": True,
    "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_two_charts_one_shared_renderer_count_chart_exact_line_color_opaque_mountain_fills_data_specific_labels_and_public_read_only_status_card_without_authenticated_context",
    "developer_dashboard_count_area_policy": "read_write_request_and_record_areas_fill_to_baseline_with_exact_respective_line_colors_and_opacity_one",
})
architecture_path.write_text(
    json.dumps(architecture, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)

manifest_path = Path("app/update.manifest.json")
manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
manifest.update({
    "version": VERSION,
    "release": VERSION,
    "build": BUILD,
    "previous_version": PREVIOUS,
    "deployment_sync_id": DEPLOYMENT_SYNC_ID,
    "generated_at": NOW,
    "updated_at": NOW,
    "database_changes": False,
    "schema_changes": False,
    "logic_changes": True,
    "visual_changes": True,
    "documentation_changes": True,
})

md_entry = (
    "## 1.7.30.21 — Montanhas opacas em Leitura e gravação\n\n"
    "- mantém inalteradas as cores e espessuras das linhas de Requisições e Registros;\n"
    "- aplica à área inferior de cada série exatamente a mesma cor da respectiva linha;\n"
    "- remove integralmente a transparência dos dois preenchimentos, formando montanhas opacas até a linha de base;\n"
    "- preserva o preenchimento translúcido do gráfico Velocidade e o renderer compartilhado;\n"
    "- não altera banco de dados nem schema.\n\n"
)
txt_entry = (
    "Prontoo 1.7.30.21 — Montanhas opacas em Leitura e gravação\n\n"
    "- Mantém as cores e espessuras das linhas de Requisições e Registros.\n"
    "- Preenche cada área inferior com a mesma cor da linha correspondente.\n"
    "- Remove a transparência dos dois preenchimentos.\n"
    "- Preserva Velocidade, banco de dados e schema.\n\n"
)
changelog = Path("CHANGELOG.md")
changelog_text = changelog.read_text(encoding="utf-8")
marker = "# Histórico de versões\n\n"
if changelog_text.count(marker) != 1:
    raise RuntimeError("Cabeçalho canônico do CHANGELOG não encontrado")
changelog.write_text(
    changelog_text.replace(marker, marker + md_entry, 1),
    encoding="utf-8",
)
legacy = Path("ChangeLog.txt")
legacy.write_text(txt_entry + legacy.read_text(encoding="utf-8"), encoding="utf-8")

Path(".github/workflows/architecture.yml").write_bytes(
    subprocess.check_output([
        "git",
        "show",
        BASE_COMMIT + ":.github/workflows/architecture.yml",
    ])
)
Path(".github/read-write-mountain-fills.trigger").unlink(missing_ok=True)
Path(".github/read_write_mountain_publish.py").unlink(missing_ok=True)

files = {}
total = 0
excluded_roots = (".git/", "ssd/", "vendor/", "node_modules/")
for file in Path(".").rglob("*"):
    if not file.is_file() or file.is_symlink():
        continue
    relative = file.as_posix()
    if relative == "app/update.manifest.json" or relative.startswith(excluded_roots):
        continue
    data = file.read_bytes()
    files[relative] = hashlib.sha256(data).hexdigest()
    total += len(data)
manifest["files"] = dict(sorted(files.items()))
manifest["file_count"] = len(files)
manifest["total_uncompressed_bytes"] = total
manifest["updated_at"] = NOW
manifest_path.write_text(
    json.dumps(manifest, ensure_ascii=False, indent=4) + "\n",
    encoding="utf-8",
)
