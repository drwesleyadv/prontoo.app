from pathlib import Path
from datetime import datetime, timezone
import hashlib
import json
import re
import subprocess
import time

BASE_COMMIT = "c98ead219d8e76351f3e1f8da4e2417f5346aa38"
VERSION = "1.7.31.1"
PREVIOUS = "1.7.30.22"
BUILD = "1.7.31.1-smooth-overlapping-performance-mountains"
DEPLOYMENT_SYNC_ID = "github-smooth-overlapping-performance-mountains-1-7-31-1"
NOW = datetime.now(timezone.utc).isoformat()
NOW_UNIX = int(time.time())


def replace_once(path: str, old: str, new: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise RuntimeError(f"Substituição não determinística em {path}: {count}")
    file.write_text(text.replace(old, new, 1), encoding="utf-8")


def regex_once(path: str, pattern: str, replacement: str) -> None:
    file = Path(path)
    text = file.read_text(encoding="utf-8")
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise RuntimeError(f"Substituição regex não determinística em {path}: {count}")
    file.write_text(updated, encoding="utf-8")


smooth_path = '''    $path = static function (array $points): string {

        $count = count($points);
        if ($count === 0) {
            return "";
        }
        if ($count === 1) {
            return "M" . $points[0][0] . " " . $points[0][1];
        }
        $tension = 0.72;
        $d = "M" . $points[0][0] . " " . $points[0][1];
        for ($i = 0; $i < $count - 1; $i++) {
            $p0 = $points[max(0, $i - 1)];
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            $p3 = $points[min($count - 1, $i + 2)];
            $cp1x = $p1[0] + (($p2[0] - $p0[0]) * $tension) / 6;
            $cp1y = $p1[1] + (($p2[1] - $p0[1]) * $tension) / 6;
            $cp2x = $p2[0] - (($p3[0] - $p1[0]) * $tension) / 6;
            $cp2y = $p2[1] - (($p3[1] - $p1[1]) * $tension) / 6;
            $segmentMinY = min($p1[1], $p2[1]);
            $segmentMaxY = max($p1[1], $p2[1]);
            $cp1y = max($segmentMinY, min($segmentMaxY, $cp1y));
            $cp2y = max($segmentMinY, min($segmentMaxY, $cp2y));
            $d .=
                " C " .
                round($cp1x, 2) .
                " " .
                round($cp1y, 2) .
                " " .
                round($cp2x, 2) .
                " " .
                round($cp2y, 2) .
                " " .
                $p2[0] .
                " " .
                $p2[1];
        }
        return $d;
    };
'''
regex_once(
    "app/Admin/AdminPages.php",
    r"    \$path = static function \(array \$points\): string \{.*?^    \};\n(?=    \$fill = static function)",
    smooth_path,
)
regex_once(
    "app/Admin/AdminPages.php",
    r"    \$betweenFill = static function \(.*?^    \};\n(?=    \$loadPoints =)",
    "",
)
replace_once(
    "app/Admin/AdminPages.php",
    '''    $loadFill =
        $valueType === "count"
            ? $betweenFill($loadPoints, $responsePoints)
            : $fill($loadD, $loadPoints, $baseline);
    $responseFill = $fill($responseD, $responsePoints, $baseline);
''',
    '''    $loadFill = $fill($loadD, $loadPoints, $baseline);
    $responseFill = $fill($responseD, $responsePoints, $baseline);
''',
)
replace_once(
    "app/Admin/AdminPages.php",
    '''    $fillAreas =
        $valueType === "count"
            ? $responseArea . $loadArea
            : $loadArea . $responseArea;
''',
    '''    $fillAreas = $loadArea . $responseArea;
''',
)

replace_once(
    "public/assets/design-system.css",
    '''.metric-dual-time-chart .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);opacity:.38}
.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}
.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}
''',
    '''.metric-dual-time-chart .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}
.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}
''',
)

replace_once(
    "tools/architecture-check.php",
    '''        '$betweenFill = static function (',
        '? $betweenFill($loadPoints, $responsePoints)',
        '$fillAreas =',
        '? $responseArea . $loadArea',
        ': $loadArea . $responseArea;',
''',
    '''        '$tension = 0.72;',
        '" C " .',
        '$loadFill = $fill($loadD, $loadPoints, $baseline);',
        '$responseFill = $fill($responseD, $responsePoints, $baseline);',
        '$fillAreas = $loadArea . $responseArea;',
''',
)
replace_once(
    "tools/architecture-check.php",
    '''        '.metric-dual-time-chart .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 30%,#111827 18%);opacity:.38}',
        '.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 18%,white 72%);opacity:.78}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}',
        '.metric-dual-time-chart[data-metric-value-type="count"] .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}',
''',
    '''        '.metric-dual-time-chart .metric-chart-fill-load{fill:color-mix(in srgb,var(--md-sys-color-primary) 78%,#111827 22%);opacity:1}',
        '.metric-dual-time-chart .metric-chart-fill-response{fill:color-mix(in srgb,var(--md-sys-color-primary) 42%,white 58%);opacity:1}',
''',
)
replace_once(
    "tools/architecture-check.php",
    "dual-area-single-renderer-count-opaque-layered-mountain-fills-data-specific-labels-one-row",
    "dual-area-single-renderer-two-opaque-overlapping-smooth-mountains-dark-back-light-front-data-specific-labels-one-row",
)
replace_once(
    "tools/architecture-check.php",
    '''    'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
    '$segmentedCountAreas = static function (',
''',
    '''    'color-mix(in srgb,var(--pt-color-success) 16%,transparent)',
    '$betweenFill = static function (',
    '? $responseArea . $loadArea',
    '[data-metric-value-type="count"] .metric-chart-fill',
    '$segmentedCountAreas = static function (',
''',
)

replace_once(
    "app/prontoo.php",
    'const PRONTOO_VERSION_FALLBACK = "1.7.30.22";',
    'const PRONTOO_VERSION_FALLBACK = "1.7.31.1";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PREVIOUS_VERSION = "1.7.30.21";',
    'const PRONTOO_PREVIOUS_VERSION = "1.7.30.22";',
)
replace_once(
    "app/prontoo.php",
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-single-renderer-count-opaque-layered-mountain-fills-data-specific-labels-one-row";',
    'const PRONTOO_PERFORMANCE_CHARTS_POLICY = "dual-area-single-renderer-two-opaque-overlapping-smooth-mountains-dark-back-light-front-data-specific-labels-one-row";',
)
replace_once(
    "br/index.php",
    'const BR_LANDING_VERSION_FALLBACK = "1.7.30.22";',
    'const BR_LANDING_VERSION_FALLBACK = "1.7.31.1";',
)

version_path = Path("version.json")
version = json.loads(version_path.read_text(encoding="utf-8"))
version.update({
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
    "functional_equivalence_policy": "preserves_metric_values_labels_colors_and_shared_renderer_while_both_charts_use_two_opaque_baseline_mountains_with_dark_back_light_front_and_clamped_bezier_smoothing",
    "notes": "Velocidade e Leitura e gravação passam a renderizar duas montanhas completas e opacas até a base: série escura ao fundo e série clara à frente, com curvas Bézier suavizadas e sem vértices angulosos.",
    "deployment_sync_id": DEPLOYMENT_SYNC_ID,
    "deployment_sync_requested_at": NOW,
    "rewrite_scope": "developer_dashboard_two_smooth_overlapping_performance_mountains",
})
version_path.write_text(json.dumps(version, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

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
    "logic_changes": True,
    "visual_changes": True,
    "developer_dashboard_observability_policy": "single_global_dashboard_four_kpis_two_charts_one_shared_renderer_two_opaque_overlapping_smooth_mountains_dark_back_light_front_data_specific_labels_and_public_read_only_status_card_without_authenticated_context",
    "developer_dashboard_count_area_policy": "both_performance_charts_render_primary_dark_and_secondary_light_full_baseline_mountains_in_back_to_front_order_with_opacity_one_and_clamped_catmull_rom_bezier_smoothing",
})
architecture_path.write_text(json.dumps(architecture, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")

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
    "notes": "Velocidade e Leitura e gravação usam duas montanhas opacas suavizadas: escura ao fundo e clara à frente.",
})

md_entry = (
    "## 1.7.31.1 — Montanhas sobrepostas e suavizadas nos gráficos de performance\n\n"
    "- renderiza, em Velocidade e Leitura e gravação, duas montanhas completas até a linha de base;\n"
    "- mantém a série escura ao fundo e a série clara à frente, ambas sem transparência;\n"
    "- usa exatamente as cores dos respectivos contornos nos preenchimentos;\n"
    "- substitui segmentos angulosos por curvas Bézier com controles limitados entre os pontos métricos;\n"
    "- não altera banco de dados nem schema.\n\n"
)
txt_entry = (
    "Prontoo 1.7.31.1 — Montanhas sobrepostas e suavizadas nos gráficos de performance\n\n"
    "- Velocidade e Leitura e gravação passam a ter duas montanhas completas até a base.\n"
    "- A montanha escura fica ao fundo e a clara à frente, sem transparência.\n"
    "- Os vértices são suavizados por curvas Bézier.\n"
    "- Banco de dados e schema permanecem inalterados.\n\n"
)
changelog = Path("CHANGELOG.md")
changelog_text = changelog.read_text(encoding="utf-8")
marker = "# Histórico de versões\n\n"
if changelog_text.count(marker) != 1:
    raise RuntimeError("Cabeçalho canônico do CHANGELOG não encontrado")
changelog.write_text(changelog_text.replace(marker, marker + md_entry, 1), encoding="utf-8")
legacy = Path("ChangeLog.txt")
legacy.write_text(txt_entry + legacy.read_text(encoding="utf-8"), encoding="utf-8")

Path(".github/workflows/architecture.yml").write_bytes(
    subprocess.check_output(["git", "show", BASE_COMMIT + ":.github/workflows/architecture.yml"])
)
Path(".github/smooth_mountain_publish.py").unlink(missing_ok=True)

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
manifest_path.write_text(json.dumps(manifest, ensure_ascii=False, indent=4) + "\n", encoding="utf-8")
